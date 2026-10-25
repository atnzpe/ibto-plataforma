<?php

/*
 * PROCESSADOR DE FOTOS de pessoas (#78): foto de perfil do usuário, 3x4 do filiado
 * e foto da Rede Nacional (#11).
 *
 * Dois jeitos de gravar:
 * - gravar(): recorta um QUADRADO do centro e reduz para 400 x 400 px (perfil e 3x4);
 * - gravarInteira(): mantém a foto INTEIRA, só reduz (Rede Nacional, onde a pessoa
 *   aparece de corpo inteiro e o recorte cortava o rosto; Gleyson, 24/10/2026).
 *
 * Os dois gravam em WebP (leve no celular) na pasta PRIVADA
 * (storage/app/private). A foto nunca fica num endereço público: quem a entrega
 * é uma rota que confere a permissão (como a ficha do aluno que só o professor
 * e a secretaria abrem).
 *
 * Usa o GD, a ferramenta de imagens que já vem no PHP da HostGator. Se o GD do
 * servidor não souber gravar WebP, grava em JPEG (funciona igual, só fica um
 * pouco maior).
 */

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FotoDePerfil
{
    // Lado do quadrado final, em pixels: nítido no menu, nos inscritos e na carteirinha.
    public const LADO = 400;

    // Maior lado da foto INTEIRA, em pixels: nítida no cartão do site sem pesar no celular.
    public const MAIOR_LADO_INTEIRA = 800;

    // Disco onde as fotos ficam: o "local" do Laravel aponta para storage/app/private.
    public const DISCO = 'local';

    /**
     * Recorta, reduz e grava a foto. Devolve o caminho dentro do disco privado.
     *
     * @param  UploadedFile  $arquivo  A foto enviada pelo formulário (já validada: tipo e tamanho).
     * @param  string  $pasta  Subpasta onde gravar. Ex.: 'fotos/usuarios' ou 'fotos/filiados'.
     * @return string O caminho gravado. Ex.: 'fotos/usuarios/9f1c2b...webp'.
     */
    public static function gravar(UploadedFile $arquivo, string $pasta): string
    {
        $original = self::abrir($arquivo);

        // Largura e altura originais. Ex.: 3000 x 4000 (foto de celular em pé).
        $largura = imagesx($original);
        $altura = imagesy($original);
        // O lado do maior quadrado que cabe na foto (o menor dos dois lados).
        $ladoDoRecorte = min($largura, $altura);
        // De onde começa o recorte, para ele ficar no CENTRO da foto.
        $inicioX = intdiv($largura - $ladoDoRecorte, 2);
        $inicioY = intdiv($altura - $ladoDoRecorte, 2);

        // Tela nova, 400 x 400, que recebe o recorte já reduzido.
        $final = imagecreatetruecolor(self::LADO, self::LADO);
        // Fundo branco: PNG com transparência não vira fundo preto.
        imagefill($final, 0, 0, (int) imagecolorallocate($final, 255, 255, 255));
        // Copia o quadrado do centro, reduzindo com suavização (boa qualidade).
        imagecopyresampled($final, $original, 0, 0, $inicioX, $inicioY, self::LADO, self::LADO, $ladoDoRecorte, $ladoDoRecorte);

        return self::salvar($final, $pasta);
    }

    /**
     * Grava a foto INTEIRA (sem recorte), só reduzida, mantendo a proporção.
     * Uma foto pequena não é ampliada (ampliar só deixa borrada).
     *
     * @param  UploadedFile  $arquivo  A foto enviada (já validada). Ex.: 3000 x 4000, em pé.
     * @param  string  $pasta  Subpasta onde gravar. Ex.: 'fotos/rede-nacional'.
     * @return string O caminho gravado. Ex.: 'fotos/rede-nacional/9f1c...webp' (600 x 800).
     */
    public static function gravarInteira(UploadedFile $arquivo, string $pasta): string
    {
        $original = self::abrir($arquivo);

        // Largura e altura originais. Ex.: 3000 x 4000.
        $largura = imagesx($original);
        $altura = imagesy($original);
        // Quanto reduzir para o maior lado caber em 800 px; nunca mais que 1 (não amplia).
        // Ex.: 3000 x 4000 → 800 / 4000 = 0,2 → 600 x 800.
        $fator = min(1, self::MAIOR_LADO_INTEIRA / max($largura, $altura));
        // Tamanho final, com pelo menos 1 px de cada lado (foto muito fina não some).
        $novaLargura = max(1, (int) round($largura * $fator));
        $novaAltura = max(1, (int) round($altura * $fator));

        // Tela nova do tamanho final, com fundo branco (PNG transparente não fica preto).
        $final = imagecreatetruecolor($novaLargura, $novaAltura);
        imagefill($final, 0, 0, (int) imagecolorallocate($final, 255, 255, 255));
        // Copia a foto TODA (do ponto 0,0 até o fim), reduzindo com suavização.
        imagecopyresampled($final, $original, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

        return self::salvar($final, $pasta);
    }

    /**
     * Abre a foto enviada como imagem do GD.
     *
     * @param  UploadedFile  $arquivo  A foto enviada pelo formulário.
     * @return \GdImage A imagem aberta, pronta para recortar ou reduzir.
     *
     * @throws RuntimeException FOTO_ILEGIVEL, se o arquivo não for uma imagem que abre.
     */
    private static function abrir(UploadedFile $arquivo): \GdImage
    {
        // Abre a imagem a partir do conteúdo (o GD descobre sozinho se é JPG, PNG ou WebP).
        $original = imagecreatefromstring((string) file_get_contents($arquivo->getRealPath()));

        // Arquivo que diz ser imagem mas não abre (corrompido ou disfarçado): para aqui.
        if ($original === false) {
            throw new RuntimeException('FOTO_ILEGIVEL');
        }

        return $original;
    }

    /**
     * Grava a imagem pronta na pasta privada, com nome aleatório.
     *
     * @param  \GdImage  $final  A imagem já recortada ou reduzida.
     * @param  string  $pasta  Subpasta. Ex.: 'fotos/filiados'.
     * @return string O caminho gravado. Ex.: 'fotos/filiados/9f1c...webp'.
     */
    private static function salvar(\GdImage $final, string $pasta): string
    {
        // Grava na memória: WebP se o servidor souber, senão JPEG. Qualidade 82 (boa e leve).
        $temWebp = function_exists('imagewebp');
        ob_start();
        $temWebp ? imagewebp($final, null, 82) : imagejpeg($final, null, 82);
        $conteudo = (string) ob_get_clean();

        // Nome aleatório (ninguém adivinha o arquivo de outra pessoa) com a extensão certa.
        $caminho = trim($pasta, '/').'/'.Str::uuid()->toString().($temWebp ? '.webp' : '.jpg');
        Storage::disk(self::DISCO)->put($caminho, $conteudo);

        return $caminho;
    }

    /**
     * Apaga uma foto antiga (ao trocar ou tirar a foto). Não faz nada se não há foto.
     *
     * @param  string|null  $caminho  O caminho gravado antes. Ex.: 'fotos/usuarios/9f1c...webp', ou null.
     */
    public static function apagar(?string $caminho): void
    {
        // Sem foto antiga: nada a apagar.
        if ($caminho === null || $caminho === '') {
            return;
        }

        Storage::disk(self::DISCO)->delete($caminho);
    }
}
