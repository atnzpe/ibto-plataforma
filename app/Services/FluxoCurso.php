<?php

/*
 * FLUXO de situação dos cursos (#40, decisão do Gleyson em 10/10/2026): a
 * TABELA de regras num lugar só — qual ação leva de qual situação para qual,
 * quem pode fazer e se precisa de motivo. Cada passo vai para a auditoria.
 *
 * Quem é quem:
 * - "dono": quem propôs o curso (cursos.propor) — só nos cursos DELE;
 * - "chancela": o IBTO (cursos.chancelar ou cursos.gerenciar).
 */

namespace App\Services;

use App\Enums\SituacaoCurso;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FluxoCurso
{
    /**
     * As ações possíveis: de quais situações, para qual, quem pode e se exige motivo.
     *
     * @var array<string, array{de: list<SituacaoCurso>, para: SituacaoCurso, quem: list<string>, motivo: bool, rotulo: string}>
     */
    public const ACOES = [
        // O dono manda o rascunho (ou o rejeitado, já ajustado) para o IBTO analisar.
        'enviar' => ['de' => [SituacaoCurso::Rascunho, SituacaoCurso::Rejeitado], 'para' => SituacaoCurso::EmAnalise, 'quem' => ['dono'], 'motivo' => false, 'rotulo' => 'Enviar para chancela'],
        // O IBTO aprova: o curso é publicado (também vale para o rascunho criado pelo próprio IBTO).
        'chancelar' => ['de' => [SituacaoCurso::Rascunho, SituacaoCurso::EmAnalise], 'para' => SituacaoCurso::Publicado, 'quem' => ['chancela'], 'motivo' => false, 'rotulo' => 'Chancelar e publicar'],
        // O IBTO devolve para ajustes, explicando o porquê.
        'rejeitar' => ['de' => [SituacaoCurso::EmAnalise], 'para' => SituacaoCurso::Rejeitado, 'quem' => ['chancela'], 'motivo' => true, 'rotulo' => 'Rejeitar'],
        // Cancelar: o dono desiste do que ainda não foi publicado; o IBTO cancela qualquer um não encerrado.
        'cancelar' => ['de' => [SituacaoCurso::Rascunho, SituacaoCurso::EmAnalise, SituacaoCurso::Rejeitado, SituacaoCurso::Publicado], 'para' => SituacaoCurso::Cancelado, 'quem' => ['dono', 'chancela'], 'motivo' => true, 'rotulo' => 'Cancelar curso'],
        // Encerrar: o curso aconteceu e terminou (sai do catálogo; certificados continuam).
        'encerrar' => ['de' => [SituacaoCurso::Publicado], 'para' => SituacaoCurso::Encerrado, 'quem' => ['chancela'], 'motivo' => false, 'rotulo' => 'Encerrar'],
    ];

    /**
     * As ações que ESTA pessoa pode fazer NESTE curso agora (para a tela mostrar os botões).
     *
     * @return list<array{acao: string, rotulo: string, motivo: bool}>
     */
    public function acoesPara(Curso $curso, User $usuario): array
    {
        $acoes = [];
        foreach (self::ACOES as $acao => $regra) {
            if ($this->pode($curso, $usuario, $acao)) {
                $acoes[] = ['acao' => $acao, 'rotulo' => $regra['rotulo'], 'motivo' => $regra['motivo']];
            }
        }

        return $acoes;
    }

    /**
     * Esta pessoa pode fazer esta ação neste curso, na situação atual?
     *
     * @param  string  $acao  Ex.: 'chancelar'
     */
    public function pode(Curso $curso, User $usuario, string $acao): bool
    {
        $regra = self::ACOES[$acao] ?? null;
        // Ação desconhecida, ou a situação atual não permite: não.
        if ($regra === null || ! in_array($curso->situacao, $regra['de'], true)) {
            return false;
        }

        // Papel desta pessoa NESTE curso (pode ser os dois: ex. o IBTO propondo).
        $ehChancela = $usuario->can('cursos.chancelar') || $usuario->can('cursos.gerenciar');
        $ehDono = $usuario->can('cursos.propor') && $curso->responsavel_id === $usuario->id;

        return (in_array('chancela', $regra['quem'], true) && $ehChancela)
            || (in_array('dono', $regra['quem'], true) && $ehDono);
    }

    /**
     * Faz a ação: muda a situação, guarda o motivo (quando há) e quem chancelou,
     * e registra na auditoria. Recusa com código se não puder.
     *
     * @param  string  $acao  Ex.: 'rejeitar'
     * @param  string|null  $motivo  Obrigatório em rejeitar e cancelar. Ex.: 'Falta a carga horária.'
     * @return Curso O mesmo curso, na situação nova.
     */
    public function aplicar(Curso $curso, User $usuario, string $acao, ?string $motivo = null): Curso
    {
        if (! $this->pode($curso, $usuario, $acao)) {
            throw new RegraCurso('CURSO_ACAO_NAO_PERMITIDA', 'Esta ação não é permitida para você, ou não nesta situação do curso.');
        }
        $regra = self::ACOES[$acao];
        $motivo = trim((string) $motivo);
        if ($regra['motivo'] && mb_strlen($motivo) < 5) {
            throw new RegraCurso('MOTIVO_OBRIGATORIO', 'Explique o motivo (pelo menos 5 letras).');
        }

        return DB::transaction(function () use ($curso, $usuario, $acao, $regra, $motivo): Curso {
            $antes = $curso->situacao->value;
            $curso->forceFill([
                'situacao' => $regra['para'],
                // Motivo só nas ações que pedem; nas outras, limpa o antigo.
                'motivo_situacao' => $regra['motivo'] ? $motivo : null,
                // Na chancela, guarda quem aprovou e quando.
                ...($acao === 'chancelar' ? ['chancelado_por' => $usuario->id, 'chancelado_em' => now()] : []),
            ])->save();

            Auditoria::registrar('CURSO_'.mb_strtoupper($acao), [
                'curso_id' => $curso->id,
                'de' => $antes,
                'para' => $curso->situacao->value,
            ]);

            return $curso;
        });
    }
}
