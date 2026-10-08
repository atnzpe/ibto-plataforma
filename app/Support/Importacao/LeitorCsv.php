<?php

/*
 * Lê um arquivo CSV (planilha salva como "valores separados") para a
 * importação de filiados (#37, F4).
 *
 * Aceita o que o Excel, o Google Planilhas e o Canva costumam gerar:
 * - separador ";" ou ",";
 * - o "BOM" (3 bytes invisíveis que o Excel põe no começo de arquivos UTF-8);
 * - a acentuação antiga do Excel em português (Windows-1252);
 * - célula entre aspas com QUEBRA DE LINHA dentro (ex.: "RJ" + Enter), como
 *   aparece na planilha do Canva: a linha não pode ser partida ao meio.
 * A primeira linha é o cabeçalho (nomes das colunas).
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

        // O separador é o que mais aparece na 1ª linha: ";" (Excel em português) ou ",".
        $primeira = strtok($conteudo, "\r\n") ?: '';
        $separador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';

        // Coloca o texto num "arquivo de memória" para o fgetcsv ler. Ele entende
        // aspas que atravessam linhas (célula com Enter dentro), o que separar por
        // linha antes NÃO entenderia.
        $arquivo = fopen('php://temp', 'r+');
        if ($arquivo === false) {
            return ['colunas' => [], 'linhas' => []];
        }
        fwrite($arquivo, $conteudo);
        rewind($arquivo);

        $colunas = [];
        $linhas = [];

        while (($valores = fgetcsv($arquivo, null, $separador, '"', '')) !== false) {
            // Linha totalmente vazia (ex.: Enter sobrando no fim): pula.
            if ($valores === [null] || implode('', array_map('strval', $valores)) === '') {
                continue;
            }

            // Tira espaços e quebras de linha das pontas (ex.: "RJ\n" vira "RJ").
            $valores = array_map(fn ($valor) => trim((string) $valor), $valores);

            // A primeira linha com conteúdo é o cabeçalho.
            if ($colunas === []) {
                $colunas = $valores;

                continue;
            }

            $linha = [];
            // Liga cada valor ao nome da sua coluna (coluna sem valor fica vazia).
            foreach ($colunas as $posicao => $nome) {
                $linha[$nome] = $valores[$posicao] ?? '';
            }
            $linhas[] = $linha;
        }

        fclose($arquivo);

        return ['colunas' => $colunas, 'linhas' => $linhas];
    }
}
