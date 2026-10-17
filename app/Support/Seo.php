<?php

/*
 * SEO DAS TELAS PÚBLICAS (#74): as etiquetas que o Google, o WhatsApp e as IAs
 * leem para entender a página (título, descrição, imagem do cartão, endereço
 * oficial e os "dados estruturados" JSON-LD, que dizem "isto é um curso").
 *
 * Por que no SERVIDOR (e não no React)? A HostGator não tem Node, então a
 * plataforma não monta o HTML do React no servidor. O robô do WhatsApp não roda
 * JavaScript: se a etiqueta só aparecesse depois do React, o link do curso iria
 * sem foto nem descrição. Aqui ela já sai pronta no HTML (app.blade.php).
 *
 * Uso no controller: Inertia::render(...)->withViewData(['seo' => Seo::pagina(...)]).
 * Lembrete: enquanto APP_INDEXAVEL=false, o Google não indexa (noindex), mas o
 * cartão do WhatsApp já funciona.
 */

namespace App\Support;

use Illuminate\Support\Str;

class Seo
{
    /**
     * Monta as etiquetas de uma página pública.
     *
     * @param  string  $titulo  O título. Ex.: 'APH Tático — Nível 1'.
     * @param  string|null  $descricao  O resumo (vira texto simples, até 160 letras). Ex.: a descrição do curso.
     * @param  string|null  $imagem  Endereço completo da imagem do cartão; null = o emblema do IBTO.
     * @param  array<string, mixed>|null  $dados  Os dados estruturados (JSON-LD), ou null. Ex.: Seo::curso(...).
     * @param  string  $tipo  O tipo do Open Graph: 'website' (padrão) ou 'article'.
     * @return array{titulo: string, descricao: string, url: string, imagem: string, tipo: string, json_ld: string|null}
     */
    public static function pagina(string $titulo, ?string $descricao, ?string $imagem = null, ?array $dados = null, string $tipo = 'website'): array
    {
        return [
            'titulo' => $titulo.' · '.config('app.name'),
            'descricao' => self::resumo($descricao),
            // O endereço oficial (canônico): sem os filtros da busca (?area=...), para o
            // Google não achar que cada filtro é uma página diferente.
            'url' => url()->current(),
            'imagem' => $imagem ?? asset('images/ibto-emblema.png'),
            'tipo' => $tipo,
            // JSON_HEX_TAG troca "<" e ">" por códigos: um título com "</script>" não
            // consegue fechar a etiqueta e enfiar código na página.
            // Se a conversão falhar (json_encode devolve false), fica sem os dados (null).
            'json_ld' => $dados === null ? null : (json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?: null),
        ];
    }

    /**
     * Os dados estruturados de um CURSO (o tipo "Course" do schema.org, que o
     * Google usa nos resultados de cursos).
     *
     * @param  string  $nome  O título do curso.
     * @param  string|null  $descricao  A descrição.
     * @param  string  $url  O endereço da página do curso.
     * @return array<string, mixed>
     */
    public static function curso(string $nome, ?string $descricao, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $nome,
            'description' => self::resumo($descricao),
            'url' => $url,
            // Quem oferece: o IBTO.
            'provider' => self::organizacao(),
        ];
    }

    /**
     * O IBTO como organização (schema.org "Organization").
     *
     * @return array<string, string>
     */
    public static function organizacao(): array
    {
        return [
            '@type' => 'Organization',
            'name' => 'Instituto Brasileiro de Treinamento Operacional',
            'alternateName' => 'IBTO',
            'url' => 'https://ib-to.org',
        ];
    }

    /**
     * Texto simples e curto para a descrição: tira etiquetas HTML e espaços
     * repetidos e corta em 160 letras (o tamanho que o Google mostra).
     *
     * @param  string|null  $texto  Ex.: 'Curso   prático de <b>APH</b>...'.
     * @return string Ex.: 'Curso prático de APH...'. Vazio vira a frase padrão do IBTO.
     */
    private static function resumo(?string $texto): string
    {
        $limpo = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $texto)));

        return $limpo === ''
            ? 'Cursos de treinamento operacional do Instituto Brasileiro de Treinamento Operacional (IBTO).'
            : Str::limit($limpo, 157);
    }
}
