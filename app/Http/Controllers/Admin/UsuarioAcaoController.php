<?php

/*
 * AÇÕES NA CONTA, na tela de Usuários (#36; pedido do Gleyson em 10/10/2026):
 * - Desativar / Reativar: "apagar sem apagar" (soft delete). A conta fica
 *   guardada, mas não entra (porteiro BloquearContaDesativada);
 * - Enviar link de nova senha: o administrador NUNCA vê nem digita a senha;
 *   a pessoa recebe um link e cria a dela (o mesmo do "Esqueci minha senha");
 * - Enviar convite: para quem tem e-mail e ainda não criou a senha.
 *
 * Só quem tem "usuarios.gerenciar" (routes/web.php). Cada ação vai para a
 * auditoria, só com números e códigos (LGPD).
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\ConviteFiliado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class UsuarioAcaoController extends Controller
{
    /**
     * Desativa a conta: a pessoa não entra mais, mas nada é apagado.
     *
     * @param  Request  $request  Quem está desativando.
     * @param  User  $usuario  A conta do endereço. Ex.: /admin/usuarios/7/desativar
     * @return RedirectResponse Volta para a lista com o resultado.
     */
    public function desativar(Request $request, User $usuario): RedirectResponse
    {
        // A própria conta não: a pessoa ficaria trancada do lado de fora.
        if ($usuario->id === $request->user()?->id) {
            return back()->withErrors(['conta' => 'Você não pode desativar a sua própria conta. (código CONTA_PROPRIA)']);
        }

        // Conta com o papel Tecnologia: só quem mexe nas permissões desativa
        // (mesma regra dos papéis: evita um Administrador trancar a Tecnologia).
        if ($usuario->papeis()->where('slug', 'tecnologia')->exists() && ! $request->user()?->can('permissoes.gerenciar')) {
            return back()->withErrors(['conta' => 'Só a Tecnologia pode desativar esta conta. (código CONTA_PROTEGIDA)']);
        }

        // Já desativada: nada a fazer (a data original fica).
        if ($usuario->desativada_em === null) {
            $usuario->forceFill(['desativada_em' => now()])->save();
            Auditoria::registrar('CONTA_DESATIVADA', ['usuario_id' => $usuario->id]);
        }

        return to_route('admin.usuarios.index')
            ->with('sucesso', "Conta de \"{$usuario->name}\" desativada. Ela não consegue mais entrar; nada foi apagado.");
    }

    /**
     * Reativa a conta: a pessoa volta a poder entrar.
     *
     * @param  User  $usuario  A conta do endereço.
     * @return RedirectResponse Volta para a lista com o resultado.
     */
    public function reativar(User $usuario): RedirectResponse
    {
        // Só registra quando estava desativada de fato.
        if ($usuario->desativada_em !== null) {
            $usuario->forceFill(['desativada_em' => null])->save();
            Auditoria::registrar('CONTA_REATIVADA', ['usuario_id' => $usuario->id]);
        }

        return to_route('admin.usuarios.index')
            ->with('sucesso', "Conta de \"{$usuario->name}\" reativada.");
    }

    /**
     * Manda para a pessoa o link de criar uma senha nova (vale 60 minutos).
     *
     * @param  User  $usuario  A conta do endereço.
     * @return RedirectResponse Volta para a lista com o resultado.
     */
    public function novaSenha(User $usuario): RedirectResponse
    {
        // Sem e-mail (filiado importado incompleto) ou desativada: não tem como / não deve.
        if (blank($usuario->email) || $usuario->desativada_em !== null) {
            return back()->withErrors(['conta' => 'Esta conta está sem e-mail ou desativada: não dá para mandar o link. (código CONTA_SEM_LINK)']);
        }

        // O mesmo envio do "Esqueci minha senha". O Laravel guarda só o "hash" do
        // código do link e não deixa mandar outro em menos de 60 segundos.
        $resultado = Password::sendResetLink(['email' => $usuario->email]);

        // Mandou há menos de 1 minuto: avisa em vez de mandar de novo.
        if ($resultado === Password::RESET_THROTTLED) {
            return back()->withErrors(['conta' => 'O link acabou de ser enviado. Espere 1 minuto para mandar de novo. (código LINK_RECENTE)']);
        }

        Auditoria::registrar('SENHA_LINK_ENVIADO', ['usuario_id' => $usuario->id]);

        return to_route('admin.usuarios.index')
            ->with('sucesso', "Link de nova senha enviado para o e-mail de \"{$usuario->name}\".");
    }

    /**
     * Manda o convite (criar a senha e ativar a conta) por e-mail.
     *
     * @param  User  $usuario  A conta do endereço.
     * @return RedirectResponse Volta para a lista com o resultado.
     */
    public function convite(User $usuario): RedirectResponse
    {
        // Só para quem tem e-mail, está ativo e ainda não criou a senha (e-mail não confirmado).
        if (blank($usuario->email) || $usuario->desativada_em !== null || $usuario->hasVerifiedEmail()) {
            return back()->withErrors(['conta' => 'O convite é só para conta ativa, com e-mail e que ainda não criou a senha. (código CONVITE_INDISPONIVEL)']);
        }

        $usuario->notify(new ConviteFiliado);
        Auditoria::registrar('CONVITE_ENVIADO', ['usuario_id' => $usuario->id]);

        return to_route('admin.usuarios.index')
            ->with('sucesso', "Convite enviado para o e-mail de \"{$usuario->name}\". O link vale por ".ConviteFiliado::DIAS_DE_VALIDADE.' dias.');
    }
}
