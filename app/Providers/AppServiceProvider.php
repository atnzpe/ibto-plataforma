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
     * "administrar" (provisório, #37, até a #36 trazer os papéis): libera os
     * cadastros de apoio só para quem está na lista ADMIN_EMAILS do .env E já
     * confirmou o e-mail. A confirmação é obrigatória: sem ela, qualquer pessoa
     * poderia criar uma conta com o e-mail de um administrador e entrar.
     * Uso numa rota: ->middleware('can:administrar').
     */
    protected function configurarPermissoes(): void
    {
        Gate::define('administrar', function (User $usuario): bool {
            // E-mail ainda não confirmado: barra, mesmo que esteja na lista.
            if (! $usuario->hasVerifiedEmail()) {
                return false;
            }

            // Confirmado: passa só se o e-mail (em minúsculas) estiver na lista.
            return in_array(strtolower($usuario->email), config('app.admin_emails'), true);
        });
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
