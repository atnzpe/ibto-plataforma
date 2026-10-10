<?php

/*
 * VÍDEO das aulas (#39, mesma lógica do Dojo Manager): transforma o link que
 * a pessoa cola no formato que o player usa.
 * - YouTube (watch?v=, youtu.be/, /embed/, /shorts/) → o código de 11 letras,
 *   para o player incorporado do YouTube (sem cookies: youtube-nocookie);
 * - link https direto de arquivo .mp4/.webm → o player do próprio navegador;
 * - qualquer outra coisa → null (o formulário recusa).
 */

namespace App\Support;

class Video
{
    /**
     * Lê o link e diz como tocar.
     *
     * @param  string|null  $url  O link colado. Ex.: 'https://youtu.be/dQw4w9WgXcQ?t=10'
     * @return array{tipo: 'youtube', id: string, embed: string}|array{tipo: 'arquivo', url: string}|null
     *                                                                                                    Ex.: ['tipo' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ']
     */
    public static function paraTocar(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $id = self::idDoYoutube($url);
        if ($id !== null) {
            return ['tipo' => 'youtube', 'id' => $id, 'embed' => 'https://www.youtube-nocookie.com/embed/'.$id];
        }

        // Arquivo de vídeo direto: só https e só .mp4/.webm (o navegador toca sozinho).
        $caminho = (string) parse_url($url, PHP_URL_PATH);
        if (str_starts_with($url, 'https://') && preg_match('/\.(mp4|webm)$/i', $caminho) === 1) {
            return ['tipo' => 'arquivo', 'url' => $url];
        }

        return null;
    }

    /**
     * O código do vídeo do YouTube (11 letras, números, "-" ou "_"), ou null.
     *
     * @param  string  $url  Ex.: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1s'
     * @return string|null Ex.: 'dQw4w9WgXcQ'
     */
    public static function idDoYoutube(string $url): ?string
    {
        $partes = parse_url($url);
        $host = strtolower((string) ($partes['host'] ?? ''));
        $caminho = (string) ($partes['path'] ?? '');

        // youtu.be/CODIGO
        if ($host === 'youtu.be') {
            $id = ltrim($caminho, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            // youtube.com/watch?v=CODIGO
            parse_str((string) ($partes['query'] ?? ''), $parametros);
            $id = is_string($parametros['v'] ?? null) ? $parametros['v'] : null;
            // youtube.com/embed/CODIGO ou /shorts/CODIGO
            if ($id === null && preg_match('#^/(embed|shorts|live)/([^/?]+)#', $caminho, $achado) === 1) {
                $id = $achado[2];
            }
        } else {
            return null;
        }

        // Só aceita o formato exato do código (protege contra links montados de propósito).
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }
}
