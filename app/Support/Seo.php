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

use App\Enums\ModalidadeCurso;
use App\Models\Curso;
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
     * @return array{titulo: string, titulo_curto: string, descricao: string, url: string, imagem: string, tipo: string, json_ld: string|null}
     */
    public static function pagina(string $titulo, ?string $descricao, ?string $imagem = null, ?array $dados = null, string $tipo = 'website'): array
    {
        return [
            // "Título - IBTO Cursos": o MESMO formato que o React usa (app.tsx), para o
            // Google e a aba do navegador mostrarem o mesmo texto antes e depois do React.
            'titulo' => $titulo.' - '.config('app.name'),
            // O título sozinho, para o texto alternativo da imagem do cartão. Ex.: 'APH Tático — Nível 1'.
            'titulo_curto' => $titulo,
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
     * A DESCRIÇÃO de um curso para o Google e o WhatsApp (15/11/2026, SEO altíssimo):
     * a chamada (ou a descrição) seguida do que a pessoa procura antes de clicar:
     * data, cidade, modalidade e preço. O resumo() corta em até 160 letras.
     *
     * @param  Curso  $curso  O curso da página.
     * @return string Ex.: 'Integração · Capacitação. 21/02/2027 · Limeira - SP · Presencial · R$ 250,00.'
     */
    public static function descricaoCurso(Curso $curso): string
    {
        // Os pedaços de informação, na ordem em que aparecem; os vazios são descartados.
        $detalhes = array_filter([
            // A data de início, no formato brasileiro (dia/mês/ano). Sem data (online): nada.
            $curso->data_inicio?->format('d/m/Y'),
            // O local (cidade). Sem local (online): nada.
            $curso->local,
            // "Presencial" ou "Online".
            $curso->modalidade->rotulo(),
            // "Gratuito" ou o preço em reais. Ex.: 'R$ 250,00'.
            $curso->gratuito() ? 'Gratuito' : 'R$ '.number_format($curso->valor_centavos / 100, 2, ',', '.'),
        ]);

        // Os detalhes numa linha só, com ponto final. Ex.: '21/02/2027 · Limeira - SP · Presencial · R$ 250,00.'
        $linhaDetalhes = implode(' · ', $detalhes).'.';

        // A frase do curso: a chamada do cartaz; sem ela, a descrição (sem HTML); sem as duas, vazia.
        $frase = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($curso->chamada ?: $curso->descricao))));

        // Espaço que sobra para a frase dentro das 157 letras: os detalhes NUNCA são cortados
        // (data e preço são o que a pessoa procura); quem encolhe é a frase.
        $espaco = 157 - mb_strlen($linhaDetalhes) - 1;
        // Frase maior que o espaço: corta e põe "..." no fim. Se couber, fica inteira.
        $frase = Str::limit($frase, max(0, $espaco - 3));
        // Garante o ponto final da frase (sem duplicar se já terminar em ".", "!" ou "?"). Frase vazia: fica vazia.
        $frase = $frase === '' || preg_match('/[.!?]$/u', $frase) ? $frase : $frase.'.';

        // Frase + detalhes. O resumo() limpa os espaços e garante o limite de tamanho.
        return self::resumo(trim($frase.' '.$linhaDetalhes));
    }

    /**
     * Os dados estruturados de um CURSO (o tipo "Course" do schema.org, que o
     * Google usa nos resultados de cursos). Desde 15/11/2026, completos: a TURMA
     * (hasCourseInstance: data, local, presencial ou online, instrutor) e a OFERTA
     * (offers: preço em reais), que o Google pede para mostrar o curso em destaque.
     *
     * @param  Curso  $curso  O curso da página.
     * @param  string  $url  O endereço da página do curso. Ex.: 'https://cursos.ib-to.org/cursos/25'.
     * @return array<string, mixed>
     */
    public static function curso(Curso $curso, string $url): array
    {
        // A TURMA (CourseInstance): como e quando o curso acontece.
        $turma = array_filter([
            '@type' => 'CourseInstance',
            // Modo: "onsite" (presencial) ou "online", nos nomes que o schema.org entende.
            'courseMode' => $curso->modalidade === ModalidadeCurso::Presencial ? 'onsite' : 'online',
            // Datas no formato internacional (ano-mês-dia). Sem data: o campo some (array_filter).
            'startDate' => $curso->data_inicio?->toDateString(),
            // Sem data de fim: termina no mesmo dia em que começa.
            'endDate' => ($curso->data_fim ?? $curso->data_inicio)?->toDateString(),
            // O lugar, só para curso presencial com local preenchido.
            'location' => $curso->modalidade === ModalidadeCurso::Presencial && $curso->local
                ? ['@type' => 'Place', 'name' => $curso->local, 'address' => $curso->local]
                : null,
            // Quem aplica, se houver um nome para mostrar.
            'instructor' => $curso->instrutorExibido() ? ['@type' => 'Person', 'name' => $curso->instrutorExibido()] : null,
            // Carga horária no formato internacional de duração. Ex.: 10 horas = 'PT10H'.
            'courseWorkload' => $curso->carga_horaria ? 'PT'.$curso->carga_horaria.'H' : null,
        ], fn ($valor) => $valor !== null);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $curso->titulo,
            'description' => self::descricaoCurso($curso),
            'url' => $url,
            // A capa do curso (endereço completo), se houver.
            'image' => $curso->imagemUrl(),
            // Quem oferece: o IBTO.
            'provider' => self::organizacao(),
            'hasCourseInstance' => [$turma],
            // A OFERTA: preço em reais (texto com ponto, como o schema.org pede). Ex.: '250.00'.
            'offers' => [[
                '@type' => 'Offer',
                // "Free" (gratuito) ou "Paid" (pago), nos nomes que o Google entende.
                'category' => $curso->gratuito() ? 'Free' : 'Paid',
                'price' => number_format($curso->valor_centavos / 100, 2, '.', ''),
                'priceCurrency' => 'BRL',
                'url' => $url,
            ]],
        ], fn ($valor) => $valor !== null);
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
