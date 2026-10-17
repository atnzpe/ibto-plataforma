<?php

/*
 * IDENTIFICADOR DO PEDIDO (#74): cada visita (cada pedido ao servidor) ganha um
 * código único, que vai em TODAS as linhas de log daquele pedido e volta na
 * resposta (cabeçalho X-Request-Id). Assim, quando alguém disser "deu erro às
 * 10h", o suporte acha no log todas as linhas daquele mesmo pedido.
 * Como o número do cupom fiscal no PDV: liga o item, o pagamento e a troca.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdentificadorDoPedido
{
    /** O nome do cabeçalho (o mesmo que servidores e proxies costumam usar). */
    public const CABECALHO = 'X-Request-Id';

    /**
     * Dá o código ao pedido e o devolve na resposta.
     *
     * @param  Request  $request  O pedido que chegou.
     * @param  Closure(Request): Response  $next  O resto do caminho (as outras etapas e o controller).
     * @return Response A resposta, com o cabeçalho X-Request-Id.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Aproveita o código que veio de fora (ex.: de um proxy) só se for "limpo":
        // letras, números e hífen, de 8 a 64 caracteres. Senão (ou se não veio), cria um novo.
        $recebido = (string) $request->headers->get(self::CABECALHO, '');
        $codigo = preg_match('/^[A-Za-z0-9-]{8,64}$/', $recebido) === 1 ? $recebido : (string) Str::uuid();

        // Context: o Laravel junta este valor a TODA linha de log deste pedido, sozinho.
        Context::add('pedido_id', $codigo);

        $resposta = $next($request);
        $resposta->headers->set(self::CABECALHO, $codigo);

        return $resposta;
    }
}
