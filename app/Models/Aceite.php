<?php

/*
 * ACEITE (LGPD, #47): uma linha = uma pessoa aceitou um documento (ex.: a
 * política de privacidade) numa versão, numa data. Revogar grava revogado_em.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $documento Ex.: 'privacidade'.
 * @property string $versao Ex.: '0.1'.
 * @property Carbon $aceito_em
 * @property Carbon|null $revogado_em null = vale.
 */
class Aceite extends Model
{
    /** O documento da política de privacidade. */
    public const PRIVACIDADE = 'privacidade';

    protected $fillable = ['user_id', 'documento', 'versao', 'aceito_em', 'revogado_em'];

    /**
     * Converte as datas em objetos de data (Carbon).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['aceito_em' => 'datetime', 'revogado_em' => 'datetime'];
    }

    /**
     * O aceite que VALE agora desta pessoa para a versão atual da política, ou null.
     *
     * @param  int  $usuarioId  Ex.: 7.
     * @return self|null O aceite (não revogado) da versão em vigor.
     */
    public static function daPoliticaAtual(int $usuarioId): ?self
    {
        return self::query()
            ->where('user_id', $usuarioId)
            ->where('documento', self::PRIVACIDADE)
            ->where('versao', (string) config('lgpd.politica_versao'))
            ->whereNull('revogado_em')
            ->latest('id')
            ->first();
    }
}
