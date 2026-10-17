<?php

/*
 * CONFERÊNCIA DO CUPOM NA INSCRIÇÃO (#89). Como a caixa do PDV conferindo um
 * cupom: existe e está ativo? está na validade? ainda tem uso? vale para este
 * produto? esta pessoa já usou? Cada recusa tem mensagem e código estável.
 *
 * O limite de usos é à prova de corrida: na matrícula, o cupom é lido com trava
 * (lockForUpdate), então duas pessoas no último uso não passam as duas.
 */

namespace App\Support;

use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Cupom;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\User;

class Cupons
{
    /**
     * Deixa o código no formato gravado: sem espaços e em maiúsculas.
     *
     * @param  string  $codigo  Ex.: ' filiado10 '.
     * @return string Ex.: 'FILIADO10'.
     */
    public static function normalizar(string $codigo): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/', '', $codigo));
    }

    /**
     * Confere o cupom para esta pessoa neste curso.
     *
     * @param  string  $codigo  O que a pessoa digitou. Ex.: 'FILIADO10'.
     * @param  Curso  $curso  O curso da inscrição.
     * @param  User  $usuario  Quem está se inscrevendo.
     * @param  bool  $travar  true = lê o cupom com trava (usar DENTRO da transação da matrícula).
     * @return Cupom O cupom válido.
     *
     * @throws RegraCurso Quando não vale (o código diz o motivo).
     */
    public static function validar(string $codigo, Curso $curso, User $usuario, bool $travar = false): Cupom
    {
        $consulta = Cupom::query()->where('codigo', self::normalizar($codigo));
        $cupom = $travar ? $consulta->lockForUpdate()->first() : $consulta->first();

        // Não existe ou foi desativado: a mesma mensagem (não confirma que o código existe).
        if ($cupom === null || ! $cupom->ativo) {
            throw new RegraCurso('CUPOM_INVALIDO', 'Cupom inválido. Confira o código.');
        }
        // Ainda não começou ou já venceu.
        if ($cupom->valido_de !== null && $cupom->valido_de->isFuture()) {
            throw new RegraCurso('CUPOM_AINDA_NAO_VALE', 'Este cupom ainda não está valendo.');
        }
        if ($cupom->valido_ate !== null && $cupom->valido_ate->isPast()) {
            throw new RegraCurso('CUPOM_VENCIDO', 'Este cupom já venceu.');
        }
        // Não vale neste curso (quando não vale em todos).
        if (! $cupom->todos_cursos && ! $cupom->cursos()->whereKey($curso->id)->exists()) {
            throw new RegraCurso('CUPOM_NAO_VALE_NESTE_CURSO', 'Este cupom não vale para este curso.');
        }
        // Só para filiados ativos.
        if ($cupom->so_filiados && $usuario->filiado?->status !== 'ativo') {
            throw new RegraCurso('CUPOM_SO_FILIADOS', 'Este cupom é exclusivo para filiados ativos do IBTO.');
        }

        // Usos que contam: matrículas com o cupom que não foram canceladas (cancelada devolve o uso).
        $usos = CursoMatricula::query()->where('cupom_id', $cupom->id)->where('situacao', '!=', SituacaoMatricula::Cancelada->value);

        // Limite total.
        if ($cupom->limite_total !== null && (clone $usos)->count() >= $cupom->limite_total) {
            throw new RegraCurso('CUPOM_ESGOTADO', 'Os usos deste cupom acabaram.');
        }
        // Limite por pessoa.
        if ((clone $usos)->where('user_id', $usuario->id)->count() >= $cupom->limite_por_pessoa) {
            throw new RegraCurso('CUPOM_JA_USADO', 'Você já usou este cupom.');
        }

        return $cupom;
    }
}
