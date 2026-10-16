<?php

/*
 * Testes do GERADOR DO PIX COPIA E COLA (#83): o código bate com o exemplo
 * oficial do Banco Central (mesmo CRC16), leva o valor exato e o identificador
 * da matrícula, e limpa a chave, o nome e a cidade. Dados INVENTADOS.
 */

use App\Support\PixCopiaECola;

test('reproduz o exemplo oficial do manual do BR Code (CRC 1D3D)', function () {
    // Exemplo do Manual de Padrões para Iniciação do Pix (Banco Central): chave aleatória,
    // sem valor, "Fulano de Tal", Brasília, sem identificador.
    $codigo = PixCopiaECola::gerar('123e4567-e12b-12d1-a456-426655440000', 'Fulano de Tal', 'BRASILIA', null, '***');

    expect($codigo)->toBe('00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D');
});

test('CRC16 confere com o valor de referência do padrão CCITT ("123456789" → 29B1)', function () {
    expect(PixCopiaECola::crc16('123456789'))->toBe('29B1');
});

test('leva o valor exato, o identificador da matrícula e a chave CNPJ só com números', function () {
    $codigo = PixCopiaECola::gerar('35.855.768/0001-21', 'Instituto Brasileiro de Treinamento Operacional', 'Recife', 15000, PixCopiaECola::txidDaMatricula(1234));

    expect($codigo)
        ->toContain('011435855768000121')         // chave: 14 números
        ->toContain('5406150.00')                 // valor: R$ 150,00
        ->toContain('5925Instituto Brasileiro de')  // nome cortado em 25 letras
        ->toContain('6006Recife')                 // cidade
        ->toContain('62150511IBTOMAT1234');        // identificador da matrícula

    // O CRC do fim confere com o resto do código.
    expect(substr($codigo, -4))->toBe(PixCopiaECola::crc16(substr($codigo, 0, -4)));
});

test('tira acentos, normaliza e-mail e telefone, e limpa o identificador', function () {
    $codigo = PixCopiaECola::gerar('Pix@IB-TO.org', 'Associação Ação', 'São José do Egito', 990, 'mat-12/ 3');

    expect($codigo)
        ->toContain('pix@ib-to.org')
        ->toContain('Associacao Acao')
        ->toContain('6015Sao Jose do Egi')
        ->toContain('5404'.'9.90')
        ->toContain('0506mat123');

    expect(PixCopiaECola::normalizarChave('+55 (81) 99999-0000'))->toBe('+5581999990000');
});
