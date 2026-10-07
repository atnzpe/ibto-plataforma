<?php

/*
 * Testes do cadastro de áreas de atuação (#37): as 15 áreas do site já vêm no
 * banco, o nome não se repete e o filtro "ativas" esconde as desativadas.
 */

use App\Models\AreaAtuacao;
use Illuminate\Database\QueryException;

test('a migration já traz as 15 áreas do site, todas ativas', function () {
    // Conta as áreas que a migration colocou no banco de teste.
    expect(AreaAtuacao::count())->toBe(15);
    // Nenhuma nasce desativada.
    expect(AreaAtuacao::where('ativa', false)->count())->toBe(0);
    // Confere duas delas pelo nome (uma com acento, para pegar erro de codificação).
    expect(AreaAtuacao::where('nome', 'Krav Maga')->exists())->toBeTrue();
    expect(AreaAtuacao::where('nome', 'Operações com Drones')->exists())->toBeTrue();
});

test('o banco recusa duas áreas com o mesmo nome', function () {
    // "Krav Maga" já existe (veio da migration): criar de novo tem que falhar.
    AreaAtuacao::create(['nome' => 'Krav Maga']);
})->throws(QueryException::class);

test('o filtro "ativas" esconde as desativadas e põe em ordem alfabética', function () {
    // Uma área nova ativa e uma desativada, além das 15 do site.
    AreaAtuacao::factory()->create(['nome' => 'AAA área nova']);
    AreaAtuacao::factory()->desativada()->create(['nome' => 'Área desativada']);

    $nomes = AreaAtuacao::ativas()->pluck('nome');

    // 15 do site + 1 nova; a desativada fica de fora.
    expect($nomes)->toHaveCount(16);
    expect($nomes)->not->toContain('Área desativada');
    // Ordem alfabética: "AAA área nova" vem antes de todas. Em maiúsculas de propósito:
    // o SQLite dos testes põe maiúscula antes de minúscula, e o MySQL não diferencia;
    // "AAA" fica em primeiro nos dois.
    expect($nomes->first())->toBe('AAA área nova');
});
