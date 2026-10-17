<?php

/*
 * MATRÍCULA de uma pessoa num curso (#41). Ver a migration
 * 2026_10_11_000006 para as situações. As mudanças de situação passam SEMPRE
 * pela classe App\Actions\Cursos\Matriculas (com trava e auditoria); por isso
 * nenhum campo aceita preenchimento direto de formulário.
 */

namespace App\Models;

use App\Enums\SituacaoMatricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_id
 * @property int $user_id
 * @property SituacaoMatricula $situacao
 * @property int $valor_centavos
 * @property string|null $comprovante
 * @property int|null $lancamento_id
 * @property int|null $confirmada_por
 * @property Carbon|null $confirmada_em
 * @property Carbon|null $cancelada_em
 * @property string|null $motivo_cancelamento
 * @property int|null $cupom_id Cupom de desconto usado (#89).
 * @property int|null $valor_cheio_centavos Preço cheio quando usou cupom (#89). Ex.: 25000.
 * @property int $desconto_centavos Desconto do cupom (#89). Ex.: 2500.
 * @property string|null $excecao_motivo Por que o IBTO matriculou quem não cumpre os pré-requisitos (#88).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CursoMatricula extends Model
{
    // Nenhum campo vem de formulário: tudo passa pela classe Matriculas (forceFill).
    protected $guarded = ['*'];

    /**
     * Converte os campos do banco para os tipos certos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'confirmada' vira SituacaoMatricula::Confirmada.
            'situacao' => SituacaoMatricula::class,
            'valor_centavos' => 'integer',
            // Cupom (#89): preço cheio e desconto, em centavos.
            'valor_cheio_centavos' => 'integer',
            'desconto_centavos' => 'integer',
            'confirmada_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    /**
     * Só as que OCUPAM VAGA (aguardando pagamento ou confirmadas; a cancelada libera).
     *
     * @param  Builder<CursoMatricula>  $consulta  A consulta que vai ser filtrada (o Laravel passa sozinho).
     * @return Builder<CursoMatricula> A mesma consulta, só com as que ocupam vaga.
     */
    public function scopeOcupandoVaga(Builder $consulta): Builder
    {
        return $consulta->where('situacao', '!=', SituacaoMatricula::Cancelada->value);
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * O certificado desta matrícula (#44), se já foi emitido.
     *
     * @return HasOne<CursoCertificado, $this>
     */
    public function certificado(): HasOne
    {
        return $this->hasOne(CursoCertificado::class, 'matricula_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * O cupom de desconto usado na inscrição (#89), se houve.
     *
     * @return BelongsTo<Cupom, $this>
     */
    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class);
    }
}
