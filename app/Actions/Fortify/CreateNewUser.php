<?php

/*
 * CADASTRO PÚBLICO (tela /register): cria a conta de quem se cadastra pelo site.
 * Desde a #36, a conta já nasce com o papel "Aluno", porque o cadastro público
 * é de quem vem fazer cursos abertos ao público (decisão do Gleyson, 08/10/2026).
 */

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Confere os dados do formulário, cria a conta e dá o papel "Aluno".
     *
     * @param  array<string, string>  $input  O que a pessoa digitou. Ex.: ['name' => 'Ana', 'email' => 'ana@ibto.org', 'password' => '...']
     * @return User A conta criada.
     */
    public function create(array $input): User
    {
        // Confere nome, e-mail (sem repetir) e senha forte; se algo estiver
        // errado, para aqui e a tela mostra o erro embaixo do campo.
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        // Cria a conta (a senha é guardada embaralhada, pelo cast do User).
        $usuario = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Busca o papel de aluno pelo slug 'aluno' (o apelido fixo do sistema; o nome
        // "Aluno" da tela pode mudar, o slug não). first(): o papel, ou null se não existir.
        $papelAluno = Papel::where('slug', 'aluno')->first();

        // Achou o papel: liga a conta a ele (a tabela papel_usuario ganha uma linha).
        if ($papelAluno !== null) {
            $usuario->papeis()->attach($papelAluno->id);
        } else {
            // Não achou (alguém apagou o papel?): a conta é criada mesmo assim,
            // mas fica registrado no log, para não passar em silêncio (#58).
            Log::error('PAPEL_ALUNO_AUSENTE', ['usuario_id' => $usuario->id]);
        }

        return $usuario;
    }
}
