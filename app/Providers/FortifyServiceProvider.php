<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\StatusEquipe;
use App\Http\Responses\LoginResponse;
use App\Models\Equipe;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as RespostaDoLogin;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    // LOGIN REFORÇADO (#36): limite de tentativas de login por computador, para a trava 2.
    // 50 por minuto (Gleyson, 09/10/2026): conta TODA tentativa, até a com senha certa;
    // uma turma inteira no mesmo Wi-Fi entra com folga para erros de digitação, e um
    // robô testando centenas de e-mails trava logo no começo.
    private const LOGIN_TENTATIVAS_POR_IP = 50;

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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        // Depois do login (17/10/2026): volta à página guardada só se a conta puder abri-la
        // (evita o 403 de quem entra com outra conta); senão, o início do papel dela.
        $this->app->singleton(RespostaDoLogin::class, LoginResponse::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            // "Pedir acesso" aparece com o cadastro público aberto, ou, com ele fechado, só
            // enquanto a chave de partida (ADMIN_EMAILS) estiver preenchida (#84).
            'canRegister' => config('app.cadastro_publico') !== 'fechado' || config('app.admin_emails') !== [],
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        // Cadastro público com aprovação (#84): a pessoa escolhe a equipe e diz quem indicou.
        // Com CADASTRO_PUBLICO=fechado (o padrão), a tela só existe enquanto a chave de
        // partida (ADMIN_EMAILS) estiver preenchida; vazia, 404.
        Fortify::registerView(function () {
            abort_if(config('app.cadastro_publico') === 'fechado' && config('app.admin_emails') === [], 404);

            return Inertia::render('auth/register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
                // As equipes que podem receber pedidos (todas, menos as inativas), em ordem de nome.
                'equipes' => Equipe::query()
                    ->where('status', '!=', StatusEquipe::Inativa->value)
                    ->orderBy('nome')
                    ->get(['id', 'nome', 'cidade', 'uf'])
                    ->map(fn (Equipe $equipe) => [
                        'id' => $equipe->id,
                        // Ex.: 'IBTO Pernambuco (Recife - PE)'.
                        'rotulo' => $equipe->cidade !== null ? "{$equipe->nome} ({$equipe->cidade} - {$equipe->uf})" : $equipe->nome,
                    ])->values(),
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // LOGIN REFORÇADO (#36): duas travas, conferidas a cada tentativa de entrar.
        RateLimiter::for('login', function (Request $request) {
            // Chave da trava 1: o e-mail digitado (minúsculo, sem acento) + o IP
            // (endereço do computador na internet). Ex.: "ana@ibto.org|200.1.2.3".
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return [
                // Trava 1: 5 tentativas por minuto para o MESMO e-mail no mesmo
                // computador. Protege a conta de quem tenta adivinhar a senha.
                Limit::perMinute(5)->by($throttleKey)
                    ->response(fn (Request $request, array $headers) => $this->loginBloqueado($request, $headers, 'email')),

                // Trava 2: soma TODOS os e-mails tentados a partir do mesmo computador.
                // Protege contra quem testa uma senha comum em muitos e-mails
                // (cada e-mail ficaria abaixo de 5 e a trava 1 nunca dispararia).
                Limit::perMinute(self::LOGIN_TENTATIVAS_POR_IP)->by('ip|'.$request->ip())
                    ->response(fn (Request $request, array $headers) => $this->loginBloqueado($request, $headers, 'ip')),
            ];
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }

    /**
     * O que acontece quando uma trava de login dispara (#36): anota no log de
     * segurança e volta para a tela de login com uma mensagem clara, com código.
     *
     * @param  Request  $request  A tentativa de login que foi barrada.
     * @param  array<string, mixed>  $headers  Cabeçalhos do Laravel; o "Retry-After" diz
     *                                         quantos segundos faltam. Ex.: ['Retry-After' => 42]
     * @param  string  $trava  Qual trava disparou: 'email' (trava 1) ou 'ip' (trava 2).
     * @return RedirectResponse De volta ao login, com o erro no campo de e-mail.
     */
    private function loginBloqueado(Request $request, array $headers, string $trava): RedirectResponse
    {
        // Quantos segundos faltam para poder tentar de novo (60 se o Laravel não disser).
        $segundos = (int) ($headers['Retry-After'] ?? 60);

        // Registro no log "seguranca" (nada em silêncio, #58). Sem e-mail nem IP
        // (LGPD): só o código, qual trava disparou e o tempo de espera.
        Log::channel('seguranca')->info('LOGIN_BLOQUEADO', [
            'trava' => $trava,
            'espera_segundos' => $segundos,
        ]);

        // Volta para o login com a mensagem embaixo do campo de e-mail; o e-mail
        // digitado continua preenchido (withInput) para a pessoa não redigitar.
        return back()
            ->withInput($request->only(Fortify::username()))
            ->withErrors([
                Fortify::username() => "Muitas tentativas de entrar. Espere {$segundos} segundos e tente de novo. (código LOGIN_BLOQUEADO)",
            ]);
    }
}
