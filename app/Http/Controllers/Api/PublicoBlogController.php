<?php

/*
 * API PÚBLICA DO BLOG (#61, bloco B3): os posts mais recentes que JÁ ESTÃO NO
 * SITE, em JSON, para o bloco "Últimas do IBTO" da landpage. Só leitura, sem
 * login, só o necessário para o cartão (sem autor, sem dados de ninguém).
 * Quem pode ler pelo navegador: só o site do IBTO (config/cors.php).
 * Ex.: GET https://cursos.ib-to.org/api/publico/blog?quantos=3
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicoBlogController extends Controller
{
    // No máximo quantos posts uma resposta traz (o site pede 3).
    private const MAXIMO = 6;

    /**
     * Os posts mais recentes no site.
     *
     * @param  Request  $request  Ex.: ?quantos=3 (de 1 a 6; padrão 3).
     * @return JsonResponse {"posts": [{titulo, categoria, resumo, data, capa_url, capa_descricao, url}]}
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Quantos: entre 1 e 6 (min/max protegem contra pedidos absurdos). Padrão 3.
        $quantos = min(self::MAXIMO, max(1, (int) $request->query('quantos', 3)));

        $posts = Post::query()->noSite()
            ->orderByDesc('publicar_em')->orderByDesc('id')
            ->limit($quantos)->get()
            ->map(fn (Post $post) => [
                'titulo' => $post->titulo,
                'categoria' => $post->categoria->rotulo(),
                'resumo' => $post->resumo,
                'data' => $post->publicar_em?->toDateString(),
                // Endereços COMPLETOS (o site está em outro domínio).
                'capa_url' => $post->capaUrl(),
                'capa_descricao' => $post->capa_descricao,
                'url' => route('blog.post', $post->slug),
            ])->values();

        // Cache de 5 minutos no navegador e no caminho: o site não consulta a plataforma a cada visita.
        return response()->json(['posts' => $posts])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
