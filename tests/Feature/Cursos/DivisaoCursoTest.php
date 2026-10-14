<?php

/*
 * Testes da DIVISÃO DO DINHEIRO de cada curso (decisão do Gleyson,
 * 14/10/2026): o IBTO escolhe o percentual no cadastro (padrão 20%); ao
 * confirmar o pagamento, a parte do instrutor vira uma conta a pagar
 * "Instrutores"; com 100% do IBTO (Formação de instrutor) não sai conta a
 * pagar; só o IBTO muda o percentual. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // O e-mail de matrícula confirmada não sai de verdade no teste.
    Notification::fake();
    $this->pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
});

/**
 * Cria um curso publicado e pago com o percentual pedido.
 *
 * @param  int  $percentual  Parte do IBTO. Ex.: 20
 * @param  int  $valor  Preço em centavos. Ex.: 25000 (R$ 250,00)
 */
function cursoComDivisao(int $percentual, int $valor = 25000): Curso
{
    $curso = new Curso([
        'titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'online', 'acesso' => 'aberto',
        'valor_centavos' => $valor, 'instrutor_nome' => 'Trinca',
    ]);
    $curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'percentual_ibto' => $percentual])->save();

    return $curso;
}

test('a conta da divisão: 20%, 100%, 0% e o centavo que sobra vai para o instrutor', function () {
    // R$ 250,00 com 20%: IBTO R$ 50,00 e instrutor R$ 200,00.
    expect(cursoComDivisao(20)->divisao(25000))->toBe(['ibto' => 5000, 'instrutor' => 20000]);
    // 100% (Formação de instrutor): tudo do IBTO.
    expect(cursoComDivisao(100)->divisao(25000))->toBe(['ibto' => 25000, 'instrutor' => 0]);
    // 0%: tudo do instrutor.
    expect(cursoComDivisao(0)->divisao(25000))->toBe(['ibto' => 0, 'instrutor' => 25000]);
    // R$ 9,99 com 20% = 199,8 centavos: o IBTO fica com 199 e o instrutor com 800 (soma 999).
    expect(cursoComDivisao(20)->divisao(999))->toBe(['ibto' => 199, 'instrutor' => 800]);
});

test('confirmar o pagamento lança a conta a pagar do instrutor (80%)', function () {
    $curso = cursoComDivisao(20);
    $matricula = app(Matriculas::class)->matricular($curso, User::factory()->create());

    app(Matriculas::class)->confirmarPagamento($matricula, '2026-10-14', $this->pix);

    // A receita é o valor inteiro, já paga (como antes).
    expect(FinLancamento::where('origem', 'matricula')->value('valor_centavos'))->toBe(25000);
    // A conta a pagar: despesa "Instrutores", PENDENTE, com 80%.
    $repasse = FinLancamento::where('origem', 'repasse')->where('origem_id', $matricula->id)->firstOrFail();
    expect($repasse->tipo)->toBe('despesa')
        ->and($repasse->situacao)->toBe('pendente')
        ->and($repasse->valor_centavos)->toBe(20000)
        ->and($repasse->categoria->nome)->toBe('Instrutores')
        ->and($repasse->descricao)->toContain('Trinca');
});

test('curso com 100% do IBTO não gera conta a pagar', function () {
    $matricula = app(Matriculas::class)->matricular(cursoComDivisao(100), User::factory()->create());

    app(Matriculas::class)->confirmarPagamento($matricula, '2026-10-14', $this->pix);

    expect(FinLancamento::where('origem', 'repasse')->count())->toBe(0);
});

test('só o IBTO escolhe o percentual; o instrutor fica no padrão de 20%', function () {
    $dados = ['titulo' => 'Formação de instrutor', 'modalidade' => 'online', 'acesso' => 'restrito', 'valor' => '100,00', 'percentual_ibto' => '100'];

    $ibto = User::factory()->create();
    $ibto->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($ibto)->post(route('comando-geral.cursos.store'), $dados)->assertSessionHasNoErrors();
    expect(Curso::where('titulo', 'Formação de instrutor')->value('percentual_ibto'))->toBe(100);

    // Acima de 100 não existe: recusado.
    $this->post(route('comando-geral.cursos.store'), [...$dados, 'titulo' => 'Errado', 'percentual_ibto' => '150'])
        ->assertSessionHasErrors('percentual_ibto');

    // O instrutor manda 0 (tudo para ele) mesmo sem o campo na tela: fica 20.
    $instrutor = User::factory()->create();
    $instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->actingAs($instrutor)->post(route('comando-geral.cursos.store'), [...$dados, 'titulo' => 'Do instrutor', 'percentual_ibto' => '0'])
        ->assertSessionHasNoErrors();
    expect(Curso::where('titulo', 'Do instrutor')->value('percentual_ibto'))->toBe(Curso::PERCENTUAL_IBTO_PADRAO);
});
