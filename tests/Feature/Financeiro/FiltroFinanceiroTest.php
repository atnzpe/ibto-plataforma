<?php

/*
 * Testes dos FILTROS ROBUSTOS DO FINANCEIRO (18/10/2026): por papel e pessoa
 * (quanto pagar a cada instrutor e fotógrafo), por movimento, por código, por
 * valor; os cartões respondem ao filtro; a visão sintética soma por pessoa; o
 * CSV e a impressão saem e vão para a auditoria. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->instrutor = User::factory()->create(['name' => 'Instrutor Marcelo']);
    $this->instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->aluno = User::factory()->create(['name' => 'Aluno Pagante', 'email' => 'pagante@exemplo.test']);

    // Curso de R$ 250,00 com 20% do IBTO: receita de 250 (aluno) e repasse de 200 (instrutor).
    $this->curso = new Curso(['titulo' => 'Combate com Faca', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 25000, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['situacao' => 'publicado', 'instrutor_id' => $this->instrutor->id, 'percentual_ibto' => 20])->save();
    $matriculas = app(Matriculas::class);
    $this->matricula = $matriculas->confirmarPagamento($matriculas->matricular($this->curso, $this->aluno), today()->toDateString(), (int) FinFormaPagamento::where('nome', 'PIX')->value('id'));
    $this->periodo = ['de' => today()->startOfMonth()->toDateString(), 'ate' => today()->endOfMonth()->toDateString()];
});

test('os cartões somam o filtro: recebido, a pagar e saldo', function () {
    $this->actingAs($this->admin)->get(route('financeiro.lancamentos.index', $this->periodo))
        ->assertInertia(fn (Assert $tela) => $tela
            ->where('cartoes.recebido', 25000)
            ->where('cartoes.a_pagar', 20000)
            ->where('cartoes.pago', 0)
            ->where('cartoes.saldo', 25000));
});

test('filtro por papel: só os repasses do instrutor (quanto pagar a ele)', function () {
    $this->actingAs($this->admin)->get(route('financeiro.lancamentos.index', [...$this->periodo, 'papel' => 'instrutor']))
        ->assertInertia(fn (Assert $tela) => $tela
            ->has('lancamentos', 1)
            ->where('lancamentos.0.pessoa', 'Instrutor Marcelo')
            ->where('cartoes.a_pagar', 20000)
            ->where('cartoes.recebido', 0));
});

test('filtro por pessoa (e-mail), movimento, valor e código', function () {
    $tela = fn (array $filtros) => $this->actingAs($this->admin)->get(route('financeiro.lancamentos.index', [...$this->periodo, ...$filtros]));

    $tela(['pessoa' => 'pagante@'])->assertInertia(fn (Assert $t) => $t->has('lancamentos', 1)->where('lancamentos.0.valor_centavos', 25000));
    $tela(['movimento' => 'repasse'])->assertInertia(fn (Assert $t) => $t->has('lancamentos', 1)->where('lancamentos.0.tipo', 'despesa'));
    $tela(['valor_min' => '210,00'])->assertInertia(fn (Assert $t) => $t->has('lancamentos', 1));
    // Código: o nº da matrícula traz a receita e o repasse dela.
    $tela(['codigo' => (string) $this->matricula->id])->assertInertia(fn (Assert $t) => $t->has('lancamentos', 2));
    // O código da página /pagar também acha os dois.
    $tela(['codigo' => (string) $this->matricula->fresh()->codigo_pagamento])->assertInertia(fn (Assert $t) => $t->has('lancamentos', 2));
    // Filtro com valor inválido é ignorado (não quebra).
    $tela(['situacao' => 'xyz', 'equipe' => 'abc'])->assertOk()->assertInertia(fn (Assert $t) => $t->has('lancamentos', 2));
});

test('visão sintética: soma por pessoa', function () {
    $this->actingAs($this->admin)->get(route('financeiro.lancamentos.index', [...$this->periodo, 'modo' => 'sintetico', 'agrupar' => 'pessoa']))
        ->assertInertia(fn (Assert $tela) => $tela
            ->has('sintetico', 2)
            ->where('sintetico.0.grupo', "Aluno Pagante (nº {$this->aluno->id})")
            ->where('sintetico.0.recebido', 25000)
            ->where('sintetico.1.a_pagar', 20000));
});

test('CSV e impressão saem com o filtro e vão para a auditoria', function () {
    $resposta = $this->actingAs($this->admin)->get(route('financeiro.lancamentos.csv', [...$this->periodo, 'modo' => 'sintetico']));
    $resposta->assertOk();
    expect($resposta->streamedContent())->toContain('Recebido;"A receber"')->toContain('250,00');

    $this->actingAs($this->admin)->get(route('financeiro.lancamentos.imprimir', $this->periodo))
        ->assertInertia(fn (Assert $tela) => $tela->component('relatorios/imprimir')->has('linhas', 2));
    expect(Auditoria::where('acao', 'RELATORIO_EXPORTADO')->count())->toBe(2);

    // Sem "financeiro.ver": 403.
    $this->actingAs($this->aluno)->get(route('financeiro.lancamentos.csv'))->assertForbidden();
});
