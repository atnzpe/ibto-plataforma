<?php

/*
 * Testes de MEUS CURSOS (desenho aprovado em 10/10/2026): a trilha de
 * formação pela categoria do filiado, o logo da equipe (ou o emblema do
 * IBTO), os cursos da pessoa com o próximo passo e que a matrícula
 * cancelada não aparece. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Actions\Filiados\CriarFiliado;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\Nivel;
use App\Models\User;

/**
 * Um curso publicado inventado.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['valor_centavos' => 0]
 */
function cursoDosMeusCursos(array $troca = []): Curso
{
    $curso = Curso::create(['titulo' => 'APH Tático', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000, ...$troca]);
    // A situação não entra pelo create (só pelo fluxo da chancela): forceFill.
    $curso->forceFill(['situacao' => 'publicado'])->save();

    return $curso;
}

test('trilha pelos NÍVEIS: o 2º nível da ordem = 1º feito, 2º atual, 3º a seguir', function () {
    // Os níveis ativos na ordem da hierarquia; o filiado fica no 2º.
    $niveis = Nivel::emOrdem(Nivel::where('ativo', true)->get())->values();
    $filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Filiado Teste', 'email' => 'monitor@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20', 'municipio' => 'Recife', 'uf' => 'PE', 'nivel_id' => $niveis[1]->id,
    ]);

    // O filiado criado ainda não confirmou o e-mail (o convite faz isso): confirma aqui.
    $filiado->usuario->markEmailAsVerified();

    $this->actingAs($filiado->usuario)->get(route('meus-cursos'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('meus-cursos')
            ->where('pessoa.filiado', true)
            // Sem equipe: sem logo (a tela mostra o emblema do IBTO).
            ->where('pessoa.logo_url', null)
            ->has('trilha', $niveis->count())
            ->where('pessoa.nivel', $niveis[1]->nome())
            ->where('trilha.0.estado', 'feita')
            ->where('trilha.1.estado', 'atual')
            ->where('trilha.2.estado', 'a_seguir'));
});

test('não filiado: trilha toda "a seguir"', function () {
    $this->actingAs(User::factory()->create())->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela->where('pessoa.filiado', false)->where('trilha.0.estado', 'a_seguir'));
});

test('cursos: o pago aguardando leva para pagar; o gratuito confirmado leva para a 1ª aula; o cancelado some', function () {
    $aluno = User::factory()->create();
    $pago = cursoDosMeusCursos(['titulo' => 'Pago']);
    $gratuito = cursoDosMeusCursos(['titulo' => 'Gratuito', 'valor_centavos' => 0]);
    $modulo = CursoModulo::create(['curso_id' => $gratuito->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $aula = $modulo->aulas()->create(['titulo' => 'Aula 1', 'ordem' => 1, 'ativa' => true]);
    $cancelado = cursoDosMeusCursos(['titulo' => 'Cancelado']);

    $matriculas = app(Matriculas::class);
    $matriculas->matricular($pago, $aluno);
    $matriculas->matricular($gratuito, $aluno);
    $matriculas->cancelar($matriculas->matricular($cancelado, $aluno), 'Desistência');

    $this->actingAs($aluno)->get(route('meus-cursos'))
        ->assertInertia(fn ($tela) => $tela
            ->has('cursos', 2)
            // As mais novas primeiro: o gratuito (2ª matrícula) e depois o pago.
            ->where('cursos.0.titulo', 'Gratuito')
            ->where('cursos.0.primeira_aula_id', $aula->id)
            ->where('cursos.1.situacao', 'aguardando_pagamento')
            ->where('cursos.1.primeira_aula_id', null));
});
