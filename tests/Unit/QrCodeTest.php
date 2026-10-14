<?php

/*
 * Testes do desenho do QR code (app/Support/QrCode.php), usado no certificado (#44).
 * Ler o QR de volta exigiria um "leitor" (outro pacote); aqui conferimos o
 * que importa para a página: é um SVG limpo, do tamanho pedido, e muda
 * quando o endereço muda.
 */

use App\Support\QrCode;

test('devolve um SVG limpo, sem a linha xml do começo, no tamanho pedido', function () {
    $svg = QrCode::svg('https://exemplo.test/validar/IBTO-AAAA-BBBB', 200);

    expect($svg)->toStartWith('<svg')
        ->and($svg)->not->toContain('<?xml')
        ->and($svg)->toContain('width="200"')
        ->and($svg)->toContain('height="200"');
});

test('o mesmo endereço dá o mesmo desenho; outro endereço, outro desenho', function () {
    $a = QrCode::svg('https://exemplo.test/validar/IBTO-AAAA-BBBB');

    expect(QrCode::svg('https://exemplo.test/validar/IBTO-AAAA-BBBB'))->toBe($a)
        ->and(QrCode::svg('https://exemplo.test/validar/IBTO-CCCC-DDDD'))->not->toBe($a);
});
