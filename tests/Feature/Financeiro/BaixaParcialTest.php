<?php

/*
 * Testes da BAIXA PARCIAL (#59, opção B do Gleyson em 10/10/2026): entra só
 * parte do valor → o lançamento fica pago com o que entrou e nasce o SALDO,
 * pendente e ligado a ele. A soma sempre bate. Dados INVENTADOS.
 */

use App\Exceptions\RegraFinanceira;
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
    $this->pix = FinFormaPagamento::where('nome', 'PIX')->value('id');
    // "Material, R$ 900,00", pendente.
    $this->material = $this->caixa->lancar([
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Material de curso')->value('id'),
        'descricao' => 'Material',
        'valor_centavos' => 90000,
        'vencimento' => '2026-10-20',
    ]);
});

test('pagar R$ 300 de R$ 900: o original fica pago com 300 e nasce o saldo de 600, pendente', function () {
    $this->caixa->pagar($this->material, '2026-10-10', $this->pix, 30000);

    $original = $this->material->fresh();
    expect($original->situacao)->toBe('pago');
    expect($original->valor_centavos)->toBe(30000);

    $saldo = FinLancamento::where('saldo_de_id', $original->id)->first();
    expect($saldo->situacao)->toBe('pendente');
    expect($saldo->valor_centavos)->toBe(60000);
    expect($saldo->descricao)->toBe('Saldo de: Material');
    expect($saldo->vencimento->toDateString())->toBe('2026-10-20');
    // A soma bate: 300 + 600 = 900.
    expect($original->valor_centavos + $saldo->valor_centavos)->toBe(90000);
});

test('o saldo também aceita baixa parcial (de novo nasce outro saldo)', function () {
    $this->caixa->pagar($this->material, '2026-10-10', $this->pix, 30000);
    $saldo = FinLancamento::where('saldo_de_id', $this->material->id)->first();

    $this->caixa->pagar($saldo, '2026-10-10', $this->pix, 50000);

    $ultimo = FinLancamento::where('saldo_de_id', $saldo->id)->first();
    expect($ultimo->valor_centavos)->toBe(10000);
    // (int): no MySQL a soma volta como texto ("80000"); no SQLite, como número.
    expect((int) FinLancamento::where('situacao', 'pago')->sum('valor_centavos'))->toBe(80000);
});

test('pagar o valor todo não gera saldo', function () {
    $this->caixa->pagar($this->material, '2026-10-10', $this->pix, 90000);

    expect(FinLancamento::whereNotNull('saldo_de_id')->exists())->toBeFalse();
});

test('valor pago zero ou acima do total é recusado (VALOR_PAGO_INVALIDO)', function () {
    expect(fn () => $this->caixa->pagar($this->material, '2026-10-10', $this->pix, 0))->toThrow(RegraFinanceira::class);
    expect(fn () => $this->caixa->pagar($this->material, '2026-10-10', $this->pix, 90001))->toThrow(RegraFinanceira::class);
    expect($this->material->fresh()->situacao)->toBe('pendente');
});

test('pela tela: "300,00" registra a baixa parcial com a mensagem certa', function () {
    $this->from(route('financeiro.lancamentos.index'))
        ->post(route('financeiro.lancamentos.pagar', $this->material), [
            'pago_em' => today()->toDateString(),
            'forma_pagamento_id' => $this->pix,
            'valor_pago' => '300,00',
        ])
        ->assertSessionHas('sucesso', 'Baixa parcial registrada; o saldo ficou em aberto.');

    expect(FinLancamento::where('saldo_de_id', $this->material->id)->value('valor_centavos'))->toBe(60000);
});

test('pela tela: valor acima do total volta com o código, sem erro 500', function () {
    $this->post(route('financeiro.lancamentos.pagar', $this->material), [
        'pago_em' => today()->toDateString(),
        'forma_pagamento_id' => $this->pix,
        'valor_pago' => '1.000,00',
    ])->assertSessionHasErrors('acao');

    expect(session('errors')->first('acao'))->toContain('VALOR_PAGO_INVALIDO');
});

test('o histórico do original mostra a baixa parcial e o número do saldo', function () {
    $this->caixa->pagar($this->material, '2026-10-10', $this->pix, 30000);
    $saldoId = FinLancamento::where('saldo_de_id', $this->material->id)->value('id');

    $this->get(route('financeiro.historico', ['lancamento' => $this->material->id]))
        ->assertInertia(fn ($tela) => $tela
            ->where('linhas.0.acao', 'LANCAMENTO_PAGO_PARCIAL')
            ->where('linhas.0.saldo_centavos', 60000)
            ->where('linhas.0.lancamentos', [$this->material->id, $saldoId]));
});
