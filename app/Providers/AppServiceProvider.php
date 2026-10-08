<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurarPermissoes();
    }

    /**
     * Regras de permissão ("Gates": portões que dizem quem pode passar).
     *
     * Gate::before roda ANTES de qualquer conferência (#36): é o "porteiro geral".
     * - Chave de emergência (e-mail do ADMIN_EMAILS, confirmado): passa sempre.
     * - Quem tem a permissão pedida por algum dos seus papéis: passa.
     * - Senão, devolve null: "não decido aqui", e o Laravel segue para as
     *   outras regras (se nenhuma liberar, a resposta é 403 Proibido).
     * Uso numa rota: ->middleware('can:cadastros.equipes').
     */
    protected function configurarPermissoes(): void
    {
        Gate::before(function (User $usuario, string $habilidade): ?bool {
            // Chave de emergência: ninguém fica trancado do lado de fora.
            if ($usuario->ehChaveDeEmergencia()) {
                return true;
            }

            // Tem a permissão: libera (true). Não tem: "não decido aqui" (null),
            // para o Laravel continuar procurando (como o porteiro que liga para o apartamento).
            return $usuario->temPermissao($habilidade) ? true : null;
        });

        // "administrar" (provisório, #37): as rotas de cadastro ainda pedem esta
        // habilidade até o bloco 2 da #36. Só a chave de emergência passa por ela
        // (o Gate::before acima já liberou), então aqui a resposta é sempre "não".
        Gate::define('administrar', fn (User $usuario): bool => false);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
