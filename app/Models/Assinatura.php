<?php

/*
 * ASSINATURA DE UM PROGRAMA (#80): a pessoa nas aulas regulares, pagando todo mês.
 * Situação guardada: aguardando_pagamento (ainda não pagou a 1ª), ativa ou
 * cancelada. O ACESSO às aulas é calculado pela data paga (pago_ate):
 * em dia → liberado; até 5 dias depois → carência (liberado, com aviso);
 * depois → travado. A carteirinha e o passaporte NÃO mudam (decisão de 17/10/2026).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $programa_id
 * @property int $user_id
 * @property int|null $turma_id
 * @property string $situacao 'aguardando_pagamento', 'ativa' ou 'cancelada'.
 * @property Carbon|null $pago_ate Até quando a mensalidade está paga.
 * @property Carbon|null $cancelada_em
 * @property Carbon|null $created_at
 * @property-read Programa $programa
 * @property-read User $usuario
 * @property-read ProgramaTurma|null $turma
 */
class Assinatura extends Model
{
    /** Dias de carência depois do vencimento (decisão de 17/10/2026). */
    public const DIAS_CARENCIA = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pago_ate' => 'date', 'cancelada_em' => 'datetime'];
    }

    /**
     * O acesso às aulas hoje.
     *
     * @return array{acesso: string, rotulo: string}
     *                                               'liberado' (em dia), 'carencia' (venceu há até 5 dias, ainda liberado),
     *                                               'travado' (venceu há mais de 5 dias), 'aguardando' (1ª mensalidade não paga) ou 'cancelada'.
     */
    public function acesso(): array
    {
        if ($this->situacao === 'cancelada') {
            return ['acesso' => 'cancelada', 'rotulo' => 'Assinatura cancelada'];
        }
        // Ainda não pagou a primeira mensalidade: sem acesso.
        if ($this->pago_ate === null) {
            return ['acesso' => 'aguardando', 'rotulo' => 'Aguardando a primeira mensalidade'];
        }
        $hoje = today();
        if ($hoje->lte($this->pago_ate)) {
            return ['acesso' => 'liberado', 'rotulo' => 'Em dia'];
        }
        // Venceu: até 5 dias depois ainda acessa (carência); depois, trava só as aulas.
        if ($hoje->lte($this->pago_ate->copy()->addDays(self::DIAS_CARENCIA))) {
            return ['acesso' => 'carencia', 'rotulo' => 'Mensalidade vencida: acesso até '.$this->pago_ate->copy()->addDays(self::DIAS_CARENCIA)->format('d/m')];
        }

        return ['acesso' => 'travado', 'rotulo' => 'Mensalidade vencida: aulas travadas até o pagamento'];
    }

    /**
     * Pode entrar nas aulas agora? (em dia ou na carência)
     */
    public function liberada(): bool
    {
        return in_array($this->acesso()['acesso'], ['liberado', 'carencia'], true);
    }

    /**
     * O programa.
     *
     * @return BelongsTo<Programa, $this>
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /**
     * Quem assina.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * A turma escolhida (opcional).
     *
     * @return BelongsTo<ProgramaTurma, $this>
     */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(ProgramaTurma::class, 'turma_id');
    }

    /**
     * As mensalidades (a mais nova primeiro).
     *
     * @return HasMany<AssinaturaPagamento, $this>
     */
    public function pagamentos(): HasMany
    {
        return $this->hasMany(AssinaturaPagamento::class)->orderByDesc('referencia');
    }
}
