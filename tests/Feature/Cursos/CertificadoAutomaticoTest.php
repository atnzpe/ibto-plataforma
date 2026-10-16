<?php

/*
 * Testes do CERTIFICADO AUTOMÁTICO do curso gravado (#72):
 * - curso só gravado: ao marcar a última aula, o certificado sai sozinho, uma vez só;
 * - antes da última aula: nada, e o motivo diz quantas faltam;
 * - curso com encontros: continua com o IBTO (não emite sozinho);
 * - curso misto: exige os 75% de presença E 100% das gravadas;
 * - quem só organiza o curso (sem matrícula) não ganha certificado.
 * Dados INVENTADOS.
 */

use App\Actions\Cursos\Certificados;
use App\Actions\Cursos\Matriculas;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoCertificado;
use App\Models\CursoModulo;
use App\Models\Papel;
use App\Models\User;
use App\Services\SalaDeAula;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    // Curso online gratuito, só com 2 aulas gravadas (sem data).
    $this->curso = Curso::create(['titulo' => 'Metodologia de Instrução', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 0, 'carga_horaria' => 4]);
    $this->curso->forceFill(['situacao' => 'publicado'])->save();
    $this->modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->aula1 = $this->modulo->aulas()->create(['titulo' => 'Demonstrar', 'ordem' => 1, 'ativa' => true]);
    $this->aula2 = $this->modulo->aulas()->create(['titulo' => 'Explicar', 'ordem' => 2, 'ativa' => true]);

    $this->aluno = User::factory()->create();
    $this->matricula = app(Matriculas::class)->matricular($this->curso, $this->aluno);
});

/**
 * Marca uma aula pela tela, como o aluno faria.
 */
function marcarPelaTela($teste, User $quem, $aula)
{
    return $teste->actingAs($quem)->put(
        route('cursos.assistida', ['curso' => $teste->curso->id, 'aula' => $aula->id]),
        ['assistida' => true],
    );
}

test('curso gravado: o certificado sai sozinho na última aula, com o parabéns', function () {
    // Primeira aula: ainda falta uma; nada de certificado.
    marcarPelaTela($this, $this->aluno, $this->aula1)->assertSessionMissing('sucesso');
    expect(CursoCertificado::count())->toBe(0)
        ->and(app(Certificados::class)->apto($this->matricula->fresh())['motivo'])->toBe('Faltam 1 de 2 aulas gravadas para assistir.');

    // Última aula: certificado emitido sozinho, sem "emitido por", e auditado.
    marcarPelaTela($this, $this->aluno, $this->aula2)->assertSessionHas('sucesso');
    $certificado = CursoCertificado::firstOrFail();
    expect($certificado->matricula_id)->toBe($this->matricula->id)
        ->and($certificado->emitido_por)->toBeNull()
        ->and(Auditoria::where('acao', 'CERTIFICADO_EMITIDO_AUTOMATICO')->exists())->toBeTrue();
});

test('marcar de novo não emite um segundo certificado', function () {
    marcarPelaTela($this, $this->aluno, $this->aula1);
    marcarPelaTela($this, $this->aluno, $this->aula2);
    marcarPelaTela($this, $this->aluno, $this->aula2);

    expect(CursoCertificado::count())->toBe(1);
});

test('curso com encontros não emite sozinho (continua com o IBTO)', function () {
    $this->modulo->aulas()->create(['titulo' => 'Encontro ao vivo', 'ordem' => 3, 'ativa' => true, 'data' => today()->toDateString()]);

    marcarPelaTela($this, $this->aluno, $this->aula1);
    marcarPelaTela($this, $this->aluno, $this->aula2);

    expect(CursoCertificado::count())->toBe(0);
});

test('curso misto: precisa da presença E de todas as gravadas', function () {
    $encontro = $this->modulo->aulas()->create(['titulo' => 'Encontro', 'ordem' => 3, 'ativa' => true, 'data' => today()->toDateString()]);
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin)->put(
        route('comando-geral.cursos.chamada.update', ['curso' => $this->curso->id, 'aula' => $encontro->id]),
        ['presentes' => [$this->matricula->id]],
    );

    // Presente no encontro, mas com uma gravada faltando: não está apto.
    app(SalaDeAula::class)->marcar($this->aula1, $this->aluno, true);
    expect(app(Certificados::class)->apto($this->matricula->fresh())['apto'])->toBeFalse();

    // Com todas as gravadas: apto (a emissão continua pelo IBTO).
    app(SalaDeAula::class)->marcar($this->aula2, $this->aluno, true);
    expect(app(Certificados::class)->apto($this->matricula->fresh())['apto'])->toBeTrue();
});

test('quem só organiza o curso (sem matrícula) não ganha certificado', function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    marcarPelaTela($this, $admin, $this->aula1);
    marcarPelaTela($this, $admin, $this->aula2);

    expect(CursoCertificado::count())->toBe(0);
});
