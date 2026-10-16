<?php

/*
 * CADASTRO PÚBLICO (tela /register), com APROVAÇÃO (#84).
 *
 * O IBTO é um grupo fechado, só de indicados (Gleyson, 17/10/2026). Quem se
 * cadastra sozinho informa a EQUIPE e QUEM INDICOU, e a conta nasce "em
 * análise": entra, mas só vê a tela "Cadastro em análise" até o admin da
 * equipe ou o Comando Geral aprovar (aí ganha o papel Aluno).
 * Com CADASTRO_PUBLICO=fechado (o PADRÃO), só os e-mails da chave de partida
 * (ADMIN_EMAILS) passam, e já nascem aprovados; os outros recebem 404.
 * (Antes da #84, a conta já nascia como Aluno; decisão de 08/10/2026, revista.)
 */

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\StatusEquipe;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Confere os dados do formulário e cria a conta EM ANÁLISE.
     *
     * @param  array<string, string>  $input  O que a pessoa digitou. Ex.: ['name' => 'Ana', 'email' => 'ana@ibto.org',
     *                                        'password' => '...', 'equipe_id' => '3', 'indicado_por' => 'Mestre Trinca']
     * @return User A conta criada (situação 'em_analise').
     */
    public function create(array $input): User
    {
        // E-mail da chave de partida (ADMIN_EMAILS no .env)? Ex.: a primeira pessoa da Tecnologia.
        $daChaveDePartida = in_array(strtolower(trim((string) ($input['email'] ?? ''))), config('app.admin_emails'), true);

        // Porta fechada (o padrão): só a chave de partida passa (para a primeira instalação);
        // qualquer outro e-mail recebe 404, como se o cadastro não existisse.
        abort_if(config('app.cadastro_publico') === 'fechado' && ! $daChaveDePartida, 404);

        // Confere nome, e-mail (sem repetir), senha forte e, para quem pede acesso, a equipe
        // (existente e não inativa) e quem indicou. A chave de partida não precisa de equipe
        // (num banco novo ainda nem existem equipes).
        $exigeIndicacao = $daChaveDePartida ? 'nullable' : 'required';
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'equipe_id' => [$exigeIndicacao, 'integer', Rule::exists('equipes', 'id')->whereNot('status', StatusEquipe::Inativa->value)],
            'indicado_por' => [$exigeIndicacao, 'string', 'min:3', 'max:150'],
        ], [
            'equipe_id.required' => 'Escolha a sua equipe.',
            'equipe_id.exists' => 'Escolha uma equipe da lista.',
            'indicado_por.required' => 'Informe quem indicou você ao IBTO.',
            'indicado_por.min' => 'Informe o nome de quem indicou você.',
        ])->validate();

        // Conta + auditoria juntas: ou as duas são gravadas, ou nenhuma.
        return DB::transaction(function () use ($input, $daChaveDePartida): User {
            // A senha é guardada embaralhada (cast do User). Sem papel ainda:
            // o papel Aluno só vem na aprovação.
            $usuario = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            // A chave de partida nasce APROVADA. Sem isso, numa instalação nova a primeira
            // pessoa da Tecnologia ficaria trancada para fora (sem ninguém para aprovar).
            // forceFill: estas colunas não estão no "Fillable" (proteção contra gravação em massa).
            $usuario->forceFill([
                'situacao_cadastro' => $daChaveDePartida ? 'aprovado' : 'em_analise',
                'equipe_solicitada_id' => isset($input['equipe_id']) && $input['equipe_id'] !== '' ? (int) $input['equipe_id'] : null,
                'indicado_por' => isset($input['indicado_por']) && trim($input['indicado_por']) !== '' ? trim($input['indicado_por']) : null,
            ])->save();

            // Auditoria só com números (LGPD): quem pediu e para qual equipe.
            Auditoria::registrar('CADASTRO_SOLICITADO', ['usuario_id' => $usuario->id, 'equipe_id' => $usuario->equipe_solicitada_id]);

            return $usuario;
        });
    }
}
