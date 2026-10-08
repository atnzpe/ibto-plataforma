<?php

/*
 * Validação do formulário de equipe (#37), no SERVIDOR.
 *
 * Regra de negócio (Gleyson, 07/10/2026): "em homologação" pode estar
 * incompleta; para ficar ATIVA, a equipe precisa de cidade, estado, nome e
 * telefone do responsável e pelo menos uma área de atuação (ver after()).
 * LGPD: telefone, e-mail e documento são conferidos aqui e gravados
 * criptografados (Model Equipe).
 */

namespace App\Http\Requests\Cadastros;

use App\Enums\StatusEquipe;
use App\Models\Equipe;
use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EquipeRequest extends FormRequest
{
    // As 27 siglas de estado (UF) do Brasil.
    public const UFS = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
        'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    /**
     * Arruma os dados antes de conferir: telefone e documento só com números
     * (ex.: "(81) 99999-0000" vira "81999990000") e UF em maiúsculas.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'responsavel_telefone' => $this->filled('responsavel_telefone')
                ? Documento::apenasDigitos($this->string('responsavel_telefone')->toString()) : null,
            'documento' => $this->filled('documento')
                ? Documento::apenasDigitos($this->string('documento')->toString()) : null,
            'uf' => $this->filled('uf') ? strtoupper($this->string('uf')->toString()) : null,
        ]);
    }

    /**
     * Regras de formato de cada campo.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Na edição, a equipe do endereço; numa nova, null.
        $equipeAtual = $this->route('equipe');

        return [
            'nome' => ['required', 'string', 'max:150',
                Rule::unique('equipes', 'nome')->ignore($equipeAtual instanceof Equipe ? $equipeAtual->id : null)],
            'status' => ['required', Rule::enum(StatusEquipe::class)],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', Rule::in(self::UFS)],
            'responsavel_nome' => ['nullable', 'string', 'max:150'],
            // Telefone com DDD: 10 dígitos (fixo) ou 11 (celular).
            'responsavel_telefone' => ['nullable', 'digits_between:10,11'],
            'responsavel_email' => ['nullable', 'email', 'max:150'],
            // CPF (11) ou CNPJ (14) com a conta dos dígitos verificadores certa.
            'documento' => ['nullable', function (string $campo, mixed $valor, Closure $falhar): void {
                if (! is_string($valor) || ! Documento::valido($valor)) {
                    $falhar('CPF ou CNPJ inválido. Confira os números.');
                }
            }],
            // Lista de áreas (números), cada uma precisa existir no cadastro de áreas.
            'areas' => ['nullable', 'array'],
            'areas.*' => ['integer', 'distinct', Rule::exists('areas_atuacao', 'id')],
            // Logo: mesma regra da imagem das áreas (sem SVG, até 2 MB).
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Regra para ATIVAR: roda depois das regras de formato.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                // Só vale quando a pessoa escolheu "Ativa"; em homologação ou inativa, pode faltar dado.
                if ($this->input('status') !== StatusEquipe::Ativa->value) {
                    return;
                }

                // Campos sem os quais a equipe não pode ficar ativa (Gleyson, 07/10/2026):
                // onde ela fica e com quem falar (o telefone garante o contato).
                $obrigatoriosParaAtivar = ['cidade', 'uf', 'responsavel_nome', 'responsavel_telefone'];
                // Pelo menos uma área de atuação.
                $minimoDeAreas = 1;

                // Para cada campo obrigatório vazio, uma mensagem embaixo dele.
                foreach ($obrigatoriosParaAtivar as $campo) {
                    if (blank($this->input($campo))) {
                        $validador->errors()->add($campo, 'Preencha este campo para ativar a equipe.');
                    }
                }

                // Poucas áreas escolhidas: avisa no campo das áreas.
                if (count((array) $this->input('areas', [])) < $minimoDeAreas) {
                    $validador->errors()->add('areas', 'Escolha pelo menos uma área de atuação para ativar a equipe.');
                }
            },
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
            'nome.required' => 'Informe o nome da equipe.',
            'nome.max' => 'O nome pode ter no máximo 150 caracteres.',
            'nome.unique' => 'Já existe uma equipe com esse nome.',
            'status.required' => 'Escolha a situação da equipe.',
            'status.enum' => 'Situação inválida.',
            'uf.in' => 'Escolha um estado da lista.',
            'responsavel_telefone.digits_between' => 'Telefone com DDD: 10 ou 11 números.',
            'responsavel_email.email' => 'E-mail inválido.',
            'areas.*.exists' => 'Uma das áreas escolhidas não existe mais.',
            'logo.image' => 'O arquivo precisa ser uma imagem.',
            'logo.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'logo.max' => 'A logo pode ter no máximo 2 MB.',
        ];
    }
}
