<?php

/*
 * ÁLBUM DE FOTOS À VENDA de um curso (#90): o fotógrafo, o preço por foto, a
 * parte dele e quando as fotos expiram (são apagadas pela limpeza automática).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_id
 * @property int $fotografo_id
 * @property string $titulo Ex.: 'Combate com Faca — 10/10/2026'.
 * @property int $preco_centavos Preço de cada foto. Ex.: 1500 (R$ 15,00).
 * @property int $percentual_fotografo Parte do fotógrafo, em %. Ex.: 70.
 * @property Carbon $expira_em Quando as fotos são apagadas.
 * @property bool $ativo
 * @property bool $expirado true = as fotos já foram apagadas (fica só o histórico).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['curso_id', 'fotografo_id', 'titulo', 'preco_centavos', 'percentual_fotografo', 'expira_em', 'ativo'])]
class FotoAlbumVenda extends Model
{
    protected $table = 'foto_albuns_venda';

    /**
     * Tipo de cada coluna especial.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preco_centavos' => 'integer',
            'percentual_fotografo' => 'integer',
            'expira_em' => 'date',
            'ativo' => 'boolean',
            'expirado' => 'boolean',
        ];
    }

    /**
     * Divide um valor entre o fotógrafo e o IBTO. O centavo que sobra fica com o fotógrafo
     * (a parte do IBTO arredonda para baixo), e a soma sempre bate.
     *
     * @param  int  $valorCentavos  Ex.: 4500 (3 fotos de R$ 15,00).
     * @return array{ibto: int, fotografo: int} Ex.: 70% → ['ibto' => 1350, 'fotografo' => 3150].
     */
    public function divisao(int $valorCentavos): array
    {
        $ibto = intdiv($valorCentavos * (100 - $this->percentual_fotografo), 100);

        return ['ibto' => $ibto, 'fotografo' => $valorCentavos - $ibto];
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fotografo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fotografo_id');
    }

    /**
     * As fotos, na ordem.
     *
     * @return HasMany<FotoVenda, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoVenda::class, 'album_id')->orderBy('ordem')->orderBy('id');
    }

    /**
     * @return HasMany<PedidoFoto, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoFoto::class, 'album_id');
    }
}
