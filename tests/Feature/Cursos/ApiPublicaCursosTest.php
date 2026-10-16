<?php

/*
 * Testes da API PÚBLICA DOS CURSOS (#52): só os publicados e não fechados
 * (a Formação de instrutor nunca aparece), com o endereço completo da página
 * do curso; o site do IBTO lê pelo navegador (CORS). Dados INVENTADOS.
 */

use App\Enums\SituacaoCurso;
use App\Models\Curso;

/**
 * Um curso na situação pedida.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['titulo' => 'Tiro']
 */
function cursoDaApi(SituacaoCurso $situacao, bool $fechado = false, array $troca = []): Curso
{
    $curso = new Curso(['titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 25000, ...$troca]);
    $curso->forceFill(['situacao' => $situacao, 'fechado' => $fechado])->save();

    return $curso;
}

test('só os cursos publicados e não fechados, com o endereço da página', function () {
    $publicado = cursoDaApi(SituacaoCurso::Publicado);
    cursoDaApi(SituacaoCurso::Rascunho, troca: ['titulo' => 'Rascunho']);
    cursoDaApi(SituacaoCurso::Publicado, fechado: true, troca: ['titulo' => 'Formação de instrutor']);

    $this->getJson(route('api.publico.cursos'))
        ->assertOk()
        ->assertJsonCount(1, 'cursos')
        ->assertJsonPath('cursos.0.titulo', 'Combate Corpo a Corpo')
        ->assertJsonPath('cursos.0.url', route('catalogo.curso', $publicado->id))
        ->assertJsonPath('cursos.0.tipo', 'Pago · aberto ao público');
});

test('o site do IBTO lê pelo navegador (CORS)', function () {
    $this->getJson(route('api.publico.cursos'), ['Origin' => 'https://ib-to.org'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://ib-to.org');
});

test('curso cancelado some da lista na hora, e o site guarda a lista só por 1 minuto', function () {
    $curso = cursoDaApi(SituacaoCurso::Publicado);
    $curso->update(['situacao' => SituacaoCurso::Cancelado]);

    $this->getJson(route('api.publico.cursos'))
        ->assertOk()
        ->assertJsonMissing(['id' => $curso->id])
        ->assertHeader('Cache-Control', 'max-age=60, public');
});
