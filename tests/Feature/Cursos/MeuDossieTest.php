<?php

/*
 * Testes do MEU DOSSIÊ (esboço de 08/10/2026: "todo filiado tem um dossiê"):
 * o resumo (concluídos, horas, presenças), os encontros com presente/falta e
 * o que foi trabalhado, os certificados (o cancelado aparece, mas não conta)
 * e que cada pessoa só vê o PRÓPRIO dossiê. Dados INVENTADOS.
 */

use App\Actions\Cursos\Certificados;
use App\Actions\Cursos\Matriculas;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // O e-mail de matrícula não sai de verdade no teste.
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    // Curso gratuito presencial de 16 h, com 4 encontros hoje.
    $this->curso = Curso::create(['titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'carga_horaria' => 16, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['situacao' => 'publicado'])->save();
    $modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->encontros = collect(range(1, 4))->map(fn ($n) => $modulo->aulas()->create(['titulo' => "Encontro {$n}", 'ordem' => $n, 'ativa' => true, 'data' => today()->toDateString()]));

    // O aluno, matriculado (gratuito = confirmada na hora).
    $this->aluno = User::factory()->create();
    $this->matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno);
});

/**
 * Faz a chamada de um encontro, com o aluno presente ou não e o que foi trabalhado.
 *
 * @param  int  $indice  Qual encontro (0 = o primeiro).
 * @param  bool  $presente  true = o aluno veio.
 * @param  string|null  $trabalhado  Ex.: 'Guarda e base.'
 */
function chamadaDoDossie($teste, int $indice, bool $presente, ?string $trabalhado = null): void
{
    $teste->actingAs($teste->admin)->put(
        route('comando-geral.cursos.chamada.update', ['curso' => $teste->curso->id, 'aula' => $teste->encontros[$indice]->id]),
        ['presentes' => $presente ? [$teste->matricula->id] : [], 'registro_encontro' => $trabalhado],
    );
}

test('encontros: presente, falta e sem chamada, com o que foi trabalhado', function () {
    chamadaDoDossie($this, 0, true, 'Guarda e base.');
    chamadaDoDossie($this, 1, false);

    $this->actingAs($this->aluno)->get(route('meu-dossie'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('meu-dossie')
            ->has('cursos', 1)
            ->where('cursos.0.encontros.0.presenca', 'presente')
            ->where('cursos.0.encontros.0.trabalhado', 'Guarda e base.')
            ->where('cursos.0.encontros.1.presenca', 'falta')
            ->where('cursos.0.encontros.2.presenca', 'sem_chamada')
            ->where('cursos.0.presencas', 1)
            ->where('cursos.0.encontros_com_chamada', 2)
            ->where('resumo.presencas', 1)
            // Sem certificado ainda: nada concluído.
            ->where('resumo.concluidos', 0));
});

test('certificado válido conta no resumo; cancelado aparece, mas não conta', function () {
    // Veio nos 4 encontros: apto (presença mínima de 75%).
    foreach (range(0, 3) as $indice) {
        chamadaDoDossie($this, $indice, true);
    }
    $certificado = app(Certificados::class)->emitir($this->matricula);

    $this->actingAs($this->aluno)->get(route('meu-dossie'))
        ->assertInertia(fn ($tela) => $tela
            ->where('resumo.concluidos', 1)
            ->where('resumo.horas', 16)
            ->where('certificados.0.codigo', $certificado->codigo)
            ->where('certificados.0.valido', true)
            ->where('cursos.0.certificado', $certificado->codigo));

    app(Certificados::class)->cancelar($certificado, 'Emitido por engano');
    $this->get(route('meu-dossie'))
        ->assertInertia(fn ($tela) => $tela
            ->where('resumo.concluidos', 0)
            ->where('resumo.horas', 0)
            ->where('certificados.0.valido', false)
            ->where('cursos.0.certificado', null));
});

test('cada pessoa só vê o PRÓPRIO dossiê', function () {
    chamadaDoDossie($this, 0, true, 'Guarda e base.');

    // Outra pessoa, sem cursos: o dossiê dela vem vazio (não mostra o do aluno).
    $this->actingAs(User::factory()->create())->get(route('meu-dossie'))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 0)->has('certificados', 0)->where('resumo.presencas', 0))
        ->assertDontSee('Guarda e base.');
});

test('sem login, vai para a tela de entrar', function () {
    $this->get(route('meu-dossie'))->assertRedirect(route('login'));
});
