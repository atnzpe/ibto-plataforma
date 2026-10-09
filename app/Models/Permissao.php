<?php

/*
 * Permissão (#36): uma coisa que dá para fazer na plataforma, identificada por
 * um slug usado nas rotas. Ex.: "cadastros.equipes" = abrir o cadastro de equipes.
 * Uso numa rota: ->middleware('can:cadastros.equipes').
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property string $grupo
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permissao extends Model
{
    // Nome da tabela (sem isto, o Laravel tentaria "permissaos").
    protected $table = 'permissoes';

    /**
     * Papéis que têm esta permissão.
     *
     * @return BelongsToMany<Papel, $this>
     */
    public function papeis(): BelongsToMany
    {
        return $this->belongsToMany(Papel::class, 'papel_permissao', 'permissao_id', 'papel_id');
    }
}
