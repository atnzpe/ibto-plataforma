<?php

/*
 * FOTO de um álbum da galeria de eventos (#61, bloco B2): o arquivo, a
 * DESCRIÇÃO (texto alternativo para quem usa leitor de tela), o crédito e a
 * ordem. A foto é entregue por rota própria, só quando o álbum está no site
 * (ou para quem cuida do blog).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $album_id
 * @property string $caminho
 * @property string|null $descricao
 * @property string|null $credito
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// "caminho" e "album_id" ficam de fora: quem grava é o controlador (forceFill).
#[Fillable(['descricao', 'credito', 'ordem'])]
class GaleriaFoto extends Model
{
    // A tabela tem nome em português.
    protected $table = 'galeria_fotos';

    // Tamanho mínimo da descrição para publicar (regra da CBSA: pelo menos 5 letras).
    public const DESCRICAO_MINIMA = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ordem' => 'integer'];
    }

    /**
     * A foto tem uma descrição válida? (pelo menos 5 letras, sem contar os espaços das pontas)
     */
    public function descrita(): bool
    {
        return mb_strlen(trim((string) $this->descricao)) >= self::DESCRICAO_MINIMA;
    }

    /**
     * Endereço público da foto (rota própria). O "?v=" muda quando a foto muda.
     */
    public function url(): string
    {
        return route('galeria.foto', ['foto' => $this->id, 'v' => $this->updated_at?->getTimestamp()]);
    }

    /**
     * O álbum desta foto.
     *
     * @return BelongsTo<GaleriaAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GaleriaAlbum::class, 'album_id');
    }
}
