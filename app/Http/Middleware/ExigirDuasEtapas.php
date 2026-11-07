<?php

/*
 * EXIGIR A VERIFICAÇÃO EM DUAS ETAPAS DA EQUIPE (07/11/2026): quem tem acesso
 * sensível (financeiro, filiados, permissões...) e ainda não ligou a segunda
 * etapa é levado para Configurações > Segurança antes de usar a plataforma.
 * A regra de quem é barrado fica em App\Support\DuasEtapas.
 * As telas para LIGAR a segunda etapa, confirmar a senha, sair e a política
 * ficam livres (senão a pessoa ficaria presa sem conseguir se proteger ou sair).
 */

namespace App\Http\Middleware;

use App\Support\DuasEtapas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigirDuasEtapas
{
    /**
     * Rotas que nunca são bloqueadas. Ex.: 'two-factor.*' = ligar, confirmar,
     * QR code e códigos de recuperação; 'passkey.*' = cadastrar a chave de acesso.
     */
    private const LIVRES = [
        'security.edit',
        'user-password.update',
        'two-factor.*',
        'passkey.*',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'logout',
        'verification.*',
        'privacidade',
        'privacidade.*',
    ];

    /**
     * Deixa passar ou leva para a tela de Segurança.
     *
     * @param  Request  $request  O pedido que chegou.
     * @param  Closure(Request): Response  $next  O resto do caminho.
     * @return Response A tela pedida, ou o redirecionamento para a Segurança.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Visitante, rota livre ou pessoa que não precisa (ou já ligou): segue normalmente.
        if ($usuario === null
            || $request->routeIs(...self::LIVRES)
            || ! DuasEtapas::pendentePara($usuario)) {
            return $next($request);
        }

        // Guarda a tela que a pessoa queria, para voltar a ela depois de ligar.
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        // Leva para a Segurança; a tela mostra o aviso explicando o porquê.
        return redirect()->route('security.edit');
    }
}
