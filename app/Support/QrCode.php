<?php

/*
 * QR CODE (#44): transforma um endereço (ex.: o da validação pública do
 * certificado) no desenho do QR code, em SVG.
 *
 * - SVG é um desenho feito de TEXTO (formas e coordenadas), não uma foto:
 *   fica nítido em qualquer tamanho, inclusive impresso, e vai direto na
 *   página, sem arquivo de imagem.
 * - Gerado AQUI no nosso servidor, com o pacote bacon/bacon-qr-code (o mesmo
 *   que o login usa na verificação em duas etapas). Não depende de site de
 *   terceiros: o endereço do certificado não sai do IBTO.
 */

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    /**
     * Desenha o QR code de um texto.
     *
     * @param  string  $conteudo  O que o celular lê ao apontar a câmera.
     *                            Ex.: 'https://plataforma.ib-to.org/validar/IBTO-7K2M-9QXA'
     * @param  int  $tamanho  Largura e altura do desenho, em pixels. Ex.: 160
     * @return string O SVG (começa com "<svg"), pronto para ir na página.
     */
    public static function svg(string $conteudo, int $tamanho = 160): string
    {
        // O "pintor": desenha quadradinhos pretos num quadrado de $tamanho,
        // com 1 quadradinho de borda branca (o leitor precisa dessa folga).
        $pintor = new ImageRenderer(
            new RendererStyle($tamanho, 1),
            new SvgImageBackEnd,
        );

        // O "escritor" calcula o QR do texto e entrega o desenho pronto.
        $svg = (new Writer($pintor))->writeString($conteudo);

        // Tira a 1ª linha (a declaração "xml version=..."): ela só serve para
        // arquivo solto e atrapalha quando o desenho vai dentro de uma página.
        // (Atenção: escrever o sinal de "fecha PHP" mesmo dentro de um
        // comentário de linha encerra o código; por isso ele não aparece aqui.)
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}
