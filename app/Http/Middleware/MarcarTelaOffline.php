<?php

/*
 * MARCA "PODE GUARDAR PARA USAR SEM INTERNET" (#74, offline).
 *
 * Nas telas públicas (catálogo, curso, trilha, blog), quando quem abriu é um
 * VISITANTE (sem login), a resposta ganha o cabeçalho X-Ibto-Offline: publico.
 * O service worker (public/sw.js) só guarda no aparelho as telas com essa
 * marca. Com login, a tela leva o nome e o e-mail da pessoa: não é marcada e
 * nunca fica guardada (LGPD, celular emprestado).
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarcarTelaOffline
{
    /**
     * Marca a resposta, se for de um visitante e der certo.
     *
     * @param  Request  $request  O pedido que chegou.
     * @param  Closure(Request): Response  $next  O resto do caminho.
     * @return Response A resposta (marcada ou não).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        // Só visitante, só leitura (GET) e só resposta de sucesso (200). Senão, sem marca.
        if ($request->user() === null && $request->isMethod('GET') && $resposta->getStatusCode() === 200) {
            $resposta->headers->set('X-Ibto-Offline', 'publico');
        }

        return $resposta;
    }
}
