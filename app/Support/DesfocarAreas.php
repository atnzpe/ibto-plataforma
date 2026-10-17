<?php

/*
 * DESFOCAR ÁREAS DE UMA FOTO (#90, pedido do Gleyson em 17/10/2026).
 *
 * Recebe a foto e os retângulos marcados (rosto, distintivo, placa de carro) e
 * devolve a foto com essas áreas IRRECONHECÍVEIS. Como: a área é encolhida para
 * poucos pontos (perde o detalhe de vez), ampliada de volta e ainda borrada.
 * Não dá para "desembaçar": a informação foi jogada fora, não só escondida.
 * Usa o GD, a biblioteca de imagens que já vem no PHP (a HostGator tem; não
 * precisa de Python nem de Node no servidor).
 */

namespace App\Support;

use GdImage;
use RuntimeException;

class DesfocarAreas
{
    /** No máximo quantas áreas por foto (ninguém precisa de mais; evita abuso). */
    public const MAXIMO_DE_AREAS = 20;

    /**
     * Desfoca as áreas da foto.
     *
     * @param  string  $conteudo  Os bytes da imagem (JPG, PNG ou WEBP).
     * @param  list<array{x: float, y: float, w: float, h: float}>  $areas  Retângulos em frações da foto (0 a 1).
     *                                                                      Ex.: [['x' => 0.1, 'y' => 0.2, 'w' => 0.15, 'h' => 0.2]].
     * @param  string  $formato  O formato de saída: 'jpg', 'png' ou 'webp'.
     * @return string Os bytes da foto já desfocada, no mesmo formato.
     */
    public static function aplicar(string $conteudo, array $areas, string $formato): string
    {
        $imagem = @imagecreatefromstring($conteudo);
        // Arquivo que não é imagem (ou corrompido): para aqui, com código estável.
        if (! $imagem instanceof GdImage) {
            throw new RuntimeException('DESFOQUE_IMAGEM_INVALIDA');
        }

        // Foto de celular "deitada": gira como o navegador mostra, para as áreas baterem com a tela.
        $imagem = self::endireitar($imagem, $conteudo);

        $largura = imagesx($imagem);
        $altura = imagesy($imagem);

        foreach ($areas as $area) {
            // Frações → pixels, sem sair da foto (uma área na borda é cortada no limite).
            $x = (int) max(0, min($largura - 1, round($area['x'] * $largura)));
            $y = (int) max(0, min($altura - 1, round($area['y'] * $altura)));
            // Largura e altura de pelo menos 1 pixel (uma área "vazia" ainda vira 1 ponto, nunca zero).
            $w = max(1, (int) min($largura - $x, round($area['w'] * $largura)));
            $h = max(1, (int) min($altura - $y, round($area['h'] * $altura)));

            self::borrar($imagem, $x, $y, $w, $h);
        }

        return self::gravar($imagem, $formato);
    }

    /**
     * Borra um retângulo da imagem (encolhe, amplia e passa o filtro de desfoque).
     *
     * @param  GdImage  $imagem  A foto inteira (é alterada aqui mesmo).
     * @param  int  $x  Canto esquerdo, em pixels. Ex.: 120.
     * @param  int  $y  Canto de cima, em pixels. Ex.: 80.
     * @param  int<1, max>  $w  Largura, em pixels (pelo menos 1). Ex.: 200.
     * @param  int<1, max>  $h  Altura, em pixels (pelo menos 1). Ex.: 240.
     */
    private static function borrar(GdImage $imagem, int $x, int $y, int $w, int $h): void
    {
        // Encolhe para no máximo ~8 pontos no lado menor: um rosto vira poucos borrões de cor.
        $fator = max(1, (int) ceil(min($w, $h) / 8));
        $pw = max(1, intdiv($w, $fator));
        $ph = max(1, intdiv($h, $fator));

        $pequena = imagecreatetruecolor($pw, $ph);
        imagecopyresampled($pequena, $imagem, 0, 0, $x, $y, $pw, $ph, $w, $h);

        $grande = imagecreatetruecolor($w, $h);
        imagecopyresampled($grande, $pequena, 0, 0, 0, 0, $w, $h, $pw, $ph);

        // Algumas passadas do desfoque suavizam os "quadradinhos" (fica um borrão, não um mosaico).
        for ($i = 0; $i < 6; $i++) {
            imagefilter($grande, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($imagem, $grande, $x, $y, 0, 0, $w, $h);
    }

    /**
     * Gira a foto conforme a etiqueta de orientação (EXIF) que o celular grava.
     * O navegador já mostra a foto girada; ao regravar, a etiqueta se perde,
     * então o giro vira de verdade aqui. Sem a extensão EXIF ou sem etiqueta: fica como está.
     *
     * @param  GdImage  $imagem  A foto lida.
     * @param  string  $conteudo  Os bytes originais (onde está a etiqueta).
     * @return GdImage A foto na posição certa.
     */
    private static function endireitar(GdImage $imagem, string $conteudo): GdImage
    {
        // Só JPG tem essa etiqueta; e o servidor precisa ter a extensão EXIF.
        if (! function_exists('exif_read_data') || ! str_starts_with($conteudo, "\xFF\xD8")) {
            return $imagem;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($conteudo));
        $orientacao = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // 3 = de cabeça para baixo; 6 = deitada para a direita; 8 = deitada para a esquerda.
        $girada = match ($orientacao) {
            3 => imagerotate($imagem, 180, 0),
            6 => imagerotate($imagem, -90, 0),
            8 => imagerotate($imagem, 90, 0),
            default => $imagem,
        };

        return $girada instanceof GdImage ? $girada : $imagem;
    }

    /**
     * Converte a imagem de volta em bytes, no formato pedido.
     *
     * @param  GdImage  $imagem  A foto pronta.
     * @param  string  $formato  'jpg', 'png' ou 'webp' (qualquer outro vira jpg).
     * @return string Os bytes do arquivo.
     */
    private static function gravar(GdImage $imagem, string $formato): string
    {
        ob_start();
        match ($formato) {
            'png' => imagepng($imagem, null, 6),
            'webp' => imagewebp($imagem, null, 85),
            default => imagejpeg($imagem, null, 88),
        };

        return (string) ob_get_clean();
    }
}
