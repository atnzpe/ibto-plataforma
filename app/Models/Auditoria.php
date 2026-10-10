<?php

/*
 * Linha da AUDITORIA (#36): quem mudou o quê e quando. Só se cria; nunca se
 * altera nem se apaga pela plataforma. LGPD: só números e códigos.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $acao
 * @property array<string, mixed>|null $detalhes
 * @property Carbon|null $created_at
 * @property-read User|null $usuario Quem fez (null se a conta foi apagada).
 */
class Auditoria extends Model
{
    protected $table = 'auditoria';

    // A tabela só tem created_at (sem updated_at): registro não muda depois de criado.
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // JSON do banco vira lista do PHP (e o contrário, ao gravar).
        return ['detalhes' => 'array'];
    }

    /**
     * Quem fez a ação (vazio se a conta foi apagada; a linha continua).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Grava uma linha de auditoria em nome de quem está logado.
     *
     * @param  string  $acao  Código estável. Ex.: 'PERMISSAO_CONCEDIDA'
     * @param  array<string, mixed>  $detalhes  Só códigos e números. Ex.: ['papel' => 'administrador', 'permissao' => 'cadastros.equipes']
     */
    public static function registrar(string $acao, array $detalhes = []): void
    {
        $linha = new self;
        // forceFill: os campos da auditoria não vêm de formulário.
        $linha->forceFill(['user_id' => Auth::id(), 'acao' => $acao, 'detalhes' => $detalhes])->save();
    }
}
