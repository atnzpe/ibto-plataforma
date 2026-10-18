<?php

/*
 * Testes do MÓDULO DE RELATÓRIOS (#73): cada relatório pede a sua permissão
 * (403 para quem não tem); o índice mostra só os liberados; os totais do
 * relatório de cursos batem com o livro-caixa (centavos); o CSV sai com o
 * separador ";"; imprimir e CSV vão para a auditoria; o instrutor vê só os
 * cursos dele na frequência; nenhum CPF aparece. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Actions\Filiados\CriarFiliado;
use App\Enums\SituacaoCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Equipe;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use App\Relatorios\AuditoriaRelatorio;
use Illuminate\Support\Facades\Notification;

/**
 * Uma conta com o papel pedido.
 */
function contaDoRelatorio(string $papel): User
{
    $usuario = User::factory()->create();
    $usuario->papeis()->attach(Papel::where('slug', $papel)->value('id'));

    return $usuario;
}

beforeEach(function () {
    Notification::fake();
    $this->admin = contaDoRelatorio('administrador');
    $this->tecnologia = contaDoRelatorio('tecnologia');

    // Um curso pago com 2 matrículas confirmadas (R$ 250,00 cada, 20% do IBTO).
    $this->curso = new Curso(['titulo' => 'Combate com Faca', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 25000, 'data_inicio' => today()->toDateString(), 'local' => 'Recife', 'instrutor_nome' => 'Marcelo']);
    $this->curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'percentual_ibto' => 20])->save();
    $matriculas = app(Matriculas::class);
    $pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
    foreach (range(1, 2) as $vez) {
        $matriculas->confirmarPagamento($matriculas->matricular($this->curso, User::factory()->create()), today()->toDateString(), $pix);
    }
});

test('cursos: receita R$ 500,00, repasse R$ 400,00 e parte do IBTO R$ 100,00 (conferido com o livro-caixa)', function () {
    $this->actingAs($this->admin)->get(route('relatorios.show', 'cursos'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela
            ->component('relatorios/ver')
            ->where('linhas.0.curso', 'Combate com Faca')
            ->where('linhas.0.confirmados', 2)
            ->where('linhas.0.receita', 50000)
            ->where('linhas.0.repasse', 40000)
            ->where('linhas.0.parte_ibto', 10000)
            ->where('totais.receita', 50000));
});

test('cada relatório pede a sua permissão; o índice mostra só os liberados', function () {
    // O Administrador não tem o de auditoria (só a Tecnologia).
    $this->actingAs($this->admin)->get(route('relatorios.show', 'auditoria'))->assertForbidden();
    $this->actingAs($this->tecnologia)->get(route('relatorios.show', 'auditoria'))->assertOk();
    $this->actingAs($this->admin)->get(route('relatorios.index'))
        // 4 do módulo + o painel das aulas regulares (17/10/2026).
        ->assertInertia(fn ($tela) => $tela->has('relatorios', 5));

    // Aluno sem nenhum relatório: 403 no módulo inteiro. Relatório que não existe: 404.
    $this->actingAs(User::factory()->create())->get(route('relatorios.index'))->assertForbidden();
    $this->actingAs($this->admin)->get('/relatorios/inexistente')->assertNotFound();
});

test('CSV com ";" e a exportação vai para a auditoria (CSV e impressão)', function () {
    $resposta = $this->actingAs($this->admin)->get(route('relatorios.csv', 'cursos'));
    $resposta->assertOk();
    expect($resposta->streamedContent())->toContain('Curso;Início;Instrutor')->toContain('500,00');

    $this->actingAs($this->admin)->get(route('relatorios.imprimir', 'cursos'))->assertOk()->assertInertia(fn ($tela) => $tela->component('relatorios/imprimir')->has('gerado_por'));

    expect(Auditoria::where('acao', 'RELATORIO_EXPORTADO')->count())->toBe(2);
});

test('frequência: o instrutor vê só os cursos dele', function () {
    $instrutor = contaDoRelatorio('instrutor');
    $this->actingAs($instrutor)->get(route('relatorios.show', 'frequencia'))->assertInertia(fn ($tela) => $tela->has('linhas', 0));

    $this->curso->forceFill(['responsavel_id' => $instrutor->id])->save();
    $this->actingAs($instrutor)->get(route('relatorios.show', 'frequencia'))->assertInertia(fn ($tela) => $tela->has('linhas', 1)->where('linhas.0.confirmados', 2));
});

test('filiados: só contagens, nenhum CPF; o admin de equipe vê só a equipe dele', function () {
    $equipe = Equipe::factory()->create(['nome' => 'Equipe Recife Teste']);
    $outra = Equipe::factory()->create(['nome' => 'Equipe Outra Teste']);
    $admEquipe = app(CriarFiliado::class)->executar(['nome' => 'Adm Equipe', 'email' => 'admeq@ibto.test', 'cpf' => '529.982.247-25', 'nascimento' => '1985-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => $equipe->id])->usuario;
    // Conta criada pelo convite: confirma o e-mail (os relatórios pedem e-mail confirmado).
    $admEquipe->markEmailAsVerified();
    $admEquipe->papeis()->attach(Papel::where('slug', 'admin_equipe')->value('id'));
    app(CriarFiliado::class)->executar(['nome' => 'Outro', 'email' => 'outro@ibto.test', 'cpf' => '111.444.777-35', 'nascimento' => '1985-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => $outra->id]);

    $resposta = $this->actingAs($admEquipe->fresh())->get(route('relatorios.show', 'filiados'));
    $resposta->assertInertia(fn ($tela) => $tela->has('linhas', 1)->where('linhas.0.equipe', 'Equipe Recife Teste'));
    expect($resposta->getContent())->not->toContain('529.982.247-25')->not->toContain('52998224725');
});

test('o nome na auditoria sai abreviado', function () {
    expect(AuditoriaRelatorio::abreviado('Gleyson Atanazio da Silva'))->toBe('Gleyson A. S.');
});

test('LGPD: a conta logada vai para a tela sem o filiado (nem CPF, nem nascimento, nem telefone)', function () {
    $equipe = Equipe::factory()->create();
    $usuario = app(CriarFiliado::class)->executar(['nome' => 'Fulano', 'email' => 'fulano@ibto.test', 'cpf' => '529.982.247-25', 'nascimento' => '1985-01-01', 'telefone' => '81999990000', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => $equipe->id])->usuario;
    $usuario->markEmailAsVerified();
    // Carrega o filiado na conta antes da tela (como as regras de permissão fazem).
    $usuario->load('filiado');

    // A tela de perfil abre para qualquer conta (a inicial pode redirecionar e o teste passaria sem conferir nada).
    $resposta = $this->actingAs($usuario)->get(route('profile.edit'));
    $resposta->assertOk()->assertInertia(fn ($tela) => $tela->missing('auth.user.filiado'));
    $pagina = $resposta->getContent();
    expect($pagina)->not->toContain('52998224725')->not->toContain('81999990000')->not->toContain('1985-01-01');
});

test('filtros robustos: por dimensão, busca livre e modo sintético (com CSV no mesmo filtro)', function () {
    // Um segundo curso, online, para ter o que filtrar.
    $online = new Curso(['titulo' => 'Inteligência Online', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000, 'data_inicio' => today()->toDateString(), 'instrutor_nome' => 'Ana']);
    $online->forceFill(['situacao' => SituacaoCurso::Publicado, 'percentual_ibto' => 20])->save();

    $tela = fn (array $filtros) => $this->actingAs($this->admin)->get(route('relatorios.show', ['chave' => 'cursos', ...$filtros]));

    // Os campos de filtro trazem a modalidade (dimensão) com os dois valores.
    $tela([])->assertInertia(fn ($t) => $t->where('campos_filtro', fn ($campos) => collect($campos)->firstWhere('chave', 'modalidade')['valores'] === ['Online', 'Presencial']));
    // Filtro por dimensão: só o online.
    $tela(['f_modalidade' => 'Online'])->assertInertia(fn ($t) => $t->has('linhas', 1)->where('linhas.0.curso', 'Inteligência Online'));
    // Busca livre (sem diferenciar maiúscula).
    $tela(['busca' => 'faca'])->assertInertia(fn ($t) => $t->has('linhas', 1)->where('linhas.0.curso', 'Combate com Faca'));
    // Valor que não existe é ignorado (não quebra, não filtra).
    $tela(['f_modalidade' => 'Marte'])->assertOk()->assertInertia(fn ($t) => $t->has('linhas', 2));
    // Sintético por modalidade: uma linha por modalidade, com a receita somada.
    $tela(['modo' => 'sintetico', 'agrupar' => 'modalidade'])->assertInertia(fn ($t) => $t
        ->has('linhas', 2)
        ->where('colunas.0.rotulo', 'Modalidade')
        ->where('linhas.1.grupo', 'Presencial')
        ->where('linhas.1.receita', 50000));

    // O CSV respeita o filtro.
    $csv = $this->actingAs($this->admin)->get(route('relatorios.csv', ['chave' => 'cursos', 'f_modalidade' => 'Online']))->streamedContent();
    expect($csv)->toContain('Inteligência Online')->not->toContain('Combate com Faca');
});
