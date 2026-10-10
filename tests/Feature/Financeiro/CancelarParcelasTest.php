<?php

/*
 * Testes do CANCELAMENTO de título parcelado (#59, pedido do Gleyson em
 * 10/10/2026): cancelar só a parcela ou todas as em aberto do título; as
 * pagas nunca são canceladas. Dados INVENTADOS.
 */

use App\Exceptions\RegraFinanceira;
use App\Models\Auditoria;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\LivroCaixa;

beforeEach(function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin);

    $this->caixa = app(LivroCaixa::class);
    // "Material" em 4x; a 1ª já paga.
    $this->parcelas = $this->caixa->lancarParcelado([
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Material de curso')->value('id'),
        'descricao' => 'Material',
        'valor_centavos' => 40000,
        'vencimento' => '2026-10-10',
    ], 4);
    $this->caixa->pagar($this->parcelas[0], '2026-10-10', FinFormaPagamento::value('id'));
    // Um outro título qualquer, que não pode ser afetado.
    $this->outro = $this->caixa->lancarParcelado([
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Material de curso')->value('id'),
        'descricao' => 'Outro',
        'valor_centavos' => 2000,
        'vencimento' => '2026-10-10',
    ], 2);
});

test('cancelar todas: só as parcelas EM ABERTO do mesmo título', function () {
    $quantas = $this->caixa->cancelarParcelas($this->parcelas[2], 'Compra desfeita');

    expect($quantas)->toBe(3);
    // A paga continua paga.
    expect($this->parcelas[0]->fresh()->situacao)->toBe('pago');
    expect($this->parcelas[1]->fresh()->situacao)->toBe('cancelado');
    expect($this->parcelas[3]->fresh()->situacao)->toBe('cancelado');
    // O outro título não foi tocado.
    expect(FinLancamento::where('descricao', 'like', 'Outro%')->where('situacao', 'pendente')->count())->toBe(2);
    expect(Auditoria::where('acao', 'PARCELAS_CANCELADAS')->first()->detalhes['parcelas'])->toBe(3);
});

test('pela tela: escopo "esta" cancela só a parcela; "todas" cancela o resto do título', function () {
    $this->post(route('financeiro.lancamentos.cancelar', $this->parcelas[1]), ['motivo' => 'Só esta', 'escopo' => 'esta'])
        ->assertSessionHas('sucesso', 'Lançamento cancelado.');
    expect(FinLancamento::where('grupo_parcelas', $this->parcelas[0]->grupo_parcelas)->where('situacao', 'pendente')->count())->toBe(2);

    $this->post(route('financeiro.lancamentos.cancelar', $this->parcelas[2]), ['motivo' => 'O resto', 'escopo' => 'todas'])
        ->assertSessionHas('sucesso', '2 parcela(s) em aberto do título cancelada(s).');
    expect(FinLancamento::where('grupo_parcelas', $this->parcelas[0]->grupo_parcelas)->where('situacao', 'pendente')->count())->toBe(0);
});

test('lançamento à vista não aceita "todas as parcelas"', function () {
    $avista = $this->caixa->lancar([
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Taxas bancárias')->value('id'),
        'descricao' => 'Tarifa',
        'valor_centavos' => 100,
        'vencimento' => '2026-10-10',
    ]);

    expect(fn () => $this->caixa->cancelarParcelas($avista, 'Não é parcela'))->toThrow(RegraFinanceira::class);
});

test('a lista informa quantas parcelas do título estão em aberto', function () {
    $this->get(route('financeiro.lancamentos.index', ['mes' => '2026-11']))
        ->assertInertia(fn ($tela) => $tela
            ->where('lancamentos.0.parcela_numero', 2)
            ->where('lancamentos.0.parcela_total', 4)
            ->where('lancamentos.0.parcelas_em_aberto', 3));
});
