<?php

/*
 * API PÚBLICA DA REDE NACIONAL (#11): os monitores e instrutores credenciados,
 * organizados por estado, para a página /rede-nacional do site desenhar sozinha
 * (modelo WKMF pedido pelo Mestre Eduardo, com a cara do IBTO).
 * Só leitura, sem login; só o necessário para o cartão, NUNCA contato (a regra
 * de quem aparece e do que sai fica em App\Actions\Filiados\RedeNacional).
 * Quem lê pelo navegador: só o site do IBTO (config/cors.php).
 * Ex.: GET https://cursos.ib-to.org/api/publico/rede-nacional
 */

namespace App\Http\Controllers\Api;

use App\Actions\Filiados\RedeNacional;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PublicoRedeNacionalController extends Controller
{
    /**
     * As duas listas (instrutores e monitores), cada uma por estado.
     *
     * @param  RedeNacional  $rede  A regra de quem aparece.
     * @return JsonResponse {"instrutores": [{uf, estado, pessoas: [{nome, nivel, equipe, uf, areas, foto_url}]}], "monitores": [...], "estados": {"PE": "Pernambuco", ...}, "total": 12}
     */
    public function __invoke(RedeNacional $rede): JsonResponse
    {
        return response()->json([
            ...$rede->listas(),
            // Os 27 estados, para o site montar a grade de siglas (estado vazio fica apagado).
            'estados' => RedeNacional::ESTADOS,
        ])
            // Cache de 5 minutos (como blog e eventos): quem tira a autorização ou a foto
            // sai do site em até 5 minutos, sem o site consultar a plataforma a cada visita.
            ->header('Cache-Control', 'public, max-age=300');
    }
}
