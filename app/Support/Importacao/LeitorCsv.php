<?php

/*
 * Lê um arquivo CSV (planilha salva como "valores separados") para a
 * importação de filiados (#37, F4).
 *
 * Aceita o que o Excel, o Google Planilhas e o Canva costumam gerar:
 * separador ";" ou ",", e o "BOM" (3 bytes invisíveis que o Excel põe no
 * começo de arquivos UTF-8). A primeira linha é o cabeçalho (nomes das colunas).
 */

namespace App\Support\Importacao;

class LeitorCsv
{
    /**
     * Devolve "colunas" (os nomes do cabeçalho) e "linhas" (cada linha como
     * [nome da coluna => valor]).
     *
     * @param  string  $conteudo  O texto inteiro do arquivo.
     * @return array{colunas: list<string>, linhas: list<array<string, string>>}
     */
    public static function ler(string $conteudo): array
    {
        // Tira o BOM do começo, se houver (senão o nome da 1ª coluna vem com lixo invisível).
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;

        // O Excel em português salva CSV com a acentuação ANTIGA do Windows
        // (Windows-1252), e não em UTF-8. Sem converter, "Município" vira lixo e
        // a importação quebra. Se o texto não é UTF-8 válido, converte.
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        // Quebra em linhas (aceita fim de linha do Windows e do Linux) e tira as vazias.
        $brutas = array_values(array_filter(
            preg_split('/\r\n|\n|\r/', $conteudo) ?: [],
            fn (string $linha) => trim($linha) !== '',
        ));

        if ($brutas === []) {
            return ['colunas' => [], 'linhas' => []];
        }

        // O separador é o que mais aparece no cabeçalho: ";" (Excel em português) ou ",".
        $separador = substr_count($brutas[0], ';') >= substr_count($brutas[0], ',') ? ';' : ',';

        // str_getcsv entende aspas (ex.: "Recife, PE" não quebra na vírgula).
        $colunas = array_map(
            fn (?string $nome) => trim((string) $nome),
            str_getcsv($brutas[0], $separador, '"', ''),
        );

        $linhas = [];
        foreach (array_slice($brutas, 1) as $bruta) {
            $valores = str_getcsv($bruta, $separador, '"', '');
            $linha = [];
            // Liga cada valor ao nome da sua coluna (coluna sem valor fica vazia).
            foreach ($colunas as $posicao => $nome) {
                $linha[$nome] = trim((string) ($valores[$posicao] ?? ''));
            }
            $linhas[] = $linha;
        }

        return ['colunas' => $colunas, 'linhas' => $linhas];
    }
}
