<?php

/*
 * ENDEREÇOS NO SITE PÚBLICO (#61, bloco B4).
 *
 * O blog e os eventos são mostrados no site (ib-to.org), e as páginas
 * públicas da plataforma levam a pessoa para lá. Este ajudante monta o
 * endereço do site; sem site configurado (ex.: no computador do
 * desenvolvedor), devolve null e a plataforma continua mostrando a própria
 * página (plano B).
 */

namespace App\Support;

class SitePublico
{
    /**
     * O endereço completo de uma página do site, ou null sem site configurado.
     *
     * @param  string  $caminho  O caminho no site, começando com "/". Ex.: '/blog/formacao-no-estande'
     * @param  array<string, string>  $parametros  O que vai depois do "?". Ex.: ['categoria' => 'evento']
     * @return string|null Ex.: 'https://homolog.ib-to.org/blog?categoria=evento'; null sem site.
     */
    public static function endereco(string $caminho, array $parametros = []): ?string
    {
        // O site do mesmo ambiente (config/app.php, chave site_url). Vazio = sem site.
        $site = (string) config('app.site_url');
        if ($site === '') {
            return null;
        }

        // Tira os parâmetros vazios (ex.: categoria '') para o endereço ficar limpo.
        $parametros = array_filter($parametros, fn (string $valor): bool => $valor !== '');
        // http_build_query monta "a=1&b=2" já com os caracteres especiais protegidos.
        $consulta = $parametros === [] ? '' : '?'.http_build_query($parametros);

        return $site.$caminho.$consulta;
    }
}
