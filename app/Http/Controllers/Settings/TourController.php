<?php

/*
 * TOUR GUIADO (#68): as duas preferências da pessoa logada.
 * - Ligar ou desligar o tour (interruptor do menu do usuário).
 * - Marcar o tour de uma tela como visto (para ele não abrir sozinho de novo).
 *
 * Só mexe na conta de quem está logado (nunca na de outra pessoa) e só guarda
 * o nome técnico da tela, nenhum dado pessoal.
 */

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    // Limite da lista de telas vistas: protege o banco de uma lista sem fim
    // (a plataforma tem menos de 60 telas; 100 sobra).
    private const MAXIMO_DE_TELAS = 100;

    /**
     * Liga ou desliga o tour guiado.
     *
     * Ao LIGAR de novo, a lista de telas vistas é zerada: a pessoa que religa
     * quer rever os tours, então cada tela volta a mostrar o seu uma vez.
     *
     * @param  Request  $request  Ex.: { ativo: true }
     * @return RedirectResponse Volta para a mesma tela, com a mensagem de confirmação.
     */
    public function atualizar(Request $request): RedirectResponse
    {
        // "boolean": aceita true/false, 1/0 e "1"/"0"; qualquer outra coisa é recusada (422).
        $dados = $request->validate(['ativo' => ['required', 'boolean']]);
        $ativo = (bool) $dados['ativo'];
        $usuario = $request->user();

        // forceFill: as colunas não estão no "Fillable" (proteção contra gravação em massa).
        $usuario->forceFill([
            'tour_ativo' => $ativo,
            // Religou: zera as telas vistas. Desligou: mantém a lista como está.
            'tours_vistos' => $ativo ? [] : $usuario->tours_vistos,
        ])->save();

        return back()->with('sucesso', $ativo ? 'Tour guiado ligado.' : 'Tour guiado desligado.');
    }

    /**
     * Marca o tour de uma tela como visto.
     *
     * @param  Request  $request  Ex.: { tela: 'comando-geral' } (o nome técnico da tela).
     * @return RedirectResponse Volta para a mesma tela, sem mensagem (é automático).
     */
    public function visto(Request $request): RedirectResponse
    {
        // Só letras minúsculas, números, "/", "_" e "-", até 60 caracteres. Ex.: 'cursos/index'.
        $dados = $request->validate(['tela' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\/_-]+$/']]);
        $usuario = $request->user();

        // A lista atual (null vira lista vazia), sem repetir a tela.
        $vistos = $usuario->tours_vistos ?? [];
        if (! in_array($dados['tela'], $vistos, true)) {
            $vistos[] = $dados['tela'];
        }

        // Guarda só as últimas 100 telas (limite de segurança).
        $usuario->forceFill(['tours_vistos' => array_slice($vistos, -self::MAXIMO_DE_TELAS)])->save();

        return back();
    }
}
