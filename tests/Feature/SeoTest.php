<?php

/*
 * Testes do SEO das telas públicas (#74): o HTML já sai com a descrição, o
 * cartão do link (Open Graph), o endereço oficial e os dados de "Course"; um
 * título com "</script>" não quebra a página; prévia, curso fechado e as
 * validações (certificado e carteirinha) levam "noindex"; o sitemap lista só
 * o que é público. Dados INVENTADOS.
 */

use App\Enums\SituacaoCurso;
use App\Models\Curso;

/**
 * Cria um curso do catálogo para os testes do SEO.
 *
 * @param  array<string, mixed>  $extra  O que muda. Ex.: ['fechado' => true].
 */
function cursoDoSeo(string $titulo, SituacaoCurso $situacao = SituacaoCurso::Publicado, array $extra = []): Curso
{
    $curso = new Curso(['titulo' => $titulo, 'descricao' => 'Curso <b>prático</b>   de teste.', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0]);
    $curso->forceFill(['situacao' => $situacao, ...$extra])->save();

    return $curso;
}

test('a página do curso publicado sai com descrição, cartão do link e dados de Course', function () {
    $curso = cursoDoSeo('APH Tático Teste');

    $html = $this->get(route('catalogo.curso', $curso))->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="description" content="Curso prático de teste.">')
        ->toContain('<meta property="og:title" content="APH Tático Teste')
        ->toContain('<link rel="canonical" href="'.route('catalogo.curso', $curso).'">')
        ->toContain('"@type":"Course"')
        ->toContain('"name":"APH Tático Teste"');
});

test('um título com </script> não fecha a etiqueta dos dados estruturados', function () {
    $curso = cursoDoSeo('Curso </script><script>alert(1)</script>');

    expect($this->get(route('catalogo.curso', $curso))->getContent())->not->toContain('</script><script>alert(1)');
});

test('o catálogo tem descrição; filtros não mudam o endereço oficial', function () {
    $this->get(route('catalogo.index', ['busca' => 'tiro']))
        ->assertOk()
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical" href="'.route('catalogo.index').'">', false);
});

test('com a plataforma indexável, o catálogo fica sem noindex e as validações continuam com noindex', function () {
    config(['app.indexavel' => true]);

    // Catálogo aberto e indexável: sem noindex.
    $this->get(route('catalogo.index'))->assertDontSee('noindex', false);

    // Validações com nome: sempre noindex, mesmo com a plataforma indexável.
    $this->get(route('certificados.validar', 'IBTO-AAAA-AAAA'))->assertSee('noindex', false);
    $this->get(route('carteirinha.validar', 'IBTO-AAAA-AAAA'))->assertSee('noindex', false);
});

test('o sitemap lista o catálogo e os cursos publicados abertos, sem rascunho nem curso fechado', function () {
    $publicado = cursoDoSeo('Publicado do sitemap');
    $rascunho = cursoDoSeo('Rascunho do sitemap', SituacaoCurso::Rascunho);
    $fechado = cursoDoSeo('Fechado do sitemap', SituacaoCurso::Publicado, ['fechado' => true]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('catalogo.index').'</loc>', false)
        ->assertSee('<loc>'.route('catalogo.curso', $publicado).'</loc>', false)
        ->assertDontSee(route('catalogo.curso', $rascunho).'</loc>', false)
        ->assertDontSee(route('catalogo.curso', $fechado).'</loc>', false);
});
