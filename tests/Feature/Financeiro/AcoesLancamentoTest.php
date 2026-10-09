<?php

/*
 * Testes das AÇÕES da tela do livro-caixa (#59): dar baixa, cancelar e
 * estornar pelas rotas, com permissão, conferência dos campos e os erros de
 * regra voltando com código (sem erro 500). Dados INVENTADOS.
 */

use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\Permissao;
use App\Models\User;
use App\Services\LivroCaixa;

beforeEach(function () {
    // Administrador: tem "financeiro.lancar".
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($this->admin);

    // Um lançamento pendente de despesa, para as ações.
    $this->lancamento = app(LivroCaixa::class)->lancar([
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Taxas bancárias')->value('id'),
        'descricao' => 'Tarifa de teste',
        'valor_centavos' => 1250,
        'vencimento' => today()->toDateString(),
    ]);
    $this->pix = FinFormaPagamento::where('nome', 'PIX')->value('id');
});

test('dar baixa pela tela: pendente vira pago', function () {
    $this->from(route('financeiro.lancamentos.index'))
        ->post(route('financeiro.lancamentos.pagar', $this->lancamento), [
            'pago_em' => today()->toDateString(),
            'forma_pagamento_id' => $this->pix,
        ])
        ->assertRedirect(route('financeiro.lancamentos.index'))
        ->assertSessionHas('sucesso', 'Baixa registrada.');

    expect($this->lancamento->fresh()->situacao)->toBe('pago');
});

test('baixa com data no futuro ou sem forma é recusada', function () {
    $this->post(route('financeiro.lancamentos.pagar', $this->lancamento), [
        'pago_em' => today()->addDay()->toDateString(),
    ])->assertSessionHasErrors(['pago_em', 'forma_pagamento_id']);

    expect($this->lancamento->fresh()->situacao)->toBe('pendente');
});

test('cancelar pela tela exige motivo', function () {
    $this->post(route('financeiro.lancamentos.cancelar', $this->lancamento), ['motivo' => 'ok'])
        ->assertSessionHasErrors('motivo');

    $this->post(route('financeiro.lancamentos.cancelar', $this->lancamento), ['motivo' => 'Lançado em duplicidade'])
        ->assertSessionHas('sucesso');
    expect($this->lancamento->fresh()->situacao)->toBe('cancelado');
});

test('estornar pela tela: cria o estorno; o segundo volta com JA_ESTORNADO', function () {
    app(LivroCaixa::class)->pagar($this->lancamento, today()->toDateString(), $this->pix);

    $this->post(route('financeiro.lancamentos.estornar', $this->lancamento), ['motivo' => 'Valor errado'])
        ->assertSessionHas('sucesso', 'Estorno registrado.');
    expect(FinLancamento::where('estorno_de_id', $this->lancamento->id)->exists())->toBeTrue();

    // De novo: a regra recusa e a tela recebe a mensagem com o código (não 500).
    $this->post(route('financeiro.lancamentos.estornar', $this->lancamento), ['motivo' => 'De novo, por engano'])
        ->assertSessionHasErrors('acao');
    expect(session('errors')->first('acao'))->toContain('JA_ESTORNADO');
});

test('a lista avisa quais lançamentos já foram estornados', function () {
    app(LivroCaixa::class)->pagar($this->lancamento, today()->toDateString(), $this->pix);
    app(LivroCaixa::class)->estornar($this->lancamento, 'Valor errado', today()->toDateString());

    $this->get(route('financeiro.lancamentos.index'))
        ->assertInertia(fn ($tela) => $tela
            ->where('lancamentos.0.estornado', true)
            ->where('lancamentos.1.estorno', true)
            ->has('formas', 4));
});

test('quem só vê o financeiro não consegue dar baixa (403)', function () {
    // Papel novo com só "financeiro.ver".
    $papel = Papel::create(['slug' => 'so_ver', 'nome' => 'Só vê', 'descricao' => 'Teste']);
    $papel->permissoes()->attach(Permissao::where('slug', 'financeiro.ver')->value('id'));
    $leitor = User::factory()->create();
    $leitor->papeis()->attach($papel->id);

    $this->actingAs($leitor)
        ->post(route('financeiro.lancamentos.pagar', $this->lancamento), [
            'pago_em' => today()->toDateString(),
            'forma_pagamento_id' => $this->pix,
        ])
        ->assertForbidden();
});
