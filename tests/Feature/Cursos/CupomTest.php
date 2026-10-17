<?php

/*
 * Testes do CUPOM PROMOCIONAL (#89): desconto percentual e fixo na matrícula,
 * Pix com o valor descontado, cupom de 100% confirma na hora, cada recusa com
 * o código certo, limite à prova de reuso, desconto ANTES da divisão 20/80 e a
 * tela de cupons só para quem tem "cupons.gerenciar". Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Cupom;
use App\Models\Curso;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\PagamentoConfig;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * Um curso pago e publicado (R$ 250,00, 20% do IBTO).
 */
function cursoPago(int $valor = 25000): Curso
{
    $curso = new Curso(['titulo' => 'Combate com Faca', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => $valor, 'instrutor_nome' => 'Marcelo']);
    $curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'percentual_ibto' => 20])->save();

    return $curso;
}

/**
 * Um cupom ativo, com o que for trocado.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['tipo' => 'valor', 'valor' => 5000]
 */
function cupom(array $troca = []): Cupom
{
    return Cupom::create([...['codigo' => 'FILIADO10', 'tipo' => 'percentual', 'valor' => 10, 'limite_por_pessoa' => 1, 'todos_cursos' => true, 'ativo' => true], ...$troca]);
}

/**
 * Espera a recusa com o código pedido.
 */
function recusaCom(string $codigo, callable $acao): void
{
    expect($acao)->toThrow(fn (RegraCurso $erro) => expect($erro->codigo)->toBe($codigo));
}

beforeEach(function () {
    Notification::fake();
    $this->curso = cursoPago();
    $this->aluno = User::factory()->create();
});

test('10% sobre R$ 250,00: a matrícula grava R$ 225,00, o preço cheio e o desconto', function () {
    cupom();
    $matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno, cupom: ' filiado10 ');

    expect($matricula->valor_centavos)->toBe(22500)
        ->and($matricula->valor_cheio_centavos)->toBe(25000)
        ->and($matricula->desconto_centavos)->toBe(2500)
        ->and($matricula->situacao)->toBe(SituacaoMatricula::AguardandoPagamento);
});

test('o Pix copia e cola sai com o valor já descontado', function () {
    PagamentoConfig::atual()->update(['pix_chave' => 'financeiro@ibto.test', 'favorecido' => 'IBTO Teste']);
    cupom(['tipo' => 'valor', 'valor' => 5000]);

    $this->actingAs($this->aluno)->post(route('matriculas.store', $this->curso), ['cupom' => 'FILIADO10'])->assertSessionHasNoErrors();

    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->where('matricula.valor_centavos', 20000)
            ->where('matricula.cupom', 'FILIADO10')
            ->where('matricula.pix.copia_e_cola', fn ($codigo) => str_contains($codigo, '5406200.00')));
});

test('cupom de 100% confirma a matrícula na hora (nada a pagar)', function () {
    cupom(['valor' => 100]);
    $matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno, cupom: 'FILIADO10');

    expect($matricula->valor_centavos)->toBe(0)->and($matricula->situacao)->toBe(SituacaoMatricula::Confirmada);
});

test('cada recusa tem o seu código', function () {
    $matriculas = app(Matriculas::class);

    recusaCom('CUPOM_INVALIDO', fn () => $matriculas->matricular($this->curso, $this->aluno, cupom: 'NAOEXISTE'));

    cupom(['codigo' => 'DESLIGADO', 'ativo' => false]);
    recusaCom('CUPOM_INVALIDO', fn () => $matriculas->matricular($this->curso, $this->aluno, cupom: 'DESLIGADO'));

    cupom(['codigo' => 'VENCIDO', 'valido_ate' => now()->subDay()]);
    recusaCom('CUPOM_VENCIDO', fn () => $matriculas->matricular($this->curso, $this->aluno, cupom: 'VENCIDO'));

    cupom(['codigo' => 'FUTURO', 'valido_de' => now()->addDay()]);
    recusaCom('CUPOM_AINDA_NAO_VALE', fn () => $matriculas->matricular($this->curso, $this->aluno, cupom: 'FUTURO'));

    $outro = cupom(['codigo' => 'SOOUTRO', 'todos_cursos' => false]);
    $outro->cursos()->sync([cursoPago()->id]);
    recusaCom('CUPOM_NAO_VALE_NESTE_CURSO', fn () => $matriculas->matricular($this->curso, $this->aluno, cupom: 'SOOUTRO'));

    cupom(['codigo' => 'SOFILIADOS', 'so_filiados' => true]);
    recusaCom('CUPOM_SO_FILIADOS', fn () => $matriculas->matricular($this->curso, $this->aluno, cupom: 'SOFILIADOS'));
});

test('limite total e por pessoa; matrícula cancelada devolve o uso', function () {
    cupom(['limite_total' => 1]);
    $matriculas = app(Matriculas::class);

    $primeira = $matriculas->matricular($this->curso, $this->aluno, cupom: 'FILIADO10');
    // Outra pessoa: o único uso já foi.
    recusaCom('CUPOM_ESGOTADO', fn () => $matriculas->matricular(cursoPago(), User::factory()->create(), cupom: 'FILIADO10'));

    // Cancelou: o uso volta.
    $matriculas->cancelar($primeira, 'Desistiu');
    expect($matriculas->matricular(cursoPago(), User::factory()->create(), cupom: 'FILIADO10')->desconto_centavos)->toBe(2500);
});

test('o desconto entra ANTES da divisão: receita e repasse de 80% sobre o valor pago', function () {
    cupom();
    $matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno, cupom: 'FILIADO10');
    app(Matriculas::class)->confirmarPagamento($matricula, today()->toDateString(), (int) FinFormaPagamento::where('nome', 'PIX')->value('id'));

    $receita = FinLancamento::where('origem', 'matricula')->firstOrFail();
    expect($receita->valor_centavos)->toBe(22500)->and($receita->descricao)->toContain('cupom FILIADO10');
    // 80% de R$ 225,00 = R$ 180,00.
    expect(FinLancamento::where('origem', 'repasse')->value('valor_centavos'))->toBe(18000);
});

test('a tela de cupons: o Comando Geral cria; quem não tem a permissão recebe 403', function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    $this->actingAs($admin)->post(route('financeiro.cupons.store'), [
        'codigo' => 'natal 15', 'tipo' => 'percentual', 'valor' => '15', 'limite_por_pessoa' => 1, 'todos_cursos' => '1', 'ativo' => '1',
    ])->assertRedirect(route('financeiro.cupons.index'));
    expect(Cupom::where('codigo', 'NATAL15')->value('valor'))->toBe(15);

    // Percentual acima de 100 é recusado.
    $this->actingAs($admin)->post(route('financeiro.cupons.store'), ['codigo' => 'ERRADO', 'tipo' => 'percentual', 'valor' => '150', 'limite_por_pessoa' => 1, 'todos_cursos' => '1'])
        ->assertSessionHasErrors('valor');

    $this->actingAs(User::factory()->create())->get(route('financeiro.cupons.index'))->assertForbidden();
});
