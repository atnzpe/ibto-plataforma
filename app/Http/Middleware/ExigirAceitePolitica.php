<?php

/*
 * EXIGIR O ACEITE DA POLÍTICA (LGPD, #47): quando ligado (LGPD_EXIGIR_ACEITE=true),
 * quem está logado e ainda não aceitou a versão atual da política é levado para
 * /privacidade antes de usar a plataforma. Desligado (o padrão), não faz nada.
 * As telas da própria política, sair da conta e confirmar o e-mail ficam livres
 * (senão a pessoa ficaria presa sem conseguir aceitar ou sair).
 */

namespace App\Http\Middleware;

use App\Models\Aceite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigirAceitePolitica
{
    /** Rotas que nunca são bloqueadas (a política, sair e confirmar o e-mail). */
    private const LIVRES = ['privacidade', 'privacidade.aceitar', 'privacidade.revogar', 'logout', 'verification.*'];

    /**
     * Deixa passar ou leva para a política.
     *
     * @param  Request  $request  O pedido que chegou.
     * @param  Closure(Request): Response  $next  O resto do caminho.
     * @return Response A tela pedida, ou o redirecionamento para /privacidade.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Desligado, visitante, rota livre ou já aceitou: segue normalmente.
        if (! config('lgpd.exigir_aceite')
            || $usuario === null
            || $request->routeIs(...self::LIVRES)
            || Aceite::daPoliticaAtual($usuario->id) !== null) {
            return $next($request);
        }

        // Guarda a tela que a pessoa queria, para voltar a ela depois de aceitar.
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('privacidade');
    }
}
