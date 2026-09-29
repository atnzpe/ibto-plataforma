<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rota de saúde (/api/saude): diz se a aplicação está no ar e se o banco responde.
 *
 * É pública, então nunca expõe detalhes internos (versões, caminhos, mensagem
 * da exceção). Em caso de falha devolve só um código estável, que também vai
 * para o log e serve para achar o problema.
 */
class SaudeController extends Controller
{
    public const CODIGO_BANCO_INDISPONIVEL = 'SAUDE_BANCO_INDISPONIVEL';

    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (Throwable $erro) {
            Log::error(self::CODIGO_BANCO_INDISPONIVEL, ['excecao' => $erro::class]);

            return response()->json([
                'status' => 'erro',
                'codigo' => self::CODIGO_BANCO_INDISPONIVEL,
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'banco' => 'ok',
        ]);
    }
}
