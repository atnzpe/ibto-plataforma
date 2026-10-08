<?php

namespace App\Providers;

use App\Models\Filiado;
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

        // "filiados.acessar" (#37, F3b): abrir o cadastro de filiados. Passa quem
        // cuida de TODOS (filiados.gerenciar) ou só da PRÓPRIA equipe (filiados.equipe).
        Gate::define('filiados.acessar', fn (User $usuario): bool => $usuario->temPermissao('filiados.gerenciar')
            || $usuario->temPermissao('filiados.equipe'));

        // "filiados.editar" (#37, F3b): mexer num filiado específico.
        Gate::define('filiados.editar', function (User $usuario, Filiado $filiado): bool {
            // Quem cuida de todos os filiados pode mexer em qualquer um.
            if ($usuario->temPermissao('filiados.gerenciar')) {
                return true;
            }

            // Sem a permissão da própria equipe: não pode.
            if (! $usuario->temPermissao('filiados.equipe')) {
                return false;
            }

            // Equipe de quem está logado (vem do registro de filiado dele); sem equipe, não pode.
            $minhaEquipe = $usuario->filiado?->equipe_id;
            if ($minhaEquipe === null) {
                return false;
            }

            // Pode mexer só se o filiado é da MESMA equipe (como o gerente da
            // unidade que só abre a ficha dos alunos da própria unidade).
            return $filiado->equipe_id === $minhaEquipe;
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
