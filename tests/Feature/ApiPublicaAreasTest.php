<?php

/*
 * Testes da API PÚBLICA DAS ÁREAS DE ATUAÇÃO (#57): só as ativas, em ordem
 * alfabética, só o nome e a imagem; o site do IBTO lê pelo navegador (CORS).
 */

use App\Models\AreaAtuacao;

test('só as áreas ativas, em ordem, só com o nome e a imagem', function () {
    AreaAtuacao::create(['nome' => 'Zzz Área de teste', 'ativa' => true]);
    AreaAtuacao::create(['nome' => 'Aaa Área de teste', 'ativa' => true]);
    AreaAtuacao::create(['nome' => 'Área desativada', 'ativa' => false]);

    $resposta = $this->getJson(route('api.publico.areas'))->assertOk();

    // As da migration inicial também entram; aqui confere as nossas.
    $nomes = collect($resposta->json('areas'))->pluck('nome');
    expect($nomes)->toContain('Zzz Área de teste')->toContain('Aaa Área de teste')->not->toContain('Área desativada')
        // Ordem alfabética (vale no SQLite e no MySQL): "Aaa" vem antes de "Zzz".
        ->and($nomes->search('Aaa Área de teste'))->toBeLessThan($nomes->search('Zzz Área de teste'))
        ->and(array_keys($resposta->json('areas.0')))->toBe(['nome', 'imagem_url']);
});

test('o site do IBTO lê pelo navegador (CORS)', function () {
    $this->getJson(route('api.publico.areas'), ['Origin' => 'https://homolog.ib-to.org'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://homolog.ib-to.org');
});
