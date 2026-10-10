<?php

/*
 * Testes do painel de MATRÍCULAS no Comando Geral (#41, bloco C5c): só quem
 * tem "pagamentos.confirmar" entra; filtros (situação e período De/Até);
 * confirmar com dia e forma (não no futuro; não duas vezes); cancelar com
 * motivo e o lembrete do estorno quando já havia pagamento.
 * Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoMatricula;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');

    $this->curso = Curso::create(['titulo' => 'Tiro Defensivo', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 25000]);
    // A situação não entra pelo create (só pelo fluxo da chancela): forceFill.
    $this->curso->forceFill(['situacao' => 'publicado'])->save();
    $this->matricula = app(Matriculas::class)->matricular($this->curso, User::factory()->create(['name' => 'Aluna Teste']));
});

test('só quem confirma pagamentos abre o painel (403 para os outros)', function () {
    $this->actingAs(User::factory()->create())->get(route('comando-geral.matriculas.index'))->assertForbidden();

    $this->actingAs($this->admin)->get(route('comando-geral.matriculas.index'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('cursos/matriculas')
            ->where('aguardando', 1)
            ->where('matriculas.data.0.aluno', 'Aluna Teste'));
});

test('filtros: situação e período De/Até', function () {
    $this->actingAs($this->admin)->get(route('comando-geral.matriculas.index', ['situacao' => 'confirmada']))
        ->assertInertia(fn ($tela) => $tela->has('matriculas.data', 0));

    $this->actingAs($this->admin)->get(route('comando-geral.matriculas.index', ['de' => today()->toDateString(), 'ate' => today()->toDateString()]))
        ->assertInertia(fn ($tela) => $tela->has('matriculas.data', 1));

    $this->actingAs($this->admin)->get(route('comando-geral.matriculas.index', ['de' => '2020-01-01', 'ate' => '2020-01-31']))
        ->assertInertia(fn ($tela) => $tela->has('matriculas.data', 0));
});

test('confirmar: dia no futuro é recusado; depois confirma uma vez só', function () {
    $this->actingAs($this->admin)
        ->post(route('comando-geral.matriculas.confirmar', $this->matricula), ['pago_em' => today()->addDay()->toDateString(), 'forma_pagamento_id' => $this->pix])
        ->assertSessionHasErrors('pago_em');

    $this->actingAs($this->admin)
        ->post(route('comando-geral.matriculas.confirmar', $this->matricula), ['pago_em' => today()->toDateString(), 'forma_pagamento_id' => $this->pix])
        ->assertSessionHas('sucesso');
    expect($this->matricula->fresh()->situacao)->toBe(SituacaoMatricula::Confirmada);
    expect($this->matricula->fresh()->confirmada_por)->toBe($this->admin->id);

    // De novo: a regra recusa com o código.
    $this->actingAs($this->admin)
        ->post(route('comando-geral.matriculas.confirmar', $this->matricula), ['pago_em' => today()->toDateString(), 'forma_pagamento_id' => $this->pix])
        ->assertSessionHasErrors(['matricula']);
});

test('cancelar exige motivo; matrícula paga lembra do estorno no financeiro', function () {
    $this->actingAs($this->admin)
        ->post(route('comando-geral.matriculas.cancelar', $this->matricula), ['motivo' => ''])
        ->assertSessionHasErrors('motivo');

    app(Matriculas::class)->confirmarPagamento($this->matricula, today()->toDateString(), $this->pix);

    $this->actingAs($this->admin)
        ->post(route('comando-geral.matriculas.cancelar', $this->matricula), ['motivo' => 'Curso adiado'])
        ->assertSessionHas('sucesso', fn (string $mensagem) => str_contains($mensagem, 'faça o estorno no Financeiro'));

    expect(CursoMatricula::count())->toBe(1);
    expect($this->matricula->fresh()->situacao)->toBe(SituacaoMatricula::Cancelada);
});
