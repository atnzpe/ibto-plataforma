<?php

/*
 * GALERIA DE EVENTOS (#61, bloco B2; o "departamento de eventos" que o Mestre
 * Eduardo pediu em 14/10/2026): os álbuns PUBLICADOS (/eventos) e as fotos de
 * cada um (/eventos/{slug}). Cada foto sai por rota própria (/imagens/galeria/{foto}).
 *
 * MODO PRIVADO (padrão desde 16/10/2026, App\Support\Galeria): só filiados e cargos
 * acima, com login, veem as fotos (muitos alunos são operadores de segurança).
 * Visitante vai para a tela de entrar; aluno sem filiação vê o aviso "exclusivo
 * para filiados". Com GALERIA_PUBLICA=true, volta a ser aberta a todos (#61).
 */

namespace App\Http\Controllers\Blog;

use App\Enums\SituacaoAlbum;
use App\Http\Controllers\Controller;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Support\Galeria;
use App\Support\SitePublico;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespostaHttp;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GaleriaController extends Controller
{
    /**
     * Os álbuns publicados, os eventos mais novos primeiro (12 por página).
     *
     * Com o site configurado (#61, B4), leva a pessoa para /eventos no SITE.
     *
     * @return Response|RespostaHttp A tela "galeria/index", ou o redirecionamento para o site.
     */
    public function index(): Response|RespostaHttp
    {
        // Modo privado: visitante vai entrar (e volta aqui depois); sem permissão, vê o aviso.
        if (! Galeria::publica()) {
            if (request()->user() === null) {
                return redirect()->guest(route('login'));
            }
            if (! Galeria::podeVer(request()->user())) {
                return Inertia::render('galeria/index', ['albuns' => ['data' => [], 'links' => []], 'restrita' => true]);
            }
        }

        // Modo público com o site configurado: a galeria mora lá. Sem site (ou modo privado): mostra aqui.
        $noSite = Galeria::publica() ? SitePublico::endereco('/eventos') : null;
        if ($noSite !== null) {
            return Inertia::location($noSite);
        }

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
            'restrita' => false,
        ]);
    }

    /**
     * As fotos de um álbum. Álbum que não está no site: 404 ("não existe").
     *
     * @param  GaleriaAlbum  $album  O álbum do endereço, pelo slug. Ex.: /eventos/estande-de-tiro-pe
     *                               Com o site configurado (#61, B4), leva a pessoa para o álbum no SITE.
     * @return Response|RespostaHttp A tela "galeria/album", ou o redirecionamento para o site.
     */
    public function show(GaleriaAlbum $album): Response|RespostaHttp
    {
        // Álbum que não está no site: para o público, não existe.
        abort_unless($album->visivelNoSite(), 404);

        // Modo privado: visitante vai entrar; sem permissão, volta para a lista (que mostra o aviso).
        if (! Galeria::podeVer(request()->user())) {
            return request()->user() === null
                ? redirect()->guest(route('login'))
                : redirect()->route('galeria.index');
        }

        // Modo público com o site configurado: o álbum mora lá. Senão: mostra aqui.
        $noSite = Galeria::publica() ? SitePublico::endereco('/eventos/'.$album->slug) : null;
        if ($noSite !== null) {
            return Inertia::location($noSite);
        }

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
        // A foto pode sair: álbum publicado, foto descrita E quem pede pode ver (modo privado: filiado para cima).
        $liberada = $foto->album->visivelNoSite() && $foto->descrita() && Galeria::podeVer(request()->user());

        // Quem cuida do blog vê sempre (a prévia na edição). Os outros, sem liberação: 404.
        abort_if(! $liberada && ! request()->user()?->can('blog.publicar'), 404);
        abort_if(! $disco->exists($foto->caminho), 404);

        // Modo privado: "private" (só o navegador de quem pediu guarda; nenhum cache no caminho).
        $cache = Galeria::publica() ? 'public, max-age=86400' : 'private, max-age=86400';

        return $disco->response($foto->caminho, null, ['Cache-Control' => $cache]);
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
