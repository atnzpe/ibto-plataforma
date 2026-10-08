<?php

namespace App\Models;

use App\Notifications\ConfirmarEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * Permissões já buscadas no banco nesta requisição (ver temPermissao()).
     * null = ainda não buscou.
     *
     * @var array<int, string>|null
     */
    private ?array $slugsDasPermissoes = null;

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

    /**
     * Papéis desta pessoa (#36). Ex.: Administrador e Instrutor.
     *
     * @return BelongsToMany<Papel, $this>
     */
    public function papeis(): BelongsToMany
    {
        return $this->belongsToMany(Papel::class, 'papel_usuario', 'user_id', 'papel_id');
    }

    /**
     * Diz se algum papel desta pessoa tem a permissão pedida.
     *
     * Na primeira pergunta, busca no banco TODAS as permissões dela de uma vez e
     * guarda (como o PDV que carrega a tabela de preços uma vez por venda, e não
     * a cada item); as próximas perguntas usam o que ficou guardado.
     *
     * @param  string  $slug  A permissão. Ex.: 'cadastros.equipes'
     * @return bool true se algum papel dela tem essa permissão.
     */
    public function temPermissao(string $slug): bool
    {
        $this->slugsDasPermissoes ??= Permissao::query()
            // Permissões ligadas a algum papel que esta pessoa tem.
            ->whereHas('papeis.usuarios', fn ($consulta) => $consulta->whereKey($this->id))
            // Só a coluna slug; cada linha vira o texto do slug (ex.: 'cadastros.areas').
            ->get(['slug'])
            ->map(fn (Permissao $permissao): string => $permissao->slug)
            ->values()
            ->all();

        return in_array($slug, $this->slugsDasPermissoes, true);
    }

    /**
     * Chave de emergência: e-mail da lista ADMIN_EMAILS do .env, já confirmado.
     * Tem todas as permissões, para ninguém ficar trancado do lado de fora se
     * alguém tirar as permissões da Tecnologia sem querer.
     *
     * A confirmação do e-mail é obrigatória: sem ela, qualquer pessoa poderia
     * criar uma conta com o e-mail de um administrador e entrar.
     *
     * @return bool true se é chave de emergência.
     */
    public function ehChaveDeEmergencia(): bool
    {
        // E-mail não confirmado: não é chave, mesmo estando na lista.
        if (! $this->hasVerifiedEmail()) {
            return false;
        }

        return in_array(strtolower($this->email), config('app.admin_emails'), true);
    }
}
