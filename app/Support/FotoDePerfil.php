<?php

/*
 * PROCESSADOR DE FOTOS de pessoas (#78): foto de perfil do usuário e 3x4 do filiado.
 *
 * Recebe a foto enviada (JPG, PNG ou WebP), recorta um QUADRADO do centro,
 * reduz para 400 x 400 px e grava em WebP (leve no celular) na pasta PRIVADA
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
        // Abre a imagem a partir do conteúdo (o GD descobre sozinho se é JPG, PNG ou WebP).
        $original = imagecreatefromstring((string) file_get_contents($arquivo->getRealPath()));

        // Arquivo que diz ser imagem mas não abre (corrompido ou disfarçado): para aqui.
        if ($original === false) {
            throw new RuntimeException('FOTO_ILEGIVEL');
        }

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
