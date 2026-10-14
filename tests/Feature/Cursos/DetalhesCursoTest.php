<?php

/*
 * Testes dos DETALHES DO CURSO no Comando Geral (tela 3 do desenho aprovado
 * em 10/10/2026): o resumo por passo, a lista de inscritos SÓ com o registro
 * (LGPD), quem pode ver (Comando Geral e o dono do curso; aluno não) e o
 * botão "Inscritos" na lista de cursos. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Models\Curso;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    $this->curso = Curso::create(['titulo' => 'Tiro Defensivo', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000, 'vagas' => 10]);
    // A situação não entra pelo create (só pelo fluxo da chancela): forceFill.
    $this->curso->forceFill(['situacao' => 'publicado'])->save();

    $matriculas = app(Matriculas::class);
    $matriculas->matricular($this->curso, User::factory()->create(['name' => 'Aluna Secreta']));
    $paga = $matriculas->matricular($this->curso, User::factory()->create());
    $matriculas->confirmarPagamento($paga, today()->toDateString(), (int) FinFormaPagamento::where('nome', 'PIX')->value('id'));
    $matriculas->cancelar($matriculas->matricular($this->curso, User::factory()->create()), 'Desistência');
});

test('resumo por passo e inscritos só com o registro (sem nome)', function () {
    $this->actingAs($this->admin)->get(route('comando-geral.cursos.inscritos', $this->curso))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('cursos/detalhes')
            ->where('resumo.vagas', 10)
            ->where('resumo.ocupadas', 2)
            ->where('resumo.aguardando', 1)
            ->where('resumo.confirmadas', 1)
            ->where('resumo.canceladas', 1)
            ->has('inscritos', 3)
            ->missing('inscritos.0.nome')
            ->where('pode_confirmar', true))
        ->assertDontSee('Aluna Secreta');
});

test('o dono do curso (instrutor) vê; outro instrutor e o aluno não (403)', function () {
    $dono = User::factory()->create();
    $dono->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->curso->forceFill(['responsavel_id' => $dono->id])->save();

    $this->actingAs($dono)->get(route('comando-geral.cursos.inscritos', $this->curso))
        ->assertOk()
        // O instrutor não abre a ficha (não confirma pagamentos).
        ->assertInertia(fn ($tela) => $tela->where('pode_confirmar', false));

    $outro = User::factory()->create();
    $outro->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->actingAs($outro)->get(route('comando-geral.cursos.inscritos', $this->curso))->assertForbidden();
});

test('a lista de cursos manda o botão "Inscritos" para quem pode', function () {
    $this->actingAs($this->admin)->get(route('comando-geral.cursos.index'))
        ->assertInertia(fn ($tela) => $tela->where('cursos.0.pode_ver_inscritos', true));
});
