<?php

/*
 * Confere o formulário de FORMA DE PAGAMENTO do financeiro (#59): nome único e
 * se está ativa.
 */

namespace App\Http\Requests\Financeiro;

use App\Models\FinFormaPagamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormaPagamentoRequest extends FormRequest
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
        // A forma do endereço, na edição (null no cadastro novo).
        $forma = $this->route('forma');

        return [
            // Nome único. Ex.: não podem existir dois "PIX".
            'nome' => ['required', 'string', 'max:50', Rule::unique('fin_formas_pagamento', 'nome')->ignore($forma instanceof FinFormaPagamento ? $forma->id : null)],
            'ativa' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da forma de pagamento.',
            'nome.unique' => 'Já existe uma forma de pagamento com este nome.',
        ];
    }
}
