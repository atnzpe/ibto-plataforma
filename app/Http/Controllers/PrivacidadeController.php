<?php

/*
 * POLÍTICA DE PRIVACIDADE (LGPD, #47): a página pública /privacidade e o aceite
 * (aceitar e revogar) de quem está logado. Cada aceite e cada revogação ficam
 * registrados com a versão e a data (tabela "aceites") e vão para a auditoria.
 */

namespace App\Http\Controllers;

use App\Models\Aceite;
use App\Models\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrivacidadeController extends Controller
{
    /**
     * A página da política (qualquer pessoa vê; quem está logado vê também o próprio aceite).
     *
     * @param  Request  $request  Pode ser de um visitante.
     * @return Response A tela "privacidade".
     */
    public function politica(Request $request): Response
    {
        $aceite = $request->user() !== null ? Aceite::daPoliticaAtual($request->user()->id) : null;

        return Inertia::render('privacidade', [
            'versao' => config('lgpd.politica_versao'),
            'data' => config('lgpd.politica_data'),
            // null = "a definir (decisão da diretoria)".
            'encarregado' => config('lgpd.encarregado') ?: null,
            'contato' => config('lgpd.contato'),
            'logado' => $request->user() !== null,
            // Quando aceitou a versão atual (ex.: '17/10/2026 10:32'), ou null.
            'aceito_em' => $aceite?->aceito_em->format('d/m/Y H:i'),
            // true = a plataforma está pedindo o aceite antes de seguir.
            'exigido' => (bool) config('lgpd.exigir_aceite'),
        ]);
    }

    /**
     * Grava o aceite da versão atual (se já aceitou, não duplica).
     *
     * @param  Request  $request  O pedido de quem está logado.
     * @return RedirectResponse Para a tela que a pessoa queria (ou o painel).
     */
    public function aceitar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        // Já aceitou esta versão: só segue em frente (não cria outra linha).
        if (Aceite::daPoliticaAtual($usuario->id) === null) {
            Aceite::create([
                'user_id' => $usuario->id,
                'documento' => Aceite::PRIVACIDADE,
                'versao' => (string) config('lgpd.politica_versao'),
                'aceito_em' => now(),
            ]);
            Auditoria::registrar('POLITICA_ACEITA', ['versao' => config('lgpd.politica_versao')]);
        }

        return redirect()->intended(route('dashboard'))->with('sucesso', 'Aceite registrado. Obrigado.');
    }

    /**
     * Revoga o aceite (a pessoa pode mudar de ideia a qualquer momento).
     * Com o aceite obrigatório ligado, a plataforma volta a pedir o aceite.
     *
     * @param  Request  $request  O pedido de quem está logado.
     * @return RedirectResponse De volta para a política.
     */
    public function revogar(Request $request): RedirectResponse
    {
        $aceite = Aceite::daPoliticaAtual($request->user()->id);

        // Só revoga se houver um aceite valendo; senão, não há o que fazer.
        if ($aceite !== null) {
            $aceite->update(['revogado_em' => now()]);
            Auditoria::registrar('POLITICA_REVOGADA', ['versao' => $aceite->versao]);
        }

        return to_route('privacidade')->with('sucesso', 'Aceite revogado. Para pedir a exclusão dos seus dados, escreva para o contato indicado.');
    }
}
