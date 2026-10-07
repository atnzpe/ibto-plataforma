<?php

namespace App\Models;

use App\Notifications\ConfirmarEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Manda o e-mail de confirmação de cadastro.
     *
     * O Laravel já tem este método e chama ele sozinho depois do cadastro (e
     * quando a pessoa pede "reenviar e-mail"). Nós o substituímos para usar a
     * nossa notificação ConfirmarEmail, que vai para a FILA em vez de sair na
     * hora: se o servidor de e-mail falhar, o cadastro não dá erro 500, e o
     * Cron tenta de novo depois (até 3 vezes).
     *
     * Não recebe argumentos e não devolve nada (void).
     */
    public function sendEmailVerificationNotification(): void
    {
        // Entrega a notificação a este usuário; como ConfirmarEmail é
        // "ShouldQueue", o Laravel só guarda o pedido na tabela `jobs`.
        $this->notify(new ConfirmarEmail);
    }
}
