<?php

/*
 * Validação do formulário de filiado (#37), no SERVIDOR.
 *
 * LGPD: CPF, nascimento e telefone são conferidos aqui e gravados
 * criptografados (Model Filiado). O CPF repetido é barrado pelo cpf_hash,
 * porque o CPF criptografado não dá para comparar.
 */

namespace App\Http\Requests\Cadastros;

use App\Models\Filiado;
use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiliadoRequest extends FormRequest
{
    /**
     * Arruma antes de conferir: CPF e telefone só com números, UF e e-mail no padrão.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => $this->filled('cpf') ? Documento::apenasDigitos($this->string('cpf')->toString()) : null,
            'telefone' => $this->filled('telefone') ? Documento::apenasDigitos($this->string('telefone')->toString()) : null,
            'uf' => $this->filled('uf') ? strtoupper($this->string('uf')->toString()) : null,
            'email' => $this->filled('email') ? strtolower($this->string('email')->toString()) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Na edição, o filiado do endereço; num novo, null.
        $filiado = $this->route('filiado');
        $filiadoId = $filiado instanceof Filiado ? $filiado->id : null;
        $usuarioId = $filiado instanceof Filiado ? $filiado->user_id : null;

        return [
            'nome' => ['required', 'string', 'max:150'],
            // E-mail da conta do app: único entre todas as contas (na edição, ignora a dele).
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuarioId)],
            // CPF: 11 números com a conta dos dígitos certa, e sem repetir (pelo hash).
            'cpf' => ['required', 'digits:11', function (string $campo, mixed $valor, Closure $falhar) use ($filiadoId): void {
                if (! is_string($valor) || ! Documento::valido($valor)) {
                    $falhar('CPF inválido. Confira os números.');

                    return;
                }

                $repetido = Filiado::query()
                    ->where('cpf_hash', Filiado::hashDoCpf($valor))
                    ->when($filiadoId !== null, fn ($consulta) => $consulta->whereKeyNot($filiadoId))
                    ->exists();

                if ($repetido) {
                    $falhar('Já existe um filiado com esse CPF.');
                }
            }],
            'nascimento' => [
                'required',
                'date',
                // Precisa ser ANTES de hoje (ninguém nasce amanhã).
                'before:today',
            ],
            'telefone' => ['nullable', 'digits_between:10,11'],
            'municipio' => ['required', 'string', 'max:100'],
            'uf' => ['required', Rule::in(EquipeRequest::UFS)],
            'equipe_id' => ['nullable', 'integer', Rule::exists('equipes', 'id')],
            'nivel_id' => ['nullable', 'integer', Rule::exists('niveis', 'id')],
            'areas' => ['nullable', 'array'],
            'areas.*' => ['integer', 'distinct', Rule::exists('areas_atuacao', 'id')],
            'status' => ['required', Rule::in(['ativo', 'inativo'])],
        ];
    }

    /**
     * Mensagens de erro em português.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome completo.',
            'email.required' => 'Informe o e-mail (a conta do app é criada com ele).',
            'email.email' => 'E-mail inválido.',
            'email.unique' => 'Já existe uma conta com esse e-mail.',
            'cpf.required' => 'Informe o CPF.',
            'cpf.digits' => 'O CPF tem 11 números.',
            'nascimento.required' => 'Informe a data de nascimento.',
            'nascimento.date' => 'Data de nascimento inválida.',
            'nascimento.before' => 'A data de nascimento precisa ser antes de hoje.',
            'telefone.digits_between' => 'Telefone com DDD: 10 ou 11 números.',
            'municipio.required' => 'Informe o município.',
            'uf.required' => 'Escolha o estado.',
            'uf.in' => 'Escolha um estado da lista.',
            'status.in' => 'Situação inválida.',
        ];
    }
}
