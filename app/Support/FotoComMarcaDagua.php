<?php

/*
 * FOTOS À VENDA: GUARDA O ORIGINAL E GERA A PRÉVIA COM MARCA D'ÁGUA (#90).
 *
 * - ORIGINAL: o arquivo do fotógrafo, como veio, na pasta PRIVADA. Só sai depois
 *   que o pedido é pago, por um link que vence.
 * - PRÉVIA: reduzida (lado maior de 1000 px, em WebP), com o emblema do IBTO
 *   repetido em diagonal, semitransparente, e uma faixa embaixo com
 *   "IBTO · <fotógrafo> · PRÉVIA". É a que aparece na vitrine: dá para escolher,
 *   mas não serve para usar sem comprar.
 *
 * Usa o GD (já vem no PHP da HostGator). Se o GD não souber gravar WebP, a prévia
 * vai em JPEG.
 */

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FotoComMarcaDagua
{
    // Lado maior da prévia, em pixels: dá para ver a foto e escolher, sem servir para imprimir.
    public const LADO_PREVIA = 1000;

    // Disco privado (storage/app/private): nada aqui tem endereço público.
    public const DISCO = 'local';

    /**
     * Guarda o original e gera a prévia com a marca d'água.
     *
     * @param  UploadedFile  $arquivo  A foto enviada (já validada: JPG, PNG ou WebP).
     * @param  string  $fotografo  Nome do fotógrafo, para a faixa. Ex.: 'Fulano Fotos'.
     * @return array{original: string, previa: string} Os caminhos gravados no disco privado.
     */
    public static function gravar(UploadedFile $arquivo, string $fotografo): array
    {
        $conteudo = (string) file_get_contents($arquivo->getRealPath());
        $imagem = imagecreatefromstring($conteudo);

        // Diz que é imagem mas não abre (corrompida ou disfarçada): para aqui, nada é gravado.
        if ($imagem === false) {
            throw new RuntimeException('FOTO_ILEGIVEL');
        }

        $nome = Str::uuid()->toString();
        $disco = Storage::disk(self::DISCO);

        // 1) O original, exatamente como veio (com a extensão do tipo real).
        $original = "fotos-venda/originais/{$nome}.".self::extensao($arquivo);
        $disco->put($original, $conteudo);

        // 2) A prévia: reduz, aplica o emblema em diagonal e a faixa com o nome.
        $previaImagem = self::reduzir($imagem);
        self::aplicarEmblema($previaImagem);
        self::aplicarFaixa($previaImagem, $fotografo);

        $temWebp = function_exists('imagewebp');
        ob_start();
        $temWebp ? imagewebp($previaImagem, null, 70) : imagejpeg($previaImagem, null, 70);
        $previa = "fotos-venda/previas/{$nome}.".($temWebp ? 'webp' : 'jpg');
        $disco->put($previa, (string) ob_get_clean());

        return ['original' => $original, 'previa' => $previa];
    }

    /**
     * Apaga o original e a prévia de uma foto (expiração ou foto tirada do álbum).
     *
     * @param  string  $original  Ex.: 'fotos-venda/originais/9f1c....jpg'.
     * @param  string  $previa  Ex.: 'fotos-venda/previas/9f1c....webp'.
     */
    public static function apagar(string $original, string $previa): void
    {
        Storage::disk(self::DISCO)->delete([$original, $previa]);
    }

    /**
     * Reduz a imagem para o lado maior caber em LADO_PREVIA (nunca aumenta).
     */
    private static function reduzir(GdImage $imagem): GdImage
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $escala = min(1, self::LADO_PREVIA / max($largura, $altura));
        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));

        $nova = imagecreatetruecolor($novaLargura, $novaAltura);
        imagecopyresampled($nova, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

        return $nova;
    }

    /**
     * O emblema do IBTO repetido em diagonal, bem transparente, sobre a foto inteira.
     * Sem o arquivo do emblema (instalação diferente): pula, a faixa ainda marca a foto.
     */
    private static function aplicarEmblema(GdImage $foto): void
    {
        $caminho = public_path('images/ibto-emblema.png');
        if (! is_file($caminho)) {
            return;
        }
        $emblema = imagecreatefrompng($caminho);
        if ($emblema === false) {
            return;
        }

        // Tamanho de cada emblema: um quarto da largura da foto.
        $lado = max(40, intdiv(imagesx($foto), 4));
        $pequeno = imagecreatetruecolor($lado, $lado);
        imagealphablending($pequeno, false);
        imagesavealpha($pequeno, true);
        imagefill($pequeno, 0, 0, (int) imagecolorallocatealpha($pequeno, 0, 0, 0, 127));
        imagecopyresampled($pequeno, $emblema, 0, 0, 0, 0, $lado, $lado, imagesx($emblema), imagesy($emblema));

        // A cor do FUNDO do arquivo do emblema, lida no canto (o arquivo não tem fundo transparente).
        $fundo = imagecolorat($pequeno, 0, 0) & 0xFFFFFF;

        // Deixa o emblema bem transparente e o fundo invisível.
        for ($x = 0; $x < $lado; $x++) {
            for ($y = 0; $y < $lado; $y++) {
                $cor = imagecolorat($pequeno, $x, $y);
                $alfa = ($cor >> 24) & 0x7F;
                // Ponto da cor do fundo (ou já transparente): some de vez (alfa 127).
                if (self::parecido($cor & 0xFFFFFF, $fundo) || $alfa >= 120) {
                    imagesetpixel($pequeno, $x, $y, 127 << 24);

                    continue;
                }
                // Ponto do desenho: transparência de no mínimo 95 (de 127), bem suave sobre a foto.
                if ($alfa < 95) {
                    imagesetpixel($pequeno, $x, $y, ($cor & 0xFFFFFF) | (95 << 24));
                }
            }
        }

        // Espalha em diagonal: linhas deslocadas meia posição, como um papel de parede.
        imagealphablending($foto, true);
        for ($linha = 0, $y = 0; $y < imagesy($foto); $linha++, $y += $lado) {
            $deslocamento = ($linha % 2) * intdiv($lado, 2);
            for ($x = -$deslocamento; $x < imagesx($foto); $x += $lado + intdiv($lado, 2)) {
                imagecopy($foto, $pequeno, $x, $y, 0, 0, $lado, $lado);
            }
        }
    }

    /**
     * Duas cores são quase iguais? (diferença pequena em vermelho, verde e azul)
     *
     * @param  int  $a  Cor no formato 0xRRGGBB. Ex.: 0x000000 (preto).
     * @param  int  $b  Outra cor. Ex.: 0x050505.
     * @return bool true = parecidas o bastante para contar como o fundo.
     */
    private static function parecido(int $a, int $b): bool
    {
        return abs((($a >> 16) & 0xFF) - (($b >> 16) & 0xFF)) < 24
            && abs((($a >> 8) & 0xFF) - (($b >> 8) & 0xFF)) < 24
            && abs(($a & 0xFF) - ($b & 0xFF)) < 24;
    }

    /**
     * A faixa escura embaixo, com "IBTO · <fotógrafo> · PRÉVIA" (fonte embutida do GD).
     */
    private static function aplicarFaixa(GdImage $foto, string $fotografo): void
    {
        $altura = 28;
        $topo = imagesy($foto) - $altura;
        imagealphablending($foto, true);
        // Faixa preta semitransparente (alfa 50 de 127).
        imagefilledrectangle($foto, 0, $topo, imagesx($foto), imagesy($foto), (int) imagecolorallocatealpha($foto, 0, 0, 0, 50));
        // A fonte embutida não tem acento: o texto vai sem acentos.
        $texto = 'IBTO - '.Str::ascii($fotografo).' - PREVIA (compre para baixar sem marca)';
        imagestring($foto, 5, 10, $topo + 6, $texto, (int) imagecolorallocate($foto, 243, 241, 236));
    }

    /**
     * A extensão pelo tipo REAL do arquivo (não pelo nome que veio).
     */
    private static function extensao(UploadedFile $arquivo): string
    {
        return match ($arquivo->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
