<?php

/*
 * Tela do painel "COMANDO GERAL" (#59 bloco 4 / #45): os números vêm do
 * App\Services\ComandoGeral. Permissão "comando_geral.ver" (routes/web.php).
 */

namespace App\Http\Controllers;

use App\Services\ComandoGeral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComandoGeralController extends Controller
{
    /**
     * @param  Request  $request  Quem está vendo (para saber se pode lançar).
     * @param  ComandoGeral  $comando  O serviço que calcula o painel.
     * @return Response A tela "comando-geral".
     */
    public function __invoke(Request $request, ComandoGeral $comando): Response
    {
        return Inertia::render('comando-geral', [
            ...$comando->painel(),
            // Atalhos da tela (o servidor confere de novo em cada rota).
            'pode_lancar' => $request->user()?->can('financeiro.lancar') ?? false,
            'pode_ver_financeiro' => $request->user()?->can('financeiro.ver') ?? false,
        ]);
    }
}
