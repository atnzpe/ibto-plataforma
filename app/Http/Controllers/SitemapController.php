<?php

/*
 * SITEMAP DA PLATAFORMA (#74): a lista das páginas públicas para os robôs de
 * busca (Google, Bing, IAs): o catálogo, cada curso PUBLICADO e aberto ao site
 * e cada trilha ativa. Fica em /sitemap.xml e é citado no robots.txt.
 * Nada de área com login, validação de certificado ou carteirinha (têm nomes).
 * Como o índice de um livro: diz onde está cada capítulo, sem o conteúdo.
 */

namespace App\Http\Controllers;

use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\Trilha;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Monta o XML do sitemap.
     *
     * @return Response O arquivo XML (formato sitemaps.org).
     */
    public function __invoke(): Response
    {
        // Cada item: [endereço, data da última mudança (AAAA-MM-DD) ou null].
        $paginas = [[route('catalogo.index'), null]];

        // Os cursos que o público vê no catálogo: publicados e não fechados.
        foreach (Curso::query()->where('situacao', SituacaoCurso::Publicado)->noSite()->orderBy('id')->get(['id', 'updated_at']) as $curso) {
            $paginas[] = [route('catalogo.curso', $curso), $curso->updated_at?->toDateString()];
        }

        // As trilhas ativas.
        foreach (Trilha::query()->where('ativa', true)->orderBy('id')->get(['id', 'updated_at']) as $trilha) {
            $paginas[] = [route('catalogo.trilha', $trilha), $trilha->updated_at?->toDateString()];
        }

        // Monta o XML linha por linha; e() protege o "&" e os sinais < > dos endereços.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($paginas as [$endereco, $data]) {
            $xml .= '  <url><loc>'.e($endereco).'</loc>'.($data !== null ? '<lastmod>'.$data.'</lastmod>' : '').'</url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        // Guarda por 1 hora no navegador e nos robôs (a lista muda pouco).
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
