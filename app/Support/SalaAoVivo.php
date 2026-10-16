<?php

/*
 * SALA AO VIVO (#71): as regras do link da sala dos encontros online.
 *
 * - Só endereços https de serviços conhecidos (Google Meet, Zoom, Microsoft
 *   Teams e YouTube): um link qualquer poderia levar o aluno a um site falso.
 * - A sala "abre" 30 minutos antes do início do encontro e "fecha" no
 *   horário de fim (sem fim cadastrado: 4 horas depois do início). Fora
 *   disso, o aluno vê "a sala abre às HH:MM" ou "encontro encerrado".
 * - Quem recebe o link é decidido pela tela (só matrícula confirmada); aqui
 *   ficam só as regras de endereço e de horário, testadas em
 *   tests/Unit/SalaAoVivoTest.php.
 */

namespace App\Support;

use App\Models\CursoAula;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class SalaAoVivo
{
    // Quantos minutos antes do início a sala abre (o aluno entra, testa o som).
    public const MINUTOS_ANTES = 30;

    // Sem horário de fim cadastrado, a sala fica aberta por estas horas depois do início.
    public const HORAS_SEM_FIM = 4;

    // Os serviços aceitos: o nome do endereço (host) exato, ou terminado em ".zoom.us"
    // (cada empresa tem o seu, ex.: "us02web.zoom.us").
    private const ENDERECOS_ACEITOS = [
        'meet.google.com',
        'zoom.us',
        'teams.microsoft.com',
        'teams.live.com',
        'youtube.com',
        'www.youtube.com',
        'youtu.be',
    ];

    /**
     * O link é de um serviço aceito, por https?
     *
     * @param  string  $link  Ex.: 'https://meet.google.com/abc-defg-hij' (aceito) ou 'http://site-estranho.com' (recusado).
     */
    public static function linkValido(string $link): bool
    {
        $partes = parse_url(trim($link));
        // Endereço quebrado, ou sem https: recusa.
        if ($partes === false || ($partes['scheme'] ?? '') !== 'https' || ! isset($partes['host'])) {
            return false;
        }
        // Usuário e senha dentro do endereço ("https://x@site"): truque de golpe, recusa.
        if (isset($partes['user']) || isset($partes['pass'])) {
            return false;
        }

        $host = strtolower($partes['host']);

        return in_array($host, self::ENDERECOS_ACEITOS, true) || str_ends_with($host, '.zoom.us');
    }

    /**
     * A situação da sala agora: 'sem_horario', 'antes', 'aberta' ou 'encerrada'.
     *
     * @param  CursoAula  $aula  O encontro (aula com data e horário de início).
     * @param  CarbonInterface|null  $agora  O momento de referência (nos testes, um horário fixo).
     */
    public static function situacao(CursoAula $aula, ?CarbonInterface $agora = null): string
    {
        // Sem dia ou sem horário de início: não dá para calcular a janela.
        if ($aula->data === null || $aula->hora_inicio === null) {
            return 'sem_horario';
        }
        $agora ??= now();
        [$abre, $fecha] = self::janela($aula);

        // Antes da abertura, dentro da janela ou depois do fim.
        if ($agora->lt($abre)) {
            return 'antes';
        }

        return $agora->lte($fecha) ? 'aberta' : 'encerrada';
    }

    /**
     * O horário em que a sala abre e o em que fecha.
     *
     * @param  CursoAula  $aula  O encontro, com data e hora de início.
     * @return array{0: Carbon, 1: Carbon} [abre, fecha]
     */
    public static function janela(CursoAula $aula): array
    {
        $dia = $aula->data->toDateString();
        $inicio = Carbon::parse("{$dia} {$aula->hora_inicio}");
        // Fim cadastrado: ele; senão, 4 horas depois do início.
        $fim = $aula->hora_fim !== null
            ? Carbon::parse("{$dia} {$aula->hora_fim}")
            : $inicio->copy()->addHours(self::HORAS_SEM_FIM);

        return [$inicio->copy()->subMinutes(self::MINUTOS_ANTES), $fim];
    }
}
