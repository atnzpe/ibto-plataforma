<?php

/*
 * CABEÇALHOS DE SEGURANÇA (18/10/2026, #74 "blindagem nível militar"): avisos
 * que vão em TODA resposta e fazem o navegador proteger quem usa a plataforma.
 *
 * Sempre ligados:
 * - X-Content-Type-Options: nosniff → o navegador não "adivinha" o tipo de um
 *   arquivo (um PDF enviado não vira script);
 * - X-Frame-Options e frame-ancestors → ninguém abre a plataforma dentro de
 *   OUTRO site (golpe do "clique escondido", o clickjacking);
 * - Referrer-Policy → os sites externos não recebem o endereço completo de onde a pessoa veio;
 * - Permissions-Policy → câmera, microfone, localização e pagamento do navegador
 *   ficam BLOQUEADOS (a plataforma não usa);
 * - Cross-Origin-Opener-Policy → uma aba aberta por outro site não controla esta;
 * - Strict-Transport-Security (HSTS), só em HTTPS → por 1 ano, o navegador só
 *   abre a plataforma com cadeado (ninguém "rebaixa" para http no meio do caminho).
 *
 * Política de conteúdo (CSP), ligada por padrão e desligável no .env
 * (SEGURANCA_CSP=false) se algo quebrar na homologação:
 * - plugins antigos (object) proibidos; endereço-base e destino dos formulários
 *   presos à própria plataforma; http vira https;
 * - scripts só da plataforma e da VLibras; vídeos em quadro só do YouTube e da VLibras.
 * 'unsafe-inline'/'unsafe-eval' ficam por enquanto: o tema e o Inertia usam um
 * script na própria página, e o widget da VLibras (animação 3D) precisa do eval.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CabecalhosDeSeguranca
{
    /** Endereços da VLibras (widget de Libras do governo). */
    private const VLIBRAS = 'https://vlibras.gov.br https://*.vlibras.gov.br';

    /**
     * Acrescenta os cabeçalhos na resposta.
     *
     * @param  Request  $request  O pedido.
     * @param  Closure(Request): Response  $next  O resto da plataforma (gera a resposta).
     * @return Response A resposta, com os cabeçalhos.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);
        $cabecalhos = $resposta->headers;

        $cabecalhos->set('X-Content-Type-Options', 'nosniff');
        $cabecalhos->set('X-Frame-Options', 'SAMEORIGIN');
        $cabecalhos->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $cabecalhos->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $cabecalhos->set('Cross-Origin-Opener-Policy', 'same-origin');

        // HSTS só faz sentido (e só é aceito) em HTTPS. No computador local (http), não vai.
        if ($request->isSecure()) {
            $cabecalhos->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // A política de conteúdo: ligada por padrão; SEGURANCA_CSP=false no .env desliga só ela.
        if (config('app.seguranca_csp', true)) {
            $cabecalhos->set('Content-Security-Policy', self::politicaDeConteudo());
        }

        return $resposta;
    }

    /**
     * A política de conteúdo (CSP), uma regra por tipo de recurso.
     *
     * @return string Ex.: "default-src 'self'; object-src 'none'; ..."
     */
    public static function politicaDeConteudo(): string
    {
        $vlibras = self::VLIBRAS;

        return implode('; ', [
            // Padrão: só da própria plataforma.
            "default-src 'self'",
            // Scripts: os da plataforma e o widget da VLibras (ver o comentário do arquivo).
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$vlibras}",
            // Estilos e fontes: os da plataforma e as fontes servidas por https.
            "style-src 'self' 'unsafe-inline' https:",
            "font-src 'self' data: https:",
            // Imagens e mídia: a plataforma, imagens embutidas (QR do Pix) e https.
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            // Conexões: a própria plataforma e a VLibras (a tradução para Libras).
            "connect-src 'self' {$vlibras}",
            // Quadros: vídeos do YouTube (sem cookies de propaganda) e a VLibras.
            "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com {$vlibras}",
            // Trabalhadores em segundo plano (o PWA offline e a animação da VLibras).
            "worker-src 'self' blob:",
            // Plugins antigos (Flash, Java): proibidos.
            "object-src 'none'",
            // Ninguém troca o endereço-base das páginas nem manda formulário para fora.
            "base-uri 'self'",
            "form-action 'self'",
            // Ninguém abre a plataforma dentro de outro site.
            "frame-ancestors 'self'",
            // Qualquer link http vira https.
            'upgrade-insecure-requests',
        ]);
    }
}
