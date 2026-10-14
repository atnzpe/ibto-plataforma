<?php

/*
 * Testes do PROGRESSO DAS AULAS ONLINE (piloto, 14/10/2026): marcar e
 * desmarcar a aula como assistida; "x de y (z%)" na sala de aula; o botão
 * "Continuar" de Meus cursos na 1ª aula não assistida; marcar duas vezes não
 * duplica; aula de outro curso dá 404; quem não está inscrito, 403.
 * Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoAulaAssistida;
use App\Models\CursoModulo;
use App\Models\FinFormaPagamento;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // O e-mail de matrícula confirmada não sai de verdade no teste.
    Notification::fake();

    // Um curso online publicado, com 1 módulo e 3 aulas.
    $this->curso = new Curso(['titulo' => 'Tiro Defensivo', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000]);
    $this->curso->forceFill(['situacao' => SituacaoCurso::Publicado])->save();
    $modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->aulas = collect([1, 2, 3])->map(fn (int $ordem) => $modulo->aulas()->create(['titulo' => "Aula {$ordem}", 'ordem' => $ordem, 'ativa' => true]));

    // Um aluno com a matrícula CONFIRMADA (pagou o PIX).
    $this->aluno = User::factory()->create();
    $matriculas = app(Matriculas::class);
    $matriculas->confirmarPagamento(
        $matriculas->matricular($this->curso, $this->aluno),
        '2026-10-14',
        (int) FinFormaPagamento::where('nome', 'PIX')->value('id'),
    );
});

/**
 * O endereço de marcar/desmarcar uma aula do curso do teste.
 */
function enderecoAssistida(Curso $curso, CursoAula $aula): string
{
    return route('cursos.assistida', ['curso' => $curso->id, 'aula' => $aula->id]);
}

test('marcar a 1ª aula: 1 de 3 (33%), ✓ na lista; desmarcar volta a 0', function () {
    $primeira = $this->aulas[0];
    $this->actingAs($this->aluno)->put(enderecoAssistida($this->curso, $primeira), ['assistida' => true])->assertRedirect();

    $this->get(route('cursos.assistir', ['curso' => $this->curso->id, 'aula' => $primeira->id]))
        ->assertInertia(fn ($tela) => $tela
            ->where('aula.assistida', true)
            ->where('progresso.assistidas', 1)
            ->where('progresso.total', 3)
            // 1 de 3 = 33,3...% → 33 (sem casas decimais).
            ->where('progresso.percentual', 33)
            ->where('aulas.0.assistida', true)
            ->where('aulas.1.assistida', false));

    // Desmarcar (marcou por engano).
    $this->put(enderecoAssistida($this->curso, $primeira), ['assistida' => false]);
    expect(CursoAulaAssistida::count())->toBe(0);
});

test('marcar duas vezes não duplica', function () {
    $this->actingAs($this->aluno);
    $this->put(enderecoAssistida($this->curso, $this->aulas[0]), ['assistida' => true]);
    $this->put(enderecoAssistida($this->curso, $this->aulas[0]), ['assistida' => true]);

    expect(CursoAulaAssistida::count())->toBe(1);
});

test('Meus cursos: "Continuar" vai para a 1ª não assistida; com todas, volta para a 1ª (rever)', function () {
    $this->actingAs($this->aluno);
    $this->put(enderecoAssistida($this->curso, $this->aulas[0]), ['assistida' => true]);

    $this->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela
            ->where('cursos.0.progresso.continuar_id', $this->aulas[1]->id)
            ->where('cursos.0.progresso.assistidas', 1));

    // Assiste as outras duas: 100% e o botão volta para a 1ª.
    $this->put(enderecoAssistida($this->curso, $this->aulas[1]), ['assistida' => true]);
    $this->put(enderecoAssistida($this->curso, $this->aulas[2]), ['assistida' => true]);
    $this->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela
            ->where('cursos.0.progresso.percentual', 100)
            ->where('cursos.0.progresso.continuar_id', $this->aulas[0]->id));
});

test('aula de OUTRO curso pelo endereço: 404, nada gravado', function () {
    $outro = new Curso(['titulo' => 'Outro', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000]);
    $outro->forceFill(['situacao' => SituacaoCurso::Publicado])->save();
    $moduloOutro = CursoModulo::create(['curso_id' => $outro->id, 'titulo' => 'M', 'ordem' => 1, 'ativo' => true]);
    $aulaDoOutro = $moduloOutro->aulas()->create(['titulo' => 'X', 'ordem' => 1, 'ativa' => true]);

    // O curso do aluno no endereço, mas a aula é do outro curso.
    $this->actingAs($this->aluno)->put(enderecoAssistida($this->curso, $aulaDoOutro), ['assistida' => true])->assertNotFound();
    expect(CursoAulaAssistida::count())->toBe(0);
});

test('quem não está inscrito não marca nada (403)', function () {
    $this->actingAs(User::factory()->create())
        ->put(enderecoAssistida($this->curso, $this->aulas[0]), ['assistida' => true])
        ->assertForbidden();
    expect(CursoAulaAssistida::count())->toBe(0);
});
