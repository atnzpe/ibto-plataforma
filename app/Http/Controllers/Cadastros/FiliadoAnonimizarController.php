<?php

/*
 * BOTÃO "ANONIMIZAR" NA FICHA DO FILIADO (LGPD, #47).
 *
 * Só quem cuida de TODOS os filiados (filiados.gerenciar, o Comando Geral) pode
 * usar. Por não ter volta, pede três confirmações: a SENHA de quem está fazendo,
 * o REGISTRO do filiado digitado de novo e o MOTIVO (ex.: "pedido do titular
 * por e-mail em 17/10/2026"). Quem faz o trabalho é o AnonimizarFiliado.
 */

namespace App\Http\Controllers\Cadastros;

use App\Actions\Filiados\AnonimizarFiliado;
use App\Http\Controllers\Controller;
use App\Models\Filiado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FiliadoAnonimizarController extends Controller
{
    /**
     * Confere as três confirmações e anonimiza.
     *
     * @param  Request  $request  Com 'senha', 'registro' e 'motivo'.
     * @param  Filiado  $filiado  O filiado do endereço (ex.: /cadastros/filiados/3/anonimizar).
     * @param  AnonimizarFiliado  $anonimizar  A ação que apaga os dados.
     * @return RedirectResponse Para a lista de filiados, com o aviso.
     */
    public function __invoke(Request $request, Filiado $filiado, AnonimizarFiliado $anonimizar): RedirectResponse
    {
        $request->validate([
            // A senha de quem está logado (current_password confere com a do banco).
            'senha' => ['required', 'current_password'],
            // O registro digitado tem que ser exatamente o deste filiado (evita anonimizar o errado).
            'registro' => ['required', 'string', Rule::in([$filiado->registro])],
            'motivo' => ['required', 'string', 'min:10', 'max:200'],
        ], [
            'senha.current_password' => 'A senha não confere.',
            'registro.in' => 'O registro digitado não é o deste filiado.',
            'motivo.min' => 'Escreva o motivo com pelo menos 10 letras (ex.: pedido do titular por e-mail).',
        ]);

        $anonimizar->executar($filiado, $request->string('motivo')->toString());

        return to_route('cadastros.filiados.index')->with('sucesso', "Filiado {$filiado->registro} anonimizado. Os dados pessoais foram apagados.");
    }
}
