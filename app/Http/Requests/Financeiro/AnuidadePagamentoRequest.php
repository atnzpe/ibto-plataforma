<?php

/*
 * Confere o formulário de REGISTRAR PAGAMENTO DA ANUIDADE (#37 + #59), na ficha
 * do filiado: o ano (com valor cadastrado e não isento), o dia e a forma.
 */

namespace App\Http\Requests\Financeiro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnuidadePagamentoRequest extends FormRequest
{
    /**
     * Quem pode enviar: a rota já exige "financeiro.lancar" e o controlador
     * confere se a pessoa pode cuidar deste filiado.
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
            // Um ano que tenha valor cadastrado e NÃO seja isento. (0, e não false:
            // a regra "exists" guarda o valor como TEXTO, e false viraria a palavra
            // "false", que nunca bate com o 0 do banco: recusaria todos os anos.)
            'ano' => ['required', 'integer', Rule::exists('anuidades', 'ano')->where('isenta', 0)],
            // Dia em que o dinheiro entrou; não pode ser no futuro.
            'pago_em' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            // Uma forma de pagamento ATIVA.
            'forma_pagamento_id' => ['required', 'integer', Rule::exists('fin_formas_pagamento', 'id')->where('ativa', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ano.exists' => 'Escolha um ano com anuidade cadastrada (e não isento).',
            'pago_em.date_format' => 'Informe o dia do pagamento.',
            'pago_em.before_or_equal' => 'O dia do pagamento não pode ser no futuro.',
            'forma_pagamento_id.required' => 'Escolha a forma de pagamento.',
            'forma_pagamento_id.exists' => 'Escolha uma forma de pagamento ativa.',
        ];
    }
}
