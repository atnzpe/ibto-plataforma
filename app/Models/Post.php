<?php

/*
 * POST do BLOG do IBTO (#61): uma notícia ou a cobertura de um evento, com
 * capa, vídeo e, se quiser, o curso ligado. Quem escreve é quem tem
 * "blog.publicar". As regras de publicar (capa, resumo, autorização de
 * imagem) ficam em App\Actions\Blog\PublicacaoPost; a situação muda só por lá.
 */

namespace App\Models;

use App\Enums\CategoriaPost;
use App\Enums\SituacaoPost;
use App\Support\Video;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $titulo
 * @property string $slug
 * @property CategoriaPost $categoria
 * @property string|null $resumo
 * @property string|null $texto
 * @property string|null $capa
 * @property string|null $capa_descricao
 * @property string|null $credito
 * @property string|null $video_url
 * @property int|null $curso_id
 * @property SituacaoPost $situacao
 * @property Carbon|null $publicar_em
 * @property bool $tem_pessoas
 * @property bool $autorizacao_imagem
 * @property string|null $autorizacao_origem
 * @property bool $tem_menores
 * @property bool $autorizacao_responsaveis
 * @property int|null $autor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Ficam de fora: "slug" (calculado aqui), "capa" (gravada pelo ImagemPublica),
// "situacao" e "publicar_em" (só a PublicacaoPost muda) e "autor_id".
#[Fillable(['titulo', 'categoria', 'resumo', 'texto', 'capa_descricao', 'credito', 'video_url', 'curso_id', 'tem_pessoas', 'autorizacao_imagem', 'autorizacao_origem', 'tem_menores', 'autorizacao_responsaveis'])]
class Post extends Model
{
    // Os valores padrão também aqui (além do banco): assim um post recém-criado
    // já "sabe" que é rascunho, sem precisar ser lido de novo do banco.
    protected $attributes = [
        'categoria' => 'noticia',
        'situacao' => 'rascunho',
        'tem_pessoas' => false,
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
            // Texto do banco vira uma das opções (ex.: CategoriaPost::Evento).
            'categoria' => CategoriaPost::class,
            'situacao' => SituacaoPost::class,
            'publicar_em' => 'datetime',
            // 0/1 do banco viram false/true.
            'tem_pessoas' => 'boolean',
            'autorizacao_imagem' => 'boolean',
            'tem_menores' => 'boolean',
            'autorizacao_responsaveis' => 'boolean',
        ];
    }

    /**
     * Ao criar, monta o "slug" (o pedaço do endereço) a partir do título.
     * Ex.: 'Formação no estande!' → 'formacao-no-estande'. Se já existir um
     * igual, acrescenta um número: 'formacao-no-estande-2'.
     */
    protected static function booted(): void
    {
        static::creating(function (Post $post): void {
            // Str::slug tira acentos e símbolos e troca espaços por traço.
            $base = Str::slug($post->titulo) ?: 'post';
            $slug = $base;
            $numero = 2;
            // Enquanto já existir um post com esse slug, tenta o próximo número.
            while (self::query()->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$numero}";
                $numero++;
            }
            $post->slug = $slug;
        });
    }

    /**
     * Filtro pronto ("escopo") dos posts que aparecem no SITE: publicados e com
     * a data de publicação já chegada (o agendado só aparece no dia).
     * Uso: Post::query()->noSite()->...
     *
     * @param  Builder<Post>  $consulta  A consulta que está sendo montada.
     */
    public function scopeNoSite(Builder $consulta): void
    {
        $consulta->where('situacao', SituacaoPost::Publicado)
            ->where(fn (Builder $quando) => $quando->whereNull('publicar_em')->orWhere('publicar_em', '<=', now()));
    }

    /**
     * O post aparece no site agora? (publicado e com a data chegada)
     */
    public function visivelNoSite(): bool
    {
        return $this->situacao === SituacaoPost::Publicado
            && ($this->publicar_em === null || $this->publicar_em->lte(now()));
    }

    /**
     * Como tocar o vídeo (YouTube ou arquivo), ou null sem vídeo.
     *
     * @return array<string, string>|null Ex.: ['tipo' => 'youtube', 'id' => '...', 'embed' => '...']
     */
    public function video(): ?array
    {
        return Video::paraTocar($this->video_url);
    }

    /**
     * Endereço público da capa (rota própria), ou null sem capa. O "?v=" muda
     * quando o post muda, para o navegador não mostrar a capa antiga.
     */
    public function capaUrl(): ?string
    {
        if ($this->capa === null) {
            return null;
        }

        return route('blog.capa', ['post' => $this->id, 'v' => $this->updated_at?->getTimestamp()]);
    }

    /**
     * O curso ligado ao post (opcional).
     *
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Quem escreveu o post.
     *
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
