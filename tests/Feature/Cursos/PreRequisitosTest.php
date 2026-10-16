<?php

/*
 * Testes dos PRÉ-REQUISITOS DOS CURSOS (#88): curso anterior (certificado válido),
 * nível mínimo e tempo de filiado; o servidor recusa quem não cumpre; a página
 * mostra cada item com cumprido true/false; o IBTO matricula por exceção só com
 * motivo (gravado na matrícula e na auditoria); só o IBTO grava os requisitos.
 * Dados INVENTADOS (CPFs de teste).
 */

use App\Actions\Cursos\Matriculas;
use App\Actions\Filiados\CriarFiliado;
use App\Enums\CategoriaNivel;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Models\Equipe;
use App\Models\Nivel;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * Um curso gratuito publicado, com data futura.
 *
 * @param  string  $titulo  Ex.: 'Combate com Faca'.
 */
function cursoPublicado(string $titulo): Curso
{
    $curso = Curso::create(['titulo' => $titulo, 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'data_inicio' => today()->addDays(10)->toDateString(), 'local' => 'Recife']);
    $curso->forceFill(['situacao' => 'publicado'])->save();

    return $curso;
}

/**
 * Um certificado VÁLIDO desta pessoa neste curso (como se tivesse concluído).
 */
function certificadoDe(User $usuario, Curso $curso): CursoCertificado
{
    $matricula = (new CursoMatricula)->forceFill(['curso_id' => $curso->id, 'user_id' => $usuario->id, 'situacao' => 'confirmada', 'valor_centavos' => 0]);
    $matricula->save();
    $certificado = (new CursoCertificado)->forceFill([
        'matricula_id' => $matricula->id, 'codigo' => 'IBTO-TEST-'.$matricula->id, 'nome' => 'Fulano', 'curso' => $curso->titulo,
        'concluido_em' => today()->subMonth(), 'emitido_em' => now()->subMonth(),
    ]);
    $certificado->save();

    return $certificado;
}

beforeEach(function () {
    Notification::fake();
    $this->anterior = cursoPublicado('Combate Corpo a Corpo');
    $this->curso = cursoPublicado('Combate com Faca');
    // Um filiado com conta (CPF de teste).
    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Filiado Teste', 'email' => 'filiado@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => Equipe::factory()->create()->id,
    ]);
    $this->aluno = $this->filiado->usuario;
    $this->aluno->markEmailAsVerified();
});

test('curso anterior: sem o certificado, o servidor recusa e a página mostra o que falta', function () {
    $this->curso->requisitosCursos()->sync([$this->anterior->id]);

    expect(fn () => app(Matriculas::class)->matricular($this->curso, $this->aluno))
        ->toThrow(fn (RegraCurso $erro) => expect($erro->codigo)->toBe('MATRICULA_REQUISITO_PENDENTE'));

    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->where('requisitos.0.texto', 'Ter concluído: Combate Corpo a Corpo')
            ->where('requisitos.0.cumprido', false));
});

test('curso anterior: com o certificado válido, matricula; certificado cancelado não vale', function () {
    $this->curso->requisitosCursos()->sync([$this->anterior->id]);
    $certificado = certificadoDe($this->aluno, $this->anterior);

    // Cancelado: não vale.
    $certificado->forceFill(['cancelado_em' => now()])->save();
    expect(fn () => app(Matriculas::class)->matricular($this->curso, $this->aluno))->toThrow(RegraCurso::class);

    // Válido: matricula.
    $certificado->forceFill(['cancelado_em' => null])->save();
    expect(app(Matriculas::class)->matricular($this->curso, $this->aluno)->curso_id)->toBe($this->curso->id);
});

test('nível mínimo: abaixo recusa, igual ou acima matricula', function () {
    $monitor = Nivel::firstOrCreate(['categoria' => CategoriaNivel::Monitor, 'grau' => 1], ['ativo' => true]);
    $instrutor = Nivel::firstOrCreate(['categoria' => CategoriaNivel::Instrutor, 'grau' => 1], ['ativo' => true]);
    $this->curso->forceFill(['nivel_minimo_id' => $instrutor->id])->save();

    $this->filiado->update(['nivel_id' => $monitor->id]);
    expect(fn () => app(Matriculas::class)->matricular($this->curso, $this->aluno->fresh()))->toThrow(RegraCurso::class);

    $this->filiado->update(['nivel_id' => $instrutor->id]);
    expect(app(Matriculas::class)->matricular($this->curso, $this->aluno->fresh()))->toBeInstanceOf(CursoMatricula::class);
});

test('tempo de filiado: sem data ou com pouco tempo recusa; com o tempo, matricula', function () {
    $this->curso->forceFill(['meses_filiacao_minimo' => 12])->save();

    // Sem a data: não cumpre, e o aviso diz o porquê.
    $this->actingAs($this->aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->where('requisitos.0.cumprido', false)
            ->where('requisitos.0.texto', fn ($texto) => str_contains($texto, 'data de filiação não está cadastrada')));

    $this->filiado->update(['data_filiacao' => today()->subMonths(6)->toDateString()]);
    expect(fn () => app(Matriculas::class)->matricular($this->curso, $this->aluno->fresh()))->toThrow(RegraCurso::class);

    $this->filiado->update(['data_filiacao' => today()->subMonths(13)->toDateString()]);
    expect(app(Matriculas::class)->matricular($this->curso, $this->aluno->fresh()))->toBeInstanceOf(CursoMatricula::class);
});

test('exceção do IBTO: sem motivo explica o que falta; com motivo matricula, grava o motivo e audita', function () {
    $this->curso->requisitosCursos()->sync([$this->anterior->id]);
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $endereco = route('comando-geral.cursos.matricular', $this->curso);

    $this->actingAs($admin)->post($endereco, ['pessoa' => 'filiado@ibto.test'])->assertSessionHasErrors('matricular');
    expect(session('errors')->first('matricular'))->toContain('REQUISITO_PENDENTE_MOTIVO');
    expect(CursoMatricula::where('curso_id', $this->curso->id)->exists())->toBeFalse();

    $this->actingAs($admin)->post($endereco, ['pessoa' => 'filiado@ibto.test', 'motivo' => 'Instrutor experiente vindo de outra federação'])
        ->assertSessionHas('sucesso');
    $matricula = CursoMatricula::where('curso_id', $this->curso->id)->firstOrFail();
    expect($matricula->excecao_motivo)->toBe('Instrutor experiente vindo de outra federação')
        ->and(Auditoria::where('acao', 'MATRICULA_EXCECAO_REQUISITO')->exists())->toBeTrue();
});

test('só o IBTO grava os pré-requisitos no cadastro do curso', function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $nivel = Nivel::firstOrCreate(['categoria' => CategoriaNivel::Instrutor, 'grau' => 1], ['ativo' => true]);

    $dados = [
        'titulo' => 'Combate com Faca', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor' => '250,00', 'data_inicio' => today()->addDays(10)->toDateString(), 'local' => 'Recife',
        'nivel_minimo_id' => $nivel->id, 'meses_filiacao_minimo' => 12, 'requisitos_enviados' => '1', 'requisitos' => [$this->anterior->id],
    ];
    $this->actingAs($admin)->put(route('comando-geral.cursos.update', $this->curso), $dados)->assertSessionHasNoErrors();

    $curso = $this->curso->fresh();
    expect($curso->nivel_minimo_id)->toBe($nivel->id)
        ->and($curso->meses_filiacao_minimo)->toBe(12)
        ->and($curso->requisitosCursos()->pluck('cursos.id')->all())->toBe([$this->anterior->id]);

    // O curso não pode exigir a si mesmo.
    $this->actingAs($admin)->put(route('comando-geral.cursos.update', $this->curso), [...$dados, 'requisitos' => [$this->curso->id]])
        ->assertSessionHasErrors('requisitos.0');
});
