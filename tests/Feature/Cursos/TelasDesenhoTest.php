<?php

/*
 * Testes das LACUNAS DOS DESENHOS de 10/10/2026, implementadas em 15/10:
 * - página do curso: a situação de cada aula para quem está matriculado
 *   (presente, falta, aguardando, assistida) e as vagas preenchidas;
 * - Meus cursos: o resumo do dossiê (os 3 registros mais recentes).
 * Dados INVENTADOS.
 */

use App\Actions\Cursos\Certificados;
use App\Actions\Cursos\Matriculas;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\Papel;
use App\Models\User;
use App\Services\SalaDeAula;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    // Curso gratuito com 2 encontros (com data) e 1 aula online (sem data), 10 vagas.
    $this->curso = Curso::create(['titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'vagas' => 10, 'carga_horaria' => 8, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['situacao' => 'publicado'])->save();
    $modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->encontro1 = $modulo->aulas()->create(['titulo' => 'Encontro 1', 'ordem' => 1, 'ativa' => true, 'data' => today()->toDateString()]);
    $this->encontro2 = $modulo->aulas()->create(['titulo' => 'Encontro 2', 'ordem' => 2, 'ativa' => true, 'data' => today()->toDateString()]);
    $this->online = $modulo->aulas()->create(['titulo' => 'Aula online', 'ordem' => 3, 'ativa' => true]);

    // O aluno, matriculado (gratuito = confirmada na hora).
    $this->aluno = User::factory()->create();
    $this->matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno);
});

test('página do curso: a situação de cada aula para o matriculado e as vagas preenchidas', function () {
    // Chamada do encontro 1 com o aluno presente; o encontro 2 ainda sem chamada.
    $this->actingAs($this->admin)->put(
        route('comando-geral.cursos.chamada.update', ['curso' => $this->curso->id, 'aula' => $this->encontro1->id]),
        ['presentes' => [$this->matricula->id], 'registro_encontro' => 'Guarda e base.'],
    );
    // A aula online marcada como assistida.
    app(SalaDeAula::class)->marcar($this->online, $this->aluno, true);

    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->where('curso.modulos.0.aulas.0.minha_situacao', 'presente')
            ->where('curso.modulos.0.aulas.1.minha_situacao', 'aguardando')
            ->where('curso.modulos.0.aulas.2.minha_situacao', 'assistida')
            ->where('vagas_preenchidas', 1));
});

test('página do curso: visitante não vê situação nenhuma', function () {
    $this->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('curso.modulos.0.aulas.0.minha_situacao', null));
});

test('Meus cursos: o resumo do dossiê traz o certificado e as presenças, sem o cancelado', function () {
    // Presente nos 2 encontros e com a aula gravada assistida: apto ao certificado
    // (curso misto exige as duas coisas desde a #72).
    foreach ([$this->encontro1, $this->encontro2] as $encontro) {
        $this->actingAs($this->admin)->put(
            route('comando-geral.cursos.chamada.update', ['curso' => $this->curso->id, 'aula' => $encontro->id]),
            ['presentes' => [$this->matricula->id], 'registro_encontro' => 'Guarda e base.'],
        );
    }
    app(SalaDeAula::class)->marcar($this->online, $this->aluno, true);
    $certificado = app(Certificados::class)->emitir($this->matricula);

    $this->actingAs($this->aluno)->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela
            ->has('dossie', 3)
            ->where('dossie', fn ($registros) => collect($registros)->contains('tipo', 'certificado')));

    // Cancelado: sai do resumo (ficam só as 2 presenças).
    app(Certificados::class)->cancelar($certificado, 'Emitido por engano');
    $this->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela
            ->has('dossie', 2)
            ->where('dossie', fn ($registros) => ! collect($registros)->contains('tipo', 'certificado')));
});
