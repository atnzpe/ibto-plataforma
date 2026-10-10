<?php

/*
 * PORTEIRO da conta desativada (#36; pedido do Gleyson em 10/10/2026).
 *
 * Roda em TODO pedido das telas (grupo "web"). Se a pessoa logada está com a
 * conta desativada, ela é deslogada na hora e volta para a tela de entrar com
 * o aviso. Pega todos os jeitos de entrar (senha, passkey, dois fatores) e
 * também quem já estava logado quando a conta foi desativada.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BloquearContaDesativada
{
    /**
     * Confere a conta e deixa passar, ou desloga.
     *
     * @param  Request  $request  O pedido. Ex.: abrir /comando-geral.
     * @param  Closure  $next  O resto do caminho (a tela pedida).
     * @return Response A tela pedida, ou o redirecionamento para entrar.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Visitante ou conta ativa: segue normalmente.
        if ($usuario === null || $usuario->desativada_em === null) {
            return $next($request);
        }

        // Conta desativada: encerra a sessão (logout + sessão nova, para o
        // cookie antigo não servir para mais nada) e manda para a tela de entrar.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Esta conta está desativada. Fale com a administração do IBTO. (código CONTA_DESATIVADA)',
        ]);
    }
}
