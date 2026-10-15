<?php

/*
 * API PÚBLICA DAS ÁREAS DE ATUAÇÃO (#57; decisão do Gleyson em 07/10/2026:
 * "as áreas cadastradas na plataforma aparecem sozinhas no site"): só as
 * áreas ATIVAS, em ordem alfabética, com o nome e a imagem (quando houver).
 * Desativar uma área no cadastro tira ela do site, sem mexer no site.
 * Só leitura, sem login; quem lê pelo navegador: só o site do IBTO (config/cors.php).
 * Ex.: GET https://cursos.ib-to.org/api/publico/areas-atuacao
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AreaAtuacao;
use Illuminate\Http\JsonResponse;

class PublicoAreasController extends Controller
{
    /**
     * As áreas ativas.
     *
     * @return JsonResponse {"areas": [{nome, imagem_url}]}  (imagem_url null = sem imagem cadastrada)
     */
    public function __invoke(): JsonResponse
    {
        $areas = AreaAtuacao::query()
            ->where('ativa', true)
            ->orderBy('nome')
            ->get()
            ->map(fn (AreaAtuacao $area) => [
                'nome' => $area->nome,
                // Endereço completo da imagem (o site está em outro domínio), ou null.
                'imagem_url' => $area->imagemUrl(),
            ])->values();

        // Cache de 5 minutos: o site não consulta a plataforma a cada visita.
        return response()->json(['areas' => $areas])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
