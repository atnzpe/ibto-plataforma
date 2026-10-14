<?php

/*
 * Testes do CURSO FECHADO (decisão do Gleyson, 14/10/2026: "a Formação de
 * instrutor NÃO deve constar no site"): fora do catálogo, das áreas do filtro
 * e das trilhas; página só para quem cuida do curso ou foi matriculado;
 * ninguém se inscreve sozinho, só o IBTO matricula; e só o IBTO marca a
 * caixa "Curso fechado". Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoCurso;
use App\Exceptions\RegraCurso;
use App\Models\AreaAtuacao;
use App\Models\Curso;
use App\Models\Papel;
use App\Models\Trilha;
use App\Models\User;

/**
 * Cria um curso PUBLICADO, pago, aberto ao público e com data futura.
 *
 * @param  bool  $fechado  true = curso fechado (fora do site).
 * @param  array<string, mixed>  $troca  Ex.: ['titulo' => 'Formação de instrutor']
 * @return Curso O curso gravado.
 */
function cursoPublicadoFechado(bool $fechado, array $troca = []): Curso
{
    $curso = new Curso([
        'titulo' => $fechado ? 'Formação de instrutor' : 'Combate Corpo a Corpo',
        'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 25000,
        // Data no futuro, para a matrícula não ser recusada por "encerrada".
        'data_inicio' => now()->addMonth()->toDateString(), 'local' => 'Recife',
        ...$troca,
    ]);
    // Situação e "fechado" ficam fora do preenchimento comum: forceFill.
    $curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'fechado' => $fechado])->save();

    return $curso;
}

/**
 * Cria uma conta com um papel. Ex.: comPapel('administrador')
 */
function comPapel(string $slug): User
{
    $usuario = User::factory()->create();
    $usuario->papeis()->attach(Papel::where('slug', $slug)->value('id'));

    return $usuario;
}

test('o catálogo e o filtro de áreas não mostram o curso fechado', function () {
    $areaAberta = AreaAtuacao::create(['nome' => 'Combate', 'ativa' => true]);
    $areaFechada = AreaAtuacao::create(['nome' => 'Docência', 'ativa' => true]);
    cursoPublicadoFechado(false, ['area_atuacao_id' => $areaAberta->id]);
    cursoPublicadoFechado(true, ['area_atuacao_id' => $areaFechada->id]);

    $this->get(route('catalogo.index'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela
            ->has('cursos', 1)
            ->where('cursos.0.titulo', 'Combate Corpo a Corpo')
            // A área que só tem curso fechado também some do filtro.
            ->has('areas', 1)
            ->where('areas.0.nome', 'Combate'));
});

test('a trilha pública não mostra o curso fechado', function () {
    $aberto = cursoPublicadoFechado(false);
    $fechado = cursoPublicadoFechado(true);
    $trilha = Trilha::create(['nome' => 'Formação', 'ativa' => true]);
    $trilha->cursos()->sync([$aberto->id => ['ordem' => 1], $fechado->id => ['ordem' => 2]]);

    $this->get(route('catalogo.trilha', $trilha))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela
            ->has('etapas', 1)
            ->where('etapas.0.cursos.0.titulo', 'Combate Corpo a Corpo'));
});

test('página do curso fechado: 404 para o público; abre para o IBTO e para o matriculado', function () {
    $curso = cursoPublicadoFechado(true);
    $aluno = User::factory()->create();

    // Visitante e pessoa logada sem matrícula: "não existe".
    $this->get(route('catalogo.curso', $curso))->assertNotFound();
    $this->actingAs($aluno)->get(route('catalogo.curso', $curso))->assertNotFound();

    // O IBTO (administrador) vê.
    $this->actingAs(comPapel('administrador'))->get(route('catalogo.curso', $curso))->assertOk();

    // Depois que o IBTO matricula, o aluno vê.
    app(Matriculas::class)->matricular($curso, $aluno, peloIbto: true);
    $this->actingAs($aluno)->get(route('catalogo.curso', $curso))->assertOk();
});

test('curso fechado: ninguém se inscreve sozinho; o IBTO matricula', function () {
    $curso = cursoPublicadoFechado(true);
    $aluno = User::factory()->create();

    // Pela regra: recusado com o código CURSO_FECHADO.
    expect(fn () => app(Matriculas::class)->matricular($curso, $aluno))
        ->toThrow(fn (RegraCurso $erro) => expect($erro->codigo)->toBe('CURSO_FECHADO'));

    // Pela tela (o botão de inscrição): também recusado, nada gravado.
    $this->actingAs($aluno)->post(route('matriculas.store', $curso));
    expect($curso->matriculas()->count())->toBe(0);

    // O IBTO matricula: aceita.
    app(Matriculas::class)->matricular($curso, $aluno, peloIbto: true);
    expect($curso->matriculas()->count())->toBe(1);
});

test('só o IBTO marca "Curso fechado"; o instrutor não consegue', function () {
    $dados = [
        'titulo' => 'Formação de instrutor', 'modalidade' => 'online', 'acesso' => 'restrito',
        'valor' => '100,00', 'fechado' => '1',
    ];

    // O IBTO cadastra fechado: grava fechado.
    $this->actingAs(comPapel('administrador'))->post(route('comando-geral.cursos.store'), $dados)
        ->assertSessionHasNoErrors();
    expect(Curso::where('titulo', 'Formação de instrutor')->first()->fechado)->toBeTrue();

    // O instrutor manda "fechado=1" mesmo assim (sem a caixa na tela): nasce aberto.
    $this->actingAs(comPapel('instrutor'))->post(route('comando-geral.cursos.store'), [...$dados, 'titulo' => 'Curso do instrutor'])
        ->assertSessionHasNoErrors();
    expect(Curso::where('titulo', 'Curso do instrutor')->first()->fechado)->toBeFalse();
});
