<?php

/*
 * LEMBRETE DE AGENDA (#38, bloco C4d; conversa de 23/09/2026: "botão
 * lembrete" no cronograma). Monta o arquivo .ics, o formato de agenda que o
 * celular (iPhone e Android), o Google Agenda e o Outlook entendem: a pessoa
 * abre o arquivo e o encontro entra na agenda dela, com dois avisos (1 dia
 * antes e 1 hora antes).
 *
 * Regras do formato (RFC 5545, a "norma" do .ics):
 * - linhas terminam em \r\n (Enter do Windows);
 * - vírgula, ponto e vírgula, barra invertida e Enter dentro de um texto
 *   precisam de uma "\" antes (escapar);
 * - linha com mais de 75 bytes é "dobrada": continua na linha de baixo,
 *   começando com um espaço.
 * - os horários vão em UTC (o "Z" no fim), assim cada agenda converte para o
 *   fuso de quem abriu.
 */

namespace App\Support;

use Carbon\CarbonInterface;

class Calendario
{
    /**
     * Monta o .ics de UM encontro.
     *
     * @param  string  $uid  Identificador único e fixo do encontro (a agenda usa para não duplicar).
     *                       Ex.: 'aula-31@ib-to.org'
     * @param  string  $titulo  Ex.: 'Combate Corpo a Corpo — Aula 1'
     * @param  CarbonInterface  $inicio  Começo. Ex.: 10/10/2026 09:00 (horário de Brasília).
     * @param  CarbonInterface  $fim  Fim. Ex.: 10/10/2026 12:00.
     * @param  bool  $diaInteiro  true = sem horário (o encontro ocupa o dia todo na agenda).
     * @param  string|null  $local  Ex.: 'Recife/PE'
     * @param  string|null  $descricao  Introdução, quem aplica e o link. Pode ter várias linhas.
     * @param  string|null  $url  A página do curso.
     * @return string O conteúdo do arquivo .ics.
     */
    public static function evento(
        string $uid,
        string $titulo,
        CarbonInterface $inicio,
        CarbonInterface $fim,
        bool $diaInteiro = false,
        ?string $local = null,
        ?string $descricao = null,
        ?string $url = null,
    ): string {
        // Formato das datas: dia inteiro = só a data (20261010); com hora = UTC (20261010T120000Z).
        $quando = $diaInteiro
            ? ['DTSTART;VALUE=DATE:'.$inicio->format('Ymd'), 'DTEND;VALUE=DATE:'.$fim->format('Ymd')]
            : ['DTSTART:'.self::utc($inicio), 'DTEND:'.self::utc($fim)];

        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IBTO//Plataforma de cursos//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            // Quando o arquivo foi gerado (obrigatório no formato).
            'DTSTAMP:'.self::utc(now()),
            ...$quando,
            'SUMMARY:'.self::escapar($titulo),
        ];

        // Campos opcionais: só entram quando têm valor.
        if ($local !== null && $local !== '') {
            $linhas[] = 'LOCATION:'.self::escapar($local);
        }
        if ($descricao !== null && $descricao !== '') {
            $linhas[] = 'DESCRIPTION:'.self::escapar($descricao);
        }
        if ($url !== null) {
            $linhas[] = 'URL:'.$url;
        }

        // Os dois avisos: 1 dia antes (-P1D) e 1 hora antes (-PT1H).
        foreach (['-P1D' => 'Amanhã', '-PT1H' => 'Em 1 hora'] as $antes => $texto) {
            array_push(
                $linhas,
                'BEGIN:VALARM',
                'ACTION:DISPLAY',
                'TRIGGER:'.$antes,
                'DESCRIPTION:'.self::escapar("{$texto}: {$titulo}"),
                'END:VALARM',
            );
        }

        array_push($linhas, 'END:VEVENT', 'END:VCALENDAR');

        // Dobra as linhas longas e junta tudo com o Enter do formato (\r\n).
        return implode("\r\n", array_map(self::dobrar(...), $linhas))."\r\n";
    }

    /**
     * Data e hora em UTC, no formato do .ics.
     *
     * @param  CarbonInterface  $momento  Ex.: 10/10/2026 09:00 em Brasília (UTC-3).
     * @return string Ex.: '20261010T120000Z'
     */
    private static function utc(CarbonInterface $momento): string
    {
        return $momento->copy()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Põe a "\" antes dos caracteres especiais do formato.
     *
     * @param  string  $texto  Ex.: 'Recife, PE; turma 1'
     * @return string Ex.: 'Recife\, PE\; turma 1'
     */
    private static function escapar(string $texto): string
    {
        // A barra invertida vem PRIMEIRO, senão as barras que acabamos de pôr seriam dobradas.
        $texto = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $texto);

        // Enter (de qualquer sistema) vira o texto "\n", que a agenda mostra como quebra de linha.
        return str_replace(["\r\n", "\r", "\n"], '\\n', $texto);
    }

    /**
     * Dobra uma linha maior que 75 bytes: quebra e continua com um espaço.
     * Conta BYTES (não letras) e nunca corta uma letra acentuada ao meio.
     *
     * @param  string  $linha  Ex.: 'DESCRIPTION:um texto bem comprido...'
     * @return string Ex.: "DESCRIPTION:um texto\r\n  bem comprido..."
     */
    private static function dobrar(string $linha): string
    {
        $partes = [];
        $atual = '';

        // mb_str_split: separa por LETRA (o "ç" fica inteiro, mesmo tendo 2 bytes).
        foreach (mb_str_split($linha) as $letra) {
            // A primeira parte cabe 75 bytes; as outras, 74 (o espaço do começo conta).
            $limite = $partes === [] ? 75 : 74;
            if (strlen($atual.$letra) > $limite) {
                $partes[] = $atual;
                $atual = '';
            }
            $atual .= $letra;
        }
        $partes[] = $atual;

        return implode("\r\n ", $partes);
    }
}
