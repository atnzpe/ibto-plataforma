<?php

/*
 * PEDIDO DE FOTOS de um aluno (#90): as fotos escolhidas de um álbum, o valor,
 * a situação (aguardando pagamento, pago ou cancelado) e até quando pode baixar.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $album_id
 * @property string $situacao 'aguardando_pagamento', 'pago' ou 'cancelado'.
 * @property int $valor_centavos
 * @property int|null $lancamento_id
 * @property int|null $confirmado_por
 * @property Carbon|null $confirmado_em
 * @property Carbon|null $download_ate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PedidoFoto extends Model
{
    protected $table = 'pedidos_fotos';

    /**
     * Tipo de cada coluna especial.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'confirmado_em' => 'datetime',
            'download_ate' => 'datetime',
        ];
    }

    /**
     * Pode baixar os originais agora? (pago e dentro do prazo)
     */
    public function podeBaixar(): bool
    {
        return $this->situacao === 'pago' && $this->download_ate !== null && $this->download_ate->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<FotoAlbumVenda, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(FotoAlbumVenda::class, 'album_id');
    }

    /**
     * As fotos escolhidas.
     *
     * @return BelongsToMany<FotoVenda, $this>
     */
    public function fotos(): BelongsToMany
    {
        return $this->belongsToMany(FotoVenda::class, 'pedido_foto_itens', 'pedido_id', 'foto_id');
    }
}
