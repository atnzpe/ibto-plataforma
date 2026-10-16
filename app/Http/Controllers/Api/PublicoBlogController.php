<?php

/*
 * API PÚBLICA DO BLOG (#61, blocos B3 e B4): os posts que JÁ ESTÃO NO SITE,
 * em JSON, para a landpage. Só leitura, sem login, só o necessário para o
 * cartão (sem autor, sem dados de ninguém).
 * - B3: o bloco "Últimas do IBTO" pede os 3 mais recentes.
 * - B4: a página /blog do site pede uma página da lista (12 por vez), com
 *   filtro por categoria (Notícias ou Eventos).
 * Quem pode ler pelo navegador: só o site do IBTO (config/cors.php).
 * Ex.: GET https://cursos.ib-to.org/api/publico/blog?quantos=12&pagina=2&categoria=evento
 */

namespace App\Http\Controllers\Api;

use App\Enums\CategoriaPost;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicoBlogController extends Controller
{
    // No máximo quantos posts uma resposta traz: 12 é uma página da lista do site.
    // O limite protege o servidor de pedidos absurdos (ex.: ?quantos=100000).
    private const MAXIMO = 12;

    /**
     * Uma página dos posts no site, os mais novos primeiro.
     *
     * @param  Request  $request  Ex.: ?quantos=12&pagina=2&categoria=noticia
     *                            quantos: de 1 a 12 (padrão 3); pagina: de 1 em diante (padrão 1);
     *                            categoria: 'noticia' ou 'evento' (outro valor ou vazio = todas).
     * @return JsonResponse {"posts": [{slug, titulo, categoria, categoria_valor, resumo, data, capa_url, capa_descricao, url}],
     *                      "pagina": {"atual", "ultima", "total"}}
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Quantos: entre 1 e 12 (min/max protegem contra pedidos absurdos). Padrão 3.
        $quantos = min(self::MAXIMO, max(1, (int) $request->query('quantos', 3)));
        // Categoria válida filtra; valor desconhecido vira null (= todas), sem erro.
        $categoria = CategoriaPost::tryFrom((string) $request->query('categoria', ''));

        $consulta = Post::query()->noSite()->orderByDesc('publicar_em')->orderByDesc('id');
        // Com categoria: só ela. Sem: todas.
        if ($categoria !== null) {
            $consulta->where('categoria', $categoria);
        }

        // paginate lê o "?pagina=" (nome em português) e calcula o total de páginas.
        // Página além da última: lista vazia (o site mostra "nenhum post").
        $pagina = $consulta->paginate($quantos, ['*'], 'pagina');

        $posts = collect($pagina->items())->map(fn (Post $post) => [
            // O "slug" é o nome do post no endereço. Ex.: 'formacao-no-estande'.
            // O site monta o próprio link com ele (ex.: https://ib-to.org/blog/formacao-no-estande).
            'slug' => $post->slug,
            'titulo' => $post->titulo,
            'categoria' => $post->categoria->rotulo(),
            // O valor técnico da categoria, para o filtro do site. Ex.: 'evento'.
            'categoria_valor' => $post->categoria->value,
            'resumo' => $post->resumo,
            'data' => $post->publicar_em?->toDateString(),
            // Endereços COMPLETOS (o site está em outro domínio).
            'capa_url' => $post->capaUrl(),
            'capa_descricao' => $post->capa_descricao,
            // O endereço do post na plataforma (mantido para quem já usa).
            'url' => route('blog.post', $post->slug),
        ])->values();

        // Cache de 5 minutos no navegador e no caminho: o site não consulta a plataforma a cada visita.
        return response()->json([
            'posts' => $posts,
            'pagina' => [
                'atual' => $pagina->currentPage(),
                'ultima' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
