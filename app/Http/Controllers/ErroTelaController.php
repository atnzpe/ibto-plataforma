<?php

/*
 * Recebe os ERROS que acontecem NA TELA (no navegador da pessoa) e grava no log
 * do servidor (#58, bloco B; regra do Gleyson: nenhum erro em silêncio).
 *
 * Sem isto, quando uma tela React quebra no celular de um aluno, o erro fica só
 * no console do navegador dele e nós nunca ficamos sabendo.
 *
 * LGPD: guarda só a mensagem do erro, o caminho da página (sem a busca "?...")
 * e o número do usuário, tudo cortado num tamanho máximo. Nada de dados de
 * formulário. Código estável no log: ERRO_TELA.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ErroTelaController extends Controller
{
    /**
     * @param  Request  $request  JSON com "mensagem", "pagina" e (opcional) "origem".
     *                            Ex.: {"mensagem": "x is undefined", "pagina": "/cadastros/filiados", "origem": "tela"}
     * @return Response 204 (recebido, sem conteúdo) ou 422 (dados inválidos).
     */
    public function __invoke(Request $request): Response
    {
        $dados = $request->validate([
            'mensagem' => ['required', 'string', 'max:1000'],
            'pagina' => ['required', 'string', 'max:500'],
            // De onde veio: "tela" (rede de proteção do React), "janela" (erro solto) ou "promessa".
            'origem' => ['nullable', 'string', 'in:tela,janela,promessa'],
        ]);

        Log::error('ERRO_TELA', [
            // Cortado em 300 letras: suficiente para entender o erro, sem encher o log.
            'mensagem' => Str::limit($dados['mensagem'], 300),
            // Só o caminho (ex.: "/cadastros/filiados"), sem "?..." (que pode ter dados).
            'pagina' => Str::before($dados['pagina'], '?'),
            'origem' => $dados['origem'] ?? 'tela',
            // Número do usuário (ou null para visitante); nunca e-mail ou nome.
            'usuario_id' => Auth::id(),
        ]);

        return response()->noContent();
    }
}
