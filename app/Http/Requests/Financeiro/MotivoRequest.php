<?php

/*
 * Confere o formulário de CANCELAR ou ESTORNAR um lançamento (#59): os dois
 * exigem um motivo de verdade. O estorno também pede o dia em que o dinheiro voltou.
 */

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;

class MotivoRequest extends FormRequest
{
    /**
     * Quem pode enviar: a rota já exige "financeiro.lancar".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Pelo menos 5 letras: "erro" não explica nada para quem ler depois.
            'motivo' => ['required', 'string', 'min:5', 'max:300'],
            // Só no estorno: o dia da devolução (vazio no cancelamento; hoje se não vier).
            'data' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Explique o motivo.',
            'motivo.min' => 'Explique o motivo (pelo menos 5 letras).',
            'data.before_or_equal' => 'O dia do estorno não pode ser no futuro.',
        ];
    }
}
