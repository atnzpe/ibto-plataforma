<?php

/*
 * DUAS ETAPAS OBRIGATÓRIAS PARA A EQUIPE (07/11/2026): decide se uma pessoa
 * precisa ligar a verificação em duas etapas antes de usar a plataforma.
 * A regra fica num lugar só, usada pelo porteiro (middleware ExigirDuasEtapas)
 * e pela tela de Segurança (para explicar o aviso).
 */

namespace App\Support;

use App\Models\User;

class DuasEtapas
{
    /**
     * Esta pessoa está barrada até ligar a segunda etapa?
     *
     * @param  User  $usuario  Quem está logado. Ex.: a conta da tesouraria.
     * @return bool true = precisa ligar antes de seguir; false = pode usar normalmente.
     */
    public static function pendentePara(User $usuario): bool
    {
        // Regra desligada no .env: ninguém é barrado.
        if (! config('seguranca.exigir_2fa_equipe')) {
            return false;
        }

        // Já tem a segunda etapa (código do autenticador confirmado) ou uma chave
        // de acesso (passkey, que já é mais forte que senha): está protegido.
        if ($usuario->hasEnabledTwoFactorAuthentication() || $usuario->passkeys()->exists()) {
            return false;
        }

        // A chave de emergência (e-mail do ADMIN_EMAILS) pode tudo: conta como equipe.
        if ($usuario->ehChaveDeEmergencia()) {
            return true;
        }

        // Sem proteção extra: só é barrado se algum papel dele tiver uma permissão
        // sensível. temPermissao() confere direto no papel (e não can()), porque
        // algumas regras, como "editar filiado", pedem também QUAL filiado.
        foreach (config('seguranca.permissoes_sensiveis', []) as $permissao) {
            if ($usuario->temPermissao($permissao)) {
                return true;
            }
        }

        // Aluno, instrutor comum ou filiado sem acesso sensível: segue livre.
        return false;
    }
}
