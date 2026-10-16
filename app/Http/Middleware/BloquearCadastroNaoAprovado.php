<?php

/*
 * PORTEIRO do cadastro com aprovação (#84; o IBTO é um grupo fechado).
 *
 * Roda em TODO pedido das telas (grupo "web"), logo depois do porteiro da
 * conta desativada. A trava fica no SERVIDOR (e não só na tela, como no Dojo
 * Manager): mesmo digitando um endereço, quem não foi aprovado não abre nada.
 * - "em análise": só abre a tela "Cadastro em análise", a confirmação de
 *   e-mail e o botão Sair;
 * - "recusado": é deslogado e volta para a tela de entrar com o aviso.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BloquearCadastroNaoAprovado
{
    // As telas que quem está "em análise" ainda pode abrir (pelo nome da rota):
    // a própria tela de espera, sair e confirmar o e-mail.
    private const ROTAS_LIBERADAS = [
        'cadastro.em-analise',
        'logout',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    /**
     * Confere a situação do cadastro e deixa passar, manda esperar ou desloga.
     *
     * @param  Request  $request  O pedido. Ex.: abrir /meus-cursos.
     * @param  Closure  $next  O resto do caminho (a tela pedida).
     * @return Response A tela pedida, a tela de espera ou a tela de entrar.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Visitante ou cadastro aprovado: segue normalmente.
        if ($usuario === null || $usuario->cadastroAprovado()) {
            return $next($request);
        }

        // Recusado: encerra a sessão (cookie antigo não serve mais) e avisa na tela de entrar.
        if ($usuario->situacao_cadastro === 'recusado') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'O seu pedido de acesso não foi aprovado. Fale com a sua equipe ou com o IBTO. (código CADASTRO_RECUSADO)',
            ]);
        }

        // Em análise numa tela liberada: segue (ex.: a própria tela de espera ou Sair).
        if (in_array($request->route()?->getName(), self::ROTAS_LIBERADAS, true)) {
            return $next($request);
        }

        // Em análise em qualquer outra tela: vai para a tela de espera.
        return redirect()->route('cadastro.em-analise');
    }
}
