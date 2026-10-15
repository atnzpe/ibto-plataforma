<?php

/*
 * GALERIA DE EVENTOS PÚBLICA (#61, bloco B2; o "departamento de eventos" que
 * o Mestre Eduardo pediu em 14/10/2026): qualquer pessoa, sem login, vê os
 * álbuns PUBLICADOS (/eventos) e as fotos de cada um (/eventos/{slug}).
 * Também entrega cada foto por rota própria (/imagens/galeria/{foto}), só de
 * álbum publicado e só foto descrita; o resto, só para quem cuida do blog.
 */

namespace App\Http\Controllers\Blog;

use App\Enums\SituacaoAlbum;
use App\Http\Controllers\Controller;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GaleriaController extends Controller
{
    /**
     * Os álbuns publicados, os eventos mais novos primeiro (12 por página).
     *
     * @return Response A tela "galeria/index".
     */
    public function index(): Response
    {
        $pagina = GaleriaAlbum::query()
            ->where('situacao', SituacaoAlbum::Publicado)
            ->with('fotos')
            ->orderByDesc('data_evento')->orderByDesc('id')
            ->paginate(12);

        return Inertia::render('galeria/index', [
            'albuns' => [
                'data' => collect($pagina->items())->map(fn (GaleriaAlbum $album) => [
                    'slug' => $album->slug,
                    'titulo' => $album->titulo,
                    'data_evento' => $album->data_evento?->toDateString(),
                    'local_evento' => $album->local_evento,
                    'fotos' => $album->fotos->count(),
                    // A capa do álbum: a primeira foto (na ordem).
                    'capa_url' => $album->fotos->first()?->url(),
                    'capa_descricao' => $album->fotos->first()?->descricao,
                ])->values(),
                'links' => $pagina->linkCollection()->toArray(),
            ],
        ]);
    }

    /**
     * As fotos de um álbum. Álbum que não está no site: 404 ("não existe").
     *
     * @param  GaleriaAlbum  $album  O álbum do endereço, pelo slug. Ex.: /eventos/estande-de-tiro-pe
     * @return Response A tela "galeria/album".
     */
    public function show(GaleriaAlbum $album): Response
    {
        abort_unless($album->visivelNoSite(), 404);

        return Inertia::render('galeria/album', [
            'album' => [
                'titulo' => $album->titulo,
                'descricao' => $album->descricao,
                'data_evento' => $album->data_evento?->toDateString(),
                'local_evento' => $album->local_evento,
                // O post ligado, se ele já está no site (senão, sem link).
                'post' => $album->post?->visivelNoSite() ? ['slug' => $album->post->slug, 'titulo' => $album->post->titulo] : null,
                'fotos' => self::fotosParaTela($album),
            ],
        ]);
    }

    /**
     * Entrega uma foto. Pública só de álbum publicado; senão, só para quem
     * cuida do blog (a prévia na edição). Outros: 404.
     *
     * @param  GaleriaFoto  $foto  A foto do endereço (ex.: /imagens/galeria/31).
     * @return StreamedResponse A imagem, guardada no navegador por 1 dia.
     */
    public function foto(GaleriaFoto $foto): StreamedResponse
    {
        $disco = Storage::disk('public');
        $publica = $foto->album->visivelNoSite() && $foto->descrita();

        abort_if(! $publica && ! request()->user()?->can('blog.publicar'), 404);
        abort_if(! $disco->exists($foto->caminho), 404);

        return $disco->response($foto->caminho, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    /**
     * As fotos do álbum como a tela usa (endereço, descrição e crédito).
     * Também usada pela página do post ligado (BlogController).
     *
     * @param  GaleriaAlbum  $album  O álbum.
     * @return list<array{id: int, url: string, descricao: string|null, credito: string}>
     */
    public static function fotosParaTela(GaleriaAlbum $album): array
    {
        return array_values($album->fotos()->get()
            ->map(fn (GaleriaFoto $foto) => [
                'id' => $foto->id,
                'url' => $foto->url(),
                'descricao' => $foto->descricao,
                // Crédito da foto, ou o padrão do álbum.
                'credito' => $foto->credito ?: $album->credito_padrao,
            ])->all());
    }
}
