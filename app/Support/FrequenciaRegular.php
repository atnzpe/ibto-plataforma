<?php

/*
 * FREQUÊNCIA DAS AULAS REGULARES (#80): quantas aulas com chamada a pessoa teve
 * e em quantas esteve presente, mês a mês, em cada programa que assina (ou
 * assinou). Vai para o dossiê: aula regular não tem certificado, a evolução
 * aparece pela frequência e, desde 18/10/2026, pelo DIÁRIO: a lista cronológica
 * das aulas com a presença e o que foi ministrado. Nada de dado pessoal.
 */

namespace App\Support;

use App\Models\Assinatura;
use App\Models\ProgramaPresenca;
use App\Models\User;

class FrequenciaRegular
{
    /** Quantos meses (os mais recentes) aparecem por programa. */
    public const MESES = 6;

    /** Quantas aulas (as mais recentes) aparecem no diário de cada programa. */
    public const AULAS_NO_DIARIO = 30;

    /**
     * A frequência da pessoa em cada programa.
     *
     * @param  User  $usuario  Quem.
     * @return list<array{programa: string, situacao: string, presencas: int, aulas: int, percentual: int|null, meses: list<array{mes: string, presencas: int, aulas: int}>, diario: list<array{data: string, presente: bool, conteudo: string|null}>}>
     *                                                                                                                                                                                                                                                  Ex.: [['programa' => 'Krav Maga online', 'situacao' => 'Em dia', 'meses' => [['mes' => '2026-10', 'presencas' => 7, 'aulas' => 8]]]].
     */
    public static function doUsuario(User $usuario): array
    {
        $resultado = [];
        $assinaturas = Assinatura::query()->with('programa:id,titulo')->where('user_id', $usuario->id)->orderBy('id')->get();

        foreach ($assinaturas as $assinatura) {
            // As presenças registradas desta assinatura, com a data da aula.
            $registros = ProgramaPresenca::query()
                ->join('programa_aulas', 'programa_aulas.id', '=', 'programa_presencas.aula_id')
                ->where('programa_presencas.assinatura_id', $assinatura->id)
                ->orderByDesc('programa_aulas.data')
                ->get(['programa_aulas.data as data', 'programa_presencas.presente as presente', 'programa_aulas.conteudo as conteudo']);

            // Agrupa por mês (AAAA-MM): aulas = chamadas em que a pessoa estava na lista; presenças = presente.
            $meses = [];
            foreach ($registros as $r) {
                $mes = substr((string) $r->getAttribute('data'), 0, 7);
                $meses[$mes] ??= ['mes' => $mes, 'presencas' => 0, 'aulas' => 0];
                $meses[$mes]['aulas']++;
                $meses[$mes]['presencas'] += (int) (bool) $r->getAttribute('presente');
            }
            krsort($meses);

            // Totais de todas as aulas com chamada: presenças, aulas e o percentual (null sem aula).
            $presencas = $registros->filter(fn ($r) => (bool) $r->getAttribute('presente'))->count();
            $aulas = $registros->count();

            $resultado[] = [
                'programa' => $assinatura->programa->titulo,
                'situacao' => $assinatura->acesso()['rotulo'],
                'presencas' => $presencas,
                'aulas' => $aulas,
                'percentual' => $aulas > 0 ? (int) round($presencas * 100 / $aulas) : null,
                'meses' => array_values(array_slice($meses, 0, self::MESES)),
                // O DIÁRIO: as aulas mais recentes primeiro, com a presença e o que foi ministrado.
                'diario' => array_values($registros->take(self::AULAS_NO_DIARIO)->map(fn ($r): array => [
                    'data' => substr((string) $r->getAttribute('data'), 0, 10),
                    'presente' => (bool) $r->getAttribute('presente'),
                    'conteudo' => $r->getAttribute('conteudo'),
                ])->all()),
            ];
        }

        return $resultado;
    }
}
