<?php

/*
 * API PÚBLICA DE UM POST DO BLOG (#61, bloco B4): o post inteiro, em JSON,
 * para a página /blog/{slug} do SITE. Só leitura, sem login.
 *
 * Entrega só o que o público já pode ver: post publicado e com a data
 * chegada. Rascunho, em revisão, agendado ou arquivado responde 404 com um
 * código estável (POST_NAO_ENCONTRADO), sem dizer se o post existe.
 * Ex.: GET https://cursos.ib-to.org/api/publico/blog/formacao-no-estande
 */

namespace App\Http\Controllers\Api;

use App\Enums\SituacaoAlbum;
use App\Enums\SituacaoCurso;
use App\Http\Controllers\Blog\GaleriaController;
use App\Http\Controllers\Controller;
use App\Models\GaleriaAlbum;
use App\Models\Post;
use App\Support\Galeria;
use Illuminate\Http\JsonResponse;

class PublicoPostController extends Controller
{
    /**
     * Um post do site, pelo slug.
     *
     * @param  string  $slug  O nome do post no endereço. Ex.: 'formacao-no-estande'.
     * @return JsonResponse {"post": {slug, titulo, categoria, categoria_valor, resumo, data, capa_url,
     *                      capa_descricao, texto, credito, video, curso, fotos}} ou 404 {"codigo": "POST_NAO_ENCONTRADO"}.
     */
    public function __invoke(string $slug): JsonResponse
    {
        // Busca pelo slug só entre os posts que estão no site (o filtro "noSite"
        // já deixa de fora rascunho, revisão, agendado e arquivado).
        $post = Post::query()->noSite()->where('slug', $slug)->first();

        // Não achou (ou não está no site): 404 com código estável, igual para os dois casos.
        if ($post === null) {
            return response()->json(['codigo' => 'POST_NAO_ENCONTRADO'], 404);
        }

        $curso = $post->curso;
        // O curso ligado só aparece se está publicado e é ABERTO: curso fechado
        // (ex.: Formação de instrutor) não tem inscrição pública (decisão de 14/10/2026).
        $cursoNoSite = $curso !== null && $curso->situacao === SituacaoCurso::Publicado && ! $curso->fechado;

        return response()->json([
            'post' => [
                'slug' => $post->slug,
                'titulo' => $post->titulo,
                'categoria' => $post->categoria->rotulo(),
                'categoria_valor' => $post->categoria->value,
                'resumo' => $post->resumo,
                'data' => $post->publicar_em?->toDateString(),
                'capa_url' => $post->capaUrl(),
                'capa_descricao' => $post->capa_descricao,
                // O texto como foi digitado (o site mostra as quebras de linha, sem HTML).
                'texto' => $post->texto,
                'credito' => $post->credito,
                // Como tocar o vídeo: {tipo: 'youtube', id, embed} ou {tipo: 'arquivo', url}, ou null.
                'video' => $post->video(),
                // O curso aberto ligado ao post: título e o endereço da página do curso na
                // plataforma (onde fica a inscrição). Sem curso, ou curso fechado: null.
                'curso' => $cursoNoSite ? [
                    'titulo' => $curso->titulo,
                    'url' => route('catalogo.curso', $curso->id),
                ] : null,
                // As fotos dos álbuns PUBLICADOS ligados a este post, só no modo público. No privado
                // (padrão), a lista vai vazia e "fotos_restritas" avisa o site para mostrar
                // "fotos exclusivas para filiados", com o link para entrar na plataforma.
                'fotos' => Galeria::publica()
                    ? $post->albuns()->where('situacao', SituacaoAlbum::Publicado)->get()
                        ->flatMap(fn (GaleriaAlbum $album) => GaleriaController::fotosParaTela($album))
                        ->values()->all()
                    : [],
                'fotos_restritas' => ! Galeria::publica() && $post->albuns()->where('situacao', SituacaoAlbum::Publicado)->exists(),
                'fotos_url' => route('galeria.index'),
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
