<?php

/*
 * Testes do PAINEL FINANCEIRO DAS AULAS REGULARES (17/10/2026): previsto,
 * recebido, a receber e em atraso batem em centavos; a divisão 20/80 e o
 * repasse (já pago × falta repassar) saem certos; os filtros de aluno e
 * situação funcionam; o instrutor vê só os programas dele; sem a permissão,
 * 403; o CSV sai com ";" e vai para a auditoria. Dados INVENTADOS.
 */

use App\Actions\AulasRegulares\Assinaturas;
use App\Models\Auditoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\Programa;
use App\Models\User;

/**
 * Uma conta com o papel pedido. Ex.: contaPainel('administrador').
 */
function contaPainel(string $papel): User
{
    $usuario = User::factory()->create();
    $usuario->papeis()->attach(Papel::where('slug', $papel)->value('id'));

    return $usuario;
}

beforeEach(function () {
    $this->admin = contaPainel('administrador');
    $this->instrutor = contaPainel('instrutor');
    // Krav Maga: R$ 100,00 por mês, 20% do IBTO, do instrutor acima.
    $this->programa = Programa::create(['titulo' => 'Krav Maga', 'modalidade' => 'presencial', 'valor_mensal_centavos' => 10000, 'percentual_ibto' => 20, 'dia_vencimento' => 10, 'ativo' => true, 'so_filiados' => false, 'instrutor_id' => $this->instrutor->id, 'instrutor_nome' => 'Instrutor Teste']);

    $acoes = app(Assinaturas::class);
    $pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
    // 1) Pago (e o repasse já pago ao instrutor).
    $pago = $acoes->assinar($this->programa, User::factory()->create(['name' => 'Aluno Pagou']));
    $acoes->confirmarPagamento($pago->pagamentos()->sole(), today()->toDateString(), $pix);
    FinLancamento::where('origem', 'repasse_assinatura')->update(['situacao' => 'pago']);
    // 2) A receber: vence hoje (ainda no prazo).
    $acoes->assinar($this->programa, User::factory()->create(['name' => 'Aluno No Prazo']));
    // 3) Em atraso: venceu ontem.
    $atrasado = $acoes->assinar($this->programa, User::factory()->create(['name' => 'Aluno Atrasado']));
    $atrasado->pagamentos()->update(['vence_em' => today()->subDay()->toDateString()]);
});

test('o painel soma previsto, recebido, a receber, em atraso e a divisão IBTO/instrutor', function () {
    $this->actingAs($this->admin)
        ->get(route('relatorios.aulas-regulares', ['de' => today()->subMonth()->toDateString(), 'ate' => today()->addMonth()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('relatorios/aulas-regulares')
            ->where('totais.alunos', 3)
            ->where('totais.previsto', 30000)
            ->where('totais.recebido', 10000)
            ->where('totais.a_receber', 10000)
            ->where('totais.atrasado', 10000)
            ->where('totais.percentual_recebido', 33)
            ->where('totais.ibto_recebido', 2000)
            ->where('totais.instrutor_recebido', 8000)
            ->where('totais.repassado', 8000)
            ->where('totais.a_repassar', 0)
            ->where('por_programa.0.nome', 'Krav Maga')
            ->where('por_instrutor.0.nome', 'Instrutor Teste')
            ->has('mensalidades', 3));
});

test('os filtros de aluno (pagador) e de situação funcionam', function () {
    $periodo = ['de' => today()->subMonth()->toDateString(), 'ate' => today()->addMonth()->toDateString()];

    $this->actingAs($this->admin)->get(route('relatorios.aulas-regulares', [...$periodo, 'situacao' => 'atrasado']))
        ->assertInertia(fn ($tela) => $tela->has('mensalidades', 1)->where('mensalidades.0.aluno', 'Aluno Atrasado'));
    $this->actingAs($this->admin)->get(route('relatorios.aulas-regulares', [...$periodo, 'pagador' => 'pagou']))
        ->assertInertia(fn ($tela) => $tela->has('mensalidades', 1)->where('totais.recebido', 10000)->has('alunos', 1));
});

test('o instrutor vê só os programas dele; sem a permissão, 403', function () {
    $outro = contaPainel('instrutor');
    // O outro instrutor não tem programa: painel vazio (nada de dinheiro dos outros).
    $this->actingAs($outro)->get(route('relatorios.aulas-regulares'))
        ->assertOk()->assertInertia(fn ($tela) => $tela->where('totais.previsto', 0)->where('ve_tudo', false)->has('programas', 0));
    // O instrutor do Krav Maga vê o programa dele.
    $this->actingAs($this->instrutor)->get(route('relatorios.aulas-regulares'))
        ->assertInertia(fn ($tela) => $tela->has('programas', 1)->where('totais.alunos', 3));
    // Aluno não tem a permissão.
    $this->actingAs(contaPainel('aluno'))->get(route('relatorios.aulas-regulares'))->assertForbidden();
});

test('o CSV sai com ";" e a exportação vai para a auditoria', function () {
    $resposta = $this->actingAs($this->admin)->get(route('relatorios.aulas-regulares.csv', ['de' => today()->subMonth()->toDateString(), 'ate' => today()->addMonth()->toDateString()]));

    $resposta->assertOk();
    expect($resposta->streamedContent())->toContain('"Aluno (pagador)";Programa')->toContain('100,00;Pago;20,00;80,00;Sim');
    expect(Auditoria::where('acao', 'RELATORIO_EXPORTADO')->exists())->toBeTrue();
});

test('o índice de relatórios mostra o painel para quem tem a permissão', function () {
    $this->actingAs($this->instrutor)->get(route('relatorios.index'))
        ->assertInertia(fn ($tela) => $tela->where('relatorios', fn ($itens) => collect($itens)->contains('href', '/relatorios/aulas-regulares')));
});
