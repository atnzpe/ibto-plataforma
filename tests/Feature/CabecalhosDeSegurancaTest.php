<?php

/*
 * Testes dos CABEÇALHOS DE SEGURANÇA (#74, 18/10/2026): toda resposta sai com
 * nosniff, anti-clickjacking, política de referência e permissões bloqueadas;
 * HSTS só em HTTPS; a política de conteúdo (CSP) liga por padrão e desliga com
 * SEGURANCA_CSP=false, sem tirar os outros cabeçalhos.
 */

test('toda resposta sai com os cabeçalhos de segurança', function () {
    $resposta = $this->get('/login');

    $resposta->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    expect($resposta->headers->get('Permissions-Policy'))->toContain('camera=()');
    // A CSP: plugins proibidos, ninguém abre a plataforma dentro de outro site, VLibras liberada.
    expect($resposta->headers->get('Content-Security-Policy'))
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('https://vlibras.gov.br')
        ->toContain('https://cdn.jsdelivr.net/gh/spbgovbr-vlibras/');
    // Em http (computador local), sem HSTS.
    $resposta->assertHeaderMissing('Strict-Transport-Security');
});

test('em HTTPS, o HSTS obriga o cadeado por um ano; a API também recebe os cabeçalhos', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->getJson('/api/saude')->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('SEGURANCA_CSP=false desliga só a política de conteúdo', function () {
    config(['app.seguranca_csp' => false]);

    $this->get('/login')
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
