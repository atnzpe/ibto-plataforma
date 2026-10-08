<?php

/*
 * Página do CONVITE do filiado (#37, bloco F3): a pessoa abre o link do e-mail,
 * cria a própria senha e já entra logada.
 *
 * As rotas exigem o link ASSINADO (middleware "signed"): endereço alterado ou
 * vencido (mais de 7 dias) dá 403. O link só vale uma vez: depois que a senha
 * foi criada, o e-mail fica confirmado e o convite passa a valer como "já usado".
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ConviteController extends Controller
{
    /**
     * Mostra o formulário "crie a sua senha".
     *
     * @param  Request  $request  O pedido (com a assinatura no endereço).
     * @param  User  $usuario  A pessoa do convite (ex.: /convite/7).
     * @return Response|RedirectResponse O formulário, ou a tela de entrar se o convite já foi usado.
     */
    public function mostrar(Request $request, User $usuario): Response|RedirectResponse
    {
        if ($this->jaUsado($usuario)) {
            return to_route('login')->with('status', 'Este convite já foi usado. Entre com o seu e-mail e senha.');
        }

        return Inertia::render('auth/convite', [
            'nome' => $usuario->name,
            // Para onde o formulário envia: o MESMO endereço assinado (a assinatura vale para os dois).
            'acao' => $request->fullUrl(),
        ]);
    }

    /**
     * Grava a senha, confirma o e-mail e entra.
     *
     * @param  Request  $request  O formulário: password e password_confirmation.
     * @param  User  $usuario  A pessoa do convite.
     * @return RedirectResponse Vai para o painel, já logada.
     */
    public function salvar(Request $request, User $usuario): RedirectResponse
    {
        if ($this->jaUsado($usuario)) {
            return to_route('login')->with('status', 'Este convite já foi usado. Entre com o seu e-mail e senha.');
        }

        $request->validate(
            // Mesma regra de senha do resto da plataforma (forte em produção) e digitada duas vezes iguais.
            ['password' => ['required', 'string', Password::defaults(), 'confirmed']],
            [
                'password.required' => 'Crie uma senha.',
                'password.confirmed' => 'As duas senhas não são iguais.',
            ],
        );

        // forceFill: senha e confirmação do e-mail não são campos de formulário comuns.
        $usuario->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            // O link chegou na caixa de e-mail da pessoa: isso confirma o e-mail.
            'email_verified_at' => now(),
        ])->save();

        Auth::login($usuario);
        // Troca o "crachá" da sessão depois de entrar (protege contra roubo de sessão).
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    /**
     * Diz se o convite já foi usado.
     *
     * @return bool true quando a pessoa já criou a senha (e-mail confirmado).
     */
    private function jaUsado(User $usuario): bool
    {
        // Depois de criar a senha, o e-mail fica confirmado (data preenchida).
        // Mesmo resultado de $usuario->hasVerifiedEmail().
        return $usuario->email_verified_at !== null;
    }
}
