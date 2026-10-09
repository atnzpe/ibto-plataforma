<?php

/*
 * Testes da conferência de CPF e CNPJ (app/Support/Documento.php).
 * Os números usados são EXEMPLOS públicos de teste, que não pertencem a ninguém
 * (regra de LGPD: nunca dados reais em teste).
 */

use App\Support\Documento;

test('aceita CPF e CNPJ válidos, com ou sem pontuação', function () {
    expect(Documento::valido('529.982.247-25'))->toBeTrue();
    expect(Documento::valido('52998224725'))->toBeTrue();
    expect(Documento::valido('11.222.333/0001-81'))->toBeTrue();
    expect(Documento::valido('11222333000181'))->toBeTrue();
});

test('recusa dígito verificador errado', function () {
    // Último dígito trocado.
    expect(Documento::valido('529.982.247-26'))->toBeFalse();
    expect(Documento::valido('11.222.333/0001-82'))->toBeFalse();
});

test('recusa números repetidos e tamanhos errados', function () {
    expect(Documento::valido('111.111.111-11'))->toBeFalse();
    expect(Documento::valido('00.000.000/0000-00'))->toBeFalse();
    expect(Documento::valido('1234'))->toBeFalse();
    expect(Documento::valido(''))->toBeFalse();
});

test('apenasDigitos tira pontos, traço e barra', function () {
    expect(Documento::apenasDigitos('11.222.333/0001-81'))->toBe('11222333000181');
});
