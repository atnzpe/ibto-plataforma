<?php

/*
 * CERTIFICADO DE GRADUAÇÃO (#46, parte 2): a "foto" do dia em que o IBTO
 * reconheceu o filiado numa condição e num nível. Tem o número de série
 * impresso e o código sorteado da validação pública (QR).
 */

namespace App\Models;

use App\Relatorios\AuditoriaRelatorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $filiado_id
 * @property string $codigo Ex.: 'IBTO-7K2M-9QXA'.
 * @property string $numero_serie Ex.: 'GR20260034E0'.
 * @property string $nome
 * @property string $condicao Ex.: 'Instrutor de Krav Maga'.
 * @property string $nivel Ex.: 'Nível Expert - [E]'.
 * @property string $abrangencia Ex.: 'Nacional'.
 * @property string $local Ex.: 'Rio de Janeiro - RJ'.
 * @property Carbon $emitido_em
 * @property int|null $emitido_por
 * @property Carbon|null $cancelado_em
 * @property string|null $motivo_cancelamento
 * @property-read Filiado $filiado
 */
class GraduacaoCertificado extends Model
{
    protected $table = 'graduacao_certificados';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['emitido_em' => 'date', 'cancelado_em' => 'datetime'];
    }

    /**
     * Vale? (não cancelado)
     */
    public function valido(): bool
    {
        return $this->cancelado_em === null;
    }

    /**
     * O nome para a validação pública: o primeiro nome e as iniciais. Ex.: 'Gleyson A. S.'
     */
    public function nomeAbreviado(): string
    {
        return AuditoriaRelatorio::abreviado($this->nome);
    }

    /**
     * O filiado.
     *
     * @return BelongsTo<Filiado, $this>
     */
    public function filiado(): BelongsTo
    {
        return $this->belongsTo(Filiado::class);
    }
}
