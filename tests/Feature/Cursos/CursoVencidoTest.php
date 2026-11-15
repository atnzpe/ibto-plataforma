<?php

/*
 * Testes da regra "CURSO VENCIDO NÃO APARECE" (15/11/2026, mesma regra da CBSA):
 * o curso cujo último dia já passou sai do catálogo, do filtro de áreas e da
 * lista do site; o que termina hoje, o que está acontecendo e o sem data
 * (online) continuam; a página do curso antigo continua abrindo pelo link.
 * Dados INVENTADOS. O "hoje" é congelado em 15/11/2026 para o teste não mudar com o calendário.
 */

use App\Enums\SituacaoCurso;
use App\Models\AreaAtuacao;
use App\Models\Curso;

// Antes de cada teste: congela o relógio em 15/11/2026, ao meio-dia (horário de Brasília).
beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
});

/**
 * Cria um curso PUBLICADO com as datas indicadas.
 *
 * @param  string  $titulo  O nome do curso. Ex.: 'Passado'.
 * @param  string|null  $inicio  Primeiro dia (ano-mês-dia) ou null (curso online, sem data).
 * @param  string|null  $fim  Último dia ou null (curso de um dia só).
 * @param  array<string, mixed>  $troca  Outros campos. Ex.: ['area_atuacao_id' => 3]
 * @return Curso O curso criado.
 */
function cursoComDatas(string $titulo, ?string $inicio, ?string $fim = null, array $troca = []): Curso
{
    $curso = new Curso([
        'titulo' => $titulo, 'modalidade' => $inicio === null ? 'online' : 'presencial', 'acesso' => 'aberto',
        'valor_centavos' => 0, 'data_inicio' => $inicio, 'data_fim' => $fim, ...$troca,
    ]);
    // A situação fica fora do "create" (o Model protege): por isso o forceFill.
    $curso->forceFill(['situacao' => SituacaoCurso::Publicado])->save();

    return $curso;
}

test('o catálogo tira os vencidos e mantém hoje, os em andamento e os sem data', function () {
    cursoComDatas('Passado (um dia)', '2026-11-05');
    cursoComDatas('Terminou ontem', '2026-11-10', '2026-11-14');
    cursoComDatas('Termina hoje', '2026-11-15');
    cursoComDatas('Em andamento', '2026-11-10', '2026-11-20');
    cursoComDatas('Futuro', '2027-02-21');
    cursoComDatas('Online sem data', null);

    $this->get(route('catalogo.index'))->assertOk()->assertInertia(fn ($tela) => $tela
        ->where('cursos', fn ($cursos) => collect($cursos)->pluck('titulo')->sort()->values()->all() === [
            'Em andamento', 'Futuro', 'Online sem data', 'Termina hoje',
        ]));
});

test('o filtro de áreas não mostra área que só tem curso vencido', function () {
    $vencida = AreaAtuacao::factory()->create(['nome' => 'Área só com curso antigo']);
    $vigente = AreaAtuacao::factory()->create(['nome' => 'Área com curso futuro']);
    cursoComDatas('Antigo', '2026-10-10', null, ['area_atuacao_id' => $vencida->id]);
    cursoComDatas('Novo', '2027-01-23', null, ['area_atuacao_id' => $vigente->id]);

    $this->get(route('catalogo.index'))->assertInertia(fn ($tela) => $tela
        ->where('areas', fn ($areas) => collect($areas)->pluck('nome')->all() === ['Área com curso futuro']));
});

test('a lista do site (API pública) também tira os vencidos', function () {
    cursoComDatas('Passado', '2026-11-05');
    cursoComDatas('Futuro', '2027-02-21');

    $titulos = collect($this->getJson(route('api.publico.cursos'))->assertOk()->json('cursos'))->pluck('titulo')->all();

    expect($titulos)->toBe(['Futuro']);
});

test('a página do curso vencido continua abrindo pelo link (certificados apontam para ela)', function () {
    $antigo = cursoComDatas('Passado', '2026-11-05');

    $this->get(route('catalogo.curso', $antigo))->assertOk();
});
