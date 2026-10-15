<?php

/*
 * ÁLBUM da galeria de eventos (#61, bloco B2): a cobertura de um evento com
 * as fotos, ligado ou não a um post do blog. A situação só muda pelas regras
 * de App\Actions\Blog\PublicacaoAlbum (publicar exige fotos descritas e a
 * autorização de uso de imagem).
 */

namespace App\Models;

use App\Enums\SituacaoAlbum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $titulo
 * @property string $slug
 * @property string|null $descricao
 * @property Carbon|null $data_evento
 * @property string|null $local_evento
 * @property int|null $post_id
 * @property string $credito_padrao
 * @property SituacaoAlbum $situacao
 * @property bool $autorizacao_imagem
 * @property string|null $autorizacao_origem
 * @property bool $tem_menores
 * @property bool $autorizacao_responsaveis
 * @property int|null $autor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Ficam de fora: "slug" (calculado aqui), "situacao" (só a PublicacaoAlbum muda) e "autor_id".
#[Fillable(['titulo', 'descricao', 'data_evento', 'local_evento', 'post_id', 'credito_padrao', 'autorizacao_imagem', 'autorizacao_origem', 'tem_menores', 'autorizacao_responsaveis'])]
class GaleriaAlbum extends Model
{
    // A tabela tem nome em português (o Laravel adivinharia em inglês).
    protected $table = 'galeria_albuns';

    // Os valores padrão também aqui: um álbum recém-criado já "sabe" que é rascunho.
    protected $attributes = [
        'situacao' => 'rascunho',
        'credito_padrao' => 'Divulgação / IBTO',
        'autorizacao_imagem' => false,
        'tem_menores' => false,
        'autorizacao_responsaveis' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'situacao' => SituacaoAlbum::class,
            'data_evento' => 'date',
            'autorizacao_imagem' => 'boolean',
            'tem_menores' => 'boolean',
            'autorizacao_responsaveis' => 'boolean',
        ];
    }

    /**
     * Ao criar, monta o "slug" a partir do título (sem acentos; com número se repetir).
     * Ex.: 'Estande de tiro (PE)' → 'estande-de-tiro-pe'.
     */
    protected static function booted(): void
    {
        static::creating(function (GaleriaAlbum $album): void {
            $base = Str::slug($album->titulo) ?: 'album';
            $slug = $base;
            $numero = 2;
            // Enquanto já existir um álbum com esse slug, tenta o próximo número.
            while (self::query()->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$numero}";
                $numero++;
            }
            $album->slug = $slug;
        });
    }

    /**
     * O álbum aparece no site? (só o publicado)
     */
    public function visivelNoSite(): bool
    {
        return $this->situacao === SituacaoAlbum::Publicado;
    }

    /**
     * As fotos, na ordem.
     *
     * @return HasMany<GaleriaFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(GaleriaFoto::class, 'album_id')->orderBy('ordem')->orderBy('id');
    }

    /**
     * O post do blog ligado (opcional).
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
