<?php

/*
 * API PÚBLICA DA GALERIA DE EVENTOS (#61, bloco B4): os álbuns de fotos
 * PUBLICADOS, em JSON, para as páginas /eventos e /eventos/{slug} do SITE.
 * Só leitura, sem login.
 *
 * Só sai foto de álbum publicado (que exige a autorização de imagem e a
 * descrição de cada foto). Álbum que não está no site responde 404 com o
 * código estável EVENTO_NAO_ENCONTRADO.
 * Ex.: GET https://cursos.ib-to.org/api/publico/eventos?pagina=1
 *      GET https://cursos.ib-to.org/api/publico/eventos/estande-de-tiro-pe
 */

namespace App\Http\Controllers\Api;

use App\Enums\SituacaoAlbum;
use App\Http\Controllers\Blog\GaleriaController;
use App\Http\Controllers\Controller;
use App\Models\GaleriaAlbum;
use App\Support\Galeria;
use Illuminate\Http\JsonResponse;

class PublicoEventosController extends Controller
{
    // Quantos álbuns por página da lista (o mesmo número da tela da plataforma).
    private const POR_PAGINA = 12;

    /**
     * Uma página dos álbuns publicados, os eventos mais novos primeiro.
     *
     * @return JsonResponse {"albuns": [{slug, titulo, data_evento, local_evento, fotos, capa_url, capa_descricao}],
     *                      "pagina": {"atual", "ultima", "total"}}
     */
    public function index(): JsonResponse
    {
        // with('fotos'): carrega as fotos junto, numa consulta só (evita uma consulta por álbum).
        $pagina = GaleriaAlbum::query()
            ->where('situacao', SituacaoAlbum::Publicado)
            ->with('fotos')
            ->orderByDesc('data_evento')->orderByDesc('id')
            ->paginate(self::POR_PAGINA, ['*'], 'pagina');

        $albuns = collect($pagina->items())->map(fn (GaleriaAlbum $album) => [
            'slug' => $album->slug,
            'titulo' => $album->titulo,
            // A data do evento. Ex.: '2026-10-11'.
            'data_evento' => $album->data_evento?->toDateString(),
            'local_evento' => $album->local_evento,
            // Quantas fotos o álbum tem (o cartão mostra "12 fotos").
            'fotos' => $album->fotos->count(),
            // A capa: a primeira foto, só no modo público (no privado, o site mostra um cartão sem foto).
            'capa_url' => Galeria::publica() ? $album->fotos->first()?->url() : null,
            'capa_descricao' => Galeria::publica() ? $album->fotos->first()?->descricao : null,
            // true = fotos só para filiados, com login na plataforma (modo privado, o padrão).
            'fotos_restritas' => ! Galeria::publica(),
        ])->values();

        // Cache de 5 minutos, como o resto da API pública.
        return response()->json([
            'albuns' => $albuns,
            'pagina' => [
                'atual' => $pagina->currentPage(),
                'ultima' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Um álbum publicado, com todas as fotos.
     *
     * @param  string  $slug  O nome do álbum no endereço. Ex.: 'estande-de-tiro-pe'.
     * @return JsonResponse {"album": {slug, titulo, descricao, data_evento, local_evento, post, fotos}}
     *                      ou 404 {"codigo": "EVENTO_NAO_ENCONTRADO"}.
     */
    public function show(string $slug): JsonResponse
    {
        $album = GaleriaAlbum::query()->where('slug', $slug)->first();

        // Não existe ou não está no site: o mesmo 404 para os dois casos.
        if ($album === null || ! $album->visivelNoSite()) {
            return response()->json(['codigo' => 'EVENTO_NAO_ENCONTRADO'], 404);
        }

        return response()->json([
            'album' => [
                'slug' => $album->slug,
                'titulo' => $album->titulo,
                'descricao' => $album->descricao,
                'data_evento' => $album->data_evento?->toDateString(),
                'local_evento' => $album->local_evento,
                // O post ligado (só o slug e o título), se ele já está no site; senão, null.
                'post' => $album->post?->visivelNoSite()
                    ? ['slug' => $album->post->slug, 'titulo' => $album->post->titulo]
                    : null,
                // As fotos: endereço completo, descrição (texto alternativo) e crédito. Só no modo
                // público; no privado (padrão) a lista vai vazia e o site mostra o botão para entrar.
                'fotos' => Galeria::publica() ? GaleriaController::fotosParaTela($album) : [],
                'fotos_restritas' => ! Galeria::publica(),
                // Onde ver as fotos com login (a página do álbum na plataforma).
                'fotos_url' => route('galeria.album', $album->slug),
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
