<?php

/*
 * Validação do CADASTRO DE CUPOM (#89): código sem repetir (só letras e
 * números), tipo, valor coerente com o tipo, validade de/até e limites.
 */

namespace App\Http\Requests\Financeiro;

use App\Models\Cupom;
use App\Support\Cupons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CupomRequest extends FormRequest
{
    /**
     * Antes de validar: o código vai sem espaços e em maiúsculas (como é gravado).
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['codigo' => Cupons::normalizar((string) $this->input('codigo', ''))]);
    }

    /**
     * As regras de cada campo.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // O cupom em edição (para o "sem repetir" ignorar ele mesmo); no novo, null.
        $cupomAtual = $this->route('cupom');
        $idAtual = $cupomAtual instanceof Cupom ? $cupomAtual->id : null;

        return [
            // Ex.: 'FILIADO10'. Só letras e números, de 3 a 30.
            'codigo' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9]+$/', Rule::unique('cupons', 'codigo')->ignore($idAtual)],
            'tipo' => ['required', Rule::in(['percentual', 'valor'])],
            // Percentual: 1 a 100. Valor fixo: o texto em reais (convertido em centavos no controlador).
            'valor' => ['required', 'string', 'max:20'],
            'valido_de' => ['nullable', 'date'],
            'valido_ate' => ['nullable', 'date', 'after:valido_de'],
            'limite_total' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limite_por_pessoa' => ['required', 'integer', 'min:1', 'max:100'],
            'so_filiados' => ['sometimes', 'boolean'],
            'todos_cursos' => ['sometimes', 'boolean'],
            'ativo' => ['sometimes', 'boolean'],
            // Os cursos (quando não vale em todos).
            'cursos' => ['nullable', 'array', 'max:200'],
            'cursos.*' => ['integer', 'distinct', Rule::exists('cursos', 'id')],
        ];
    }

    /**
     * Conferências que dependem de mais de um campo.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Valor coerente com o tipo: percentual de 1 a 100; valor fixo maior que zero.
                $centavos = $this->valorEmUnidades();
                if ($this->input('tipo') === 'percentual' && ($centavos < 1 || $centavos > 100)) {
                    $validator->errors()->add('valor', 'O percentual vai de 1 a 100.');
                }
                if ($this->input('tipo') === 'valor' && $centavos < 1) {
                    $validator->errors()->add('valor', 'Informe o valor do desconto. Ex.: 50,00.');
                }
                // Não vale em todos os cursos: precisa marcar pelo menos um.
                if (! $this->boolean('todos_cursos') && count((array) $this->input('cursos', [])) === 0) {
                    $validator->errors()->add('cursos', 'Marque os cursos em que o cupom vale, ou "Vale em todos os cursos".');
                }
            },
        ];
    }

    /**
     * O valor no que se grava: percentual inteiro (ex.: '10' → 10) ou centavos (ex.: '50,00' → 5000).
     *
     * @return int Ex.: 5000.
     */
    public function valorEmUnidades(): int
    {
        $texto = trim((string) $this->input('valor', ''));

        // Percentual: só o número inteiro.
        if ($this->input('tipo') === 'percentual') {
            return (int) preg_replace('/\D/', '', $texto);
        }

        // Reais em centavos: "1.234,56" → 123456; "50" → 5000.
        $limpo = str_replace(['.', ' '], '', $texto);
        $partes = explode(',', $limpo);
        $reais = (int) preg_replace('/\D/', '', $partes[0]);
        $centavos = isset($partes[1]) ? (int) str_pad(substr((string) preg_replace('/\D/', '', $partes[1]), 0, 2), 2, '0') : 0;

        return $reais * 100 + $centavos;
    }
}
