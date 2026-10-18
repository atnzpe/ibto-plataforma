<?php

/*
 * Testes da PÁGINA DE PAGAMENTO ÚNICA /pagar/<código> (17/10/2026): toda cobrança
 * nova ganha um código ULID único; o dono vê o checkout com o Pix; outra pessoa
 * recebe 404 (sem saber se existe); código inválido nem consulta; depois de
 * confirmado, a página mostra "pago" e sem Pix; o Comando Geral também abre.
 * Dados INVENTADOS.
 */

use App\Actions\AulasRegulares\Assinaturas;
use App\Models\FinFormaPagamento;
use App\Models\PagamentoConfig;
use App\Models\Papel;
use App\Models\Programa;
use App\Models\User;

beforeEach(function () {
    PagamentoConfig::atual()->update(['pix_chave' => 'financeiro@ibto.test', 'favorecido' => 'IBTO Teste']);
    $programa = Programa::create(['titulo' => 'Krav Maga online', 'modalidade' => 'online', 'valor_mensal_centavos' => 12000, 'percentual_ibto' => 20, 'dia_vencimento' => 10, 'ativo' => true, 'so_filiados' => false]);
    $this->aluno = User::factory()->create();
    $this->mensalidade = app(Assinaturas::class)->assinar($programa, $this->aluno)->pagamentos()->sole();
});

test('a cobrança nova ganha um código ULID único e o dono vê o checkout com o Pix', function () {
    expect($this->mensalidade->codigo_pagamento)->toMatch('/^[0-9a-z]{26}$/');

    $this->actingAs($this->aluno)->get($this->mensalidade->linkDePagamento())
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('pagar')
            ->where('cobranca.situacao', 'aguardando')
            ->where('cobranca.valor_centavos', 12000)
            ->where('cobranca.pix.txid', 'IBTOAUL'.$this->mensalidade->id)
            ->missing('cobranca.dono_id'));
});

test('outra pessoa e código inválido recebem 404; o Comando Geral abre', function () {
    $this->actingAs(User::factory()->create())->get($this->mensalidade->linkDePagamento())->assertNotFound();
    $this->actingAs($this->aluno)->get('/pagar/nao-e-um-codigo')->assertNotFound();

    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin)->get($this->mensalidade->linkDePagamento())->assertOk();
});

test('depois de confirmado: "pago" e sem Pix', function () {
    app(Assinaturas::class)->confirmarPagamento($this->mensalidade, today()->toDateString(), (int) FinFormaPagamento::where('nome', 'PIX')->value('id'));

    $this->actingAs($this->aluno)->get($this->mensalidade->linkDePagamento())
        ->assertInertia(fn ($tela) => $tela->where('cobranca.situacao', 'pago')->where('cobranca.pix', null));
});
