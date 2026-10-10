<?php

/*
 * LANÇAMENTO do livro-caixa (#59): uma conta a receber (receita) ou a pagar
 * (despesa). Quem cria, paga, cancela e estorna é o App\Services\LivroCaixa,
 * que confere as regras e grava a auditoria.
 *
 * Regra de ouro (Gleyson, 09/10/2026): lançamento PAGO nunca muda nem é apagado.
 * Para corrigir, cria-se um ESTORNO ligado a ele (como o estorno no PDV).
 */

namespace App\Models;

use App\Exceptions\RegraFinanceira;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tipo
 * @property int $categoria_id
 * @property int|null $forma_pagamento_id
 * @property string $descricao
 * @property int $valor_centavos
 * @property Carbon $vencimento
 * @property int|null $parcela_numero
 * @property int|null $parcela_total
 * @property string|null $grupo_parcelas
 * @property Carbon|null $pago_em
 * @property string $situacao
 * @property string|null $motivo
 * @property int|null $estorno_de_id
 * @property int|null $saldo_de_id
 * @property int|null $equipe_id
 * @property string|null $origem
 * @property int|null $origem_id
 * @property int|null $criado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FinLancamento extends Model
{
    protected $table = 'fin_lancamentos';

    // Sem $fillable de propósito: quem preenche é só o LivroCaixa (forceFill),
    // nunca um formulário direto. Assim ninguém "pula" as regras.
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            // Texto "2027-03-31" do banco vira data no PHP.
            'vencimento' => 'date',
            'pago_em' => 'date',
        ];
    }

    /**
     * Trava do banco de dados para a regra de ouro: um lançamento que JÁ ESTAVA
     * pago não pode ser alterado nem apagado, venha o pedido de onde vier.
     */
    protected static function booted(): void
    {
        // Antes de gravar uma alteração: se a situação ANTERIOR era "pago", recusa.
        // (getOriginal = como estava no banco antes desta alteração.)
        static::updating(function (FinLancamento $lancamento): void {
            if ($lancamento->getOriginal('situacao') === 'pago') {
                throw new RegraFinanceira('LANCAMENTO_PAGO_IMUTAVEL', 'Lançamento pago não pode ser alterado. Faça um estorno.');
            }
        });

        // Antes de apagar: pago nunca se apaga (o histórico de dinheiro não some).
        static::deleting(function (FinLancamento $lancamento): void {
            if ($lancamento->getOriginal('situacao') === 'pago') {
                throw new RegraFinanceira('LANCAMENTO_PAGO_IMUTAVEL', 'Lançamento pago não pode ser apagado. Faça um estorno.');
            }
        });
    }

    /**
     * Quanto este lançamento soma no saldo, em centavos, com o sinal certo.
     * Receita soma (+), despesa subtrai (−); um estorno faz o contrário do original.
     *
     * @return int Ex.: receita de 9000 → 9000; estorno dela → -9000; despesa de 5000 → -5000.
     */
    public function valorNoSaldo(): int
    {
        // Receita entra no caixa (+1); despesa sai (−1).
        $sinal = $this->tipo === 'receita' ? 1 : -1;

        // Estorno desfaz o original: inverte o sinal.
        if ($this->estorno_de_id !== null) {
            $sinal = -$sinal;
        }

        return $sinal * $this->valor_centavos;
    }

    /**
     * Só as contas VENCIDAS: ainda pendentes e com o vencimento antes de hoje.
     * Uso: FinLancamento::query()->vencidos(Carbon::parse('2027-04-01'))->get()
     *
     * @param  Builder<FinLancamento>  $consulta  A consulta que vai ser filtrada (o Laravel passa sozinho).
     * @param  CarbonInterface|null  $hoje  A data de referência (vazio = hoje).
     * @return Builder<FinLancamento> A mesma consulta, com o filtro.
     */
    public function scopeVencidos(Builder $consulta, ?CarbonInterface $hoje = null): Builder
    {
        // O dia de referência, só a data (sem hora). Ex.: '2027-04-01'.
        $dia = ($hoje ?? now())->toDateString();

        // Filtra lançamentos pendentes com vencimento antes da data de referência
        return $consulta->where('situacao', 'pendente')->where('vencimento', '<', $dia);
    }

    /**
     * @return BelongsTo<FinCategoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FinCategoria::class, 'categoria_id');
    }

    /**
     * @return BelongsTo<FinFormaPagamento, $this>
     */
    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FinFormaPagamento::class, 'forma_pagamento_id');
    }

    /**
     * O estorno deste lançamento, se houver (no máximo um).
     *
     * @return HasOne<FinLancamento, $this>
     */
    public function estorno(): HasOne
    {
        return $this->hasOne(FinLancamento::class, 'estorno_de_id');
    }
}
