<?php

/*
 * Papel (perfil) de usuário (#36): Aluno, Filiado, Instrutor, Administrador ou
 * Tecnologia. Cada papel tem um conjunto de permissões, configurável pela tela.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property string|null $descricao
 * @property bool $sistema
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'nome', 'descricao'])]
class Papel extends Model
{
    // Nome da tabela (sem isto, o Laravel tentaria "papels").
    protected $table = 'papeis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sistema' => 'boolean'];
    }

    /**
     * Permissões deste papel.
     *
     * @return BelongsToMany<Permissao, $this>
     */
    public function permissoes(): BelongsToMany
    {
        return $this->belongsToMany(Permissao::class, 'papel_permissao', 'papel_id', 'permissao_id');
    }

    /**
     * Usuários que têm este papel.
     *
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'papel_usuario', 'papel_id', 'user_id');
    }
}
