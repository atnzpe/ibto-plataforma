<?php

/*
 * Confere o formulário de BAIXA (marcar como pago) do financeiro (#59):
 * o dia do pagamento e a forma (PIX, dinheiro...).
 */

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BaixaRequest extends FormRequest
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
            // Dia do pagamento; não pode ser no futuro (dinheiro que ainda não entrou não é baixa).
            'pago_em' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            // Quanto entrou, como texto ("300,00"); vazio = o valor todo. A conversão
            // fica no controlador e a regra (maior que zero, até o total) no LivroCaixa.
            'valor_pago' => ['nullable', 'string', 'max:20'],
            // Uma forma de pagamento ATIVA que exista.
            'forma_pagamento_id' => ['required', 'integer', Rule::exists('fin_formas_pagamento', 'id')->where('ativa', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pago_em.date_format' => 'Informe o dia do pagamento.',
            'pago_em.before_or_equal' => 'O dia do pagamento não pode ser no futuro.',
            'forma_pagamento_id.required' => 'Escolha a forma de pagamento.',
            'forma_pagamento_id.exists' => 'Escolha uma forma de pagamento ativa.',
        ];
    }
}
