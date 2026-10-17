<?php

/*
 * UMA FOTO À VENDA (#90): o original (privado; só sai depois de pago) e a prévia
 * reduzida com a marca d'água (a que aparece na vitrine).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $album_id
 * @property string $original Caminho na pasta privada. Ex.: 'fotos-venda/originais/9f1c....jpg'.
 * @property string $previa Caminho da prévia com marca d'água. Ex.: 'fotos-venda/previas/9f1c....webp'.
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FotoVenda extends Model
{
    protected $table = 'fotos_venda';

    /**
     * @return BelongsTo<FotoAlbumVenda, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(FotoAlbumVenda::class, 'album_id');
    }
}
