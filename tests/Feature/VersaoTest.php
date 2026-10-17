<?php

/*
 * Testes da VERSÃO NO AR (#75): o número vem do arquivo VERSION e aparece na rota
 * de saúde e nas telas (rodapé). Ajuda o suporte a saber o que está no ar.
 */

use App\Models\User;

test('a rota de saúde mostra a versão do arquivo VERSION', function () {
    $versao = trim((string) file_get_contents(base_path('VERSION')));

    $this->getJson(route('api.saude'))->assertOk()->assertJsonPath('versao.numero', $versao);
});

test('as telas recebem a versão para o rodapé', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))
        ->assertInertia(fn ($tela) => $tela->where('versao.numero', config('app.versao')));
});

test('o banco aparece com a versão e a última migration aplicada', function () {
    $this->getJson(route('api.saude'))
        // A última migration tem o formato do nome do arquivo (data_hora_descricao), seja ela qual for.
        ->assertJsonPath('versao.banco.migracao', fn ($migracao) => is_string($migracao) && preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_\w+$/', $migracao) === 1)
        ->assertJsonPath('versao.banco.versao', fn ($versao) => is_string($versao) && $versao !== '');
});

test('o VERSION segue o versionamento semântico (MAIOR.MENOR.CORREÇÃO)', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toMatch('/^\d+\.\d+\.\d+$/');
});
