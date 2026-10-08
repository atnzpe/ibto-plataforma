<?php

/*
 * Testes da criação de filiado (#37, bloco F1): conta criada junto, número de
 * registro gerado, CPF criptografado e sem repetição (pelo cpf_hash).
 * Todos os dados são INVENTADOS (regra de LGPD: nunca dados reais em teste);
 * os CPFs são exemplos públicos de teste.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\AreaAtuacao;
use App\Models\Equipe;
use App\Models\Filiado;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Dados de um filiado inventado, para cada teste trocar só o que precisa.
 *
 * @param  array<string, mixed>  $troca
 * @return array<string, mixed>
 */
function filiadoInventado(array $troca = []): array
{
    return [
        'nome' => 'Filiado Teste',
        'email' => 'Filiado@IBTO.test',
        'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20',
        'telefone' => '81999990000',
        'municipio' => 'Recife',
        'uf' => 'pe',
        'equipe_id' => Equipe::where('nome', 'Vulcan')->value('id'),
        'areas' => [AreaAtuacao::where('nome', 'Krav Maga')->value('id')],
        ...$troca,
    ];
}

test('o número de registro segue o formato BR + ano + 4 números + UF', function () {
    expect(Filiado::montarRegistro(2026, 7, 'pe'))->toBe('BR20260007PE');
    expect(Filiado::montarRegistro(2027, 123, 'SP'))->toBe('BR20270123SP');
});

test('cria o filiado e a conta juntos, com o primeiro registro do ano', function () {
    $filiado = app(CriarFiliado::class)->executar(filiadoInventado());

    $ano = now()->format('Y');
    expect($filiado->registro)->toBe("BR{$ano}0001PE");
    expect($filiado->usuario->email)->toBe('filiado@ibto.test');
    expect($filiado->usuario->name)->toBe('Filiado Teste');
    expect($filiado->areas()->pluck('nome')->all())->toBe(['Krav Maga']);
});

test('a sequência do registro é do ano inteiro, não por estado', function () {
    app(CriarFiliado::class)->executar(filiadoInventado());
    $segundo = app(CriarFiliado::class)->executar(filiadoInventado([
        'email' => 'outro@ibto.test', 'cpf' => '111.444.777-35', 'uf' => 'SP',
    ]));

    expect($segundo->registro)->toBe('BR'.now()->format('Y').'0002SP');
});

test('LGPD: CPF, nascimento e telefone ficam criptografados no banco', function () {
    app(CriarFiliado::class)->executar(filiadoInventado());

    // Lê a linha direto do banco, como um invasor veria.
    $linha = DB::table('filiados')->first();

    expect($linha->cpf)->not->toContain('52998224725');
    expect($linha->nascimento)->not->toContain('1990-05-20');
    expect($linha->telefone)->not->toContain('81999990000');
    // E o hash não é o CPF.
    expect($linha->cpf_hash)->not->toContain('52998224725')->toHaveLength(64);
});

test('o banco barra CPF repetido, mesmo escrito de outro jeito', function () {
    app(CriarFiliado::class)->executar(filiadoInventado());

    // Mesmo CPF, sem pontuação: o hash é igual e o banco recusa.
    app(CriarFiliado::class)->executar(filiadoInventado(['email' => 'outro@ibto.test', 'cpf' => '52998224725']));
})->throws(QueryException::class);

test('se o filiado falhar, a conta também não fica (tudo ou nada)', function () {
    app(CriarFiliado::class)->executar(filiadoInventado());

    try {
        app(CriarFiliado::class)->executar(filiadoInventado(['email' => 'sobra@ibto.test', 'cpf' => '52998224725']));
    } catch (QueryException) {
        // Esperado: CPF repetido.
    }

    // A conta "sobra@" não pode ter ficado sem filiado.
    expect(DB::table('users')->where('email', 'sobra@ibto.test')->exists())->toBeFalse();
});
