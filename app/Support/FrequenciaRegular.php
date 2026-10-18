<?php

/*
 * FREQUÊNCIA DAS AULAS REGULARES (#80): quantas aulas com chamada a pessoa teve
 * e em quantas esteve presente, mês a mês, em cada programa que assina (ou
 * assinou). Vai para o dossiê: aula regular não tem certificado, a evolução
 * aparece pela frequência. Só contagens (nada de dado pessoal).
 */

namespace App\Support;

use App\Models\Assinatura;
use App\Models\ProgramaPresenca;
use App\Models\User;

class FrequenciaRegular
{
    /** Quantos meses (os mais recentes) aparecem por programa. */
    public const MESES = 6;

    /**
     * A frequência da pessoa em cada programa.
     *
     * @param  User  $usuario  Quem.
     * @return list<array{programa: string, situacao: string, meses: list<array{mes: string, presencas: int, aulas: int}>}>
     *                                                                                                                      Ex.: [['programa' => 'Krav Maga online', 'situacao' => 'Em dia', 'meses' => [['mes' => '2026-10', 'presencas' => 7, 'aulas' => 8]]]].
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
                ->get(['programa_aulas.data as data', 'programa_presencas.presente as presente']);

            // Agrupa por mês (AAAA-MM): aulas = chamadas em que a pessoa estava na lista; presenças = presente.
            $meses = [];
            foreach ($registros as $r) {
                $mes = substr((string) $r->getAttribute('data'), 0, 7);
                $meses[$mes] ??= ['mes' => $mes, 'presencas' => 0, 'aulas' => 0];
                $meses[$mes]['aulas']++;
                $meses[$mes]['presencas'] += (int) (bool) $r->getAttribute('presente');
            }
            krsort($meses);

            $resultado[] = [
                'programa' => $assinatura->programa->titulo,
                'situacao' => $assinatura->acesso()['rotulo'],
                'meses' => array_values(array_slice($meses, 0, self::MESES)),
            ];
        }

        return $resultado;
    }
}
