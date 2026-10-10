<?php

/*
 * Confere o formulário de MÓDULO (#39): título, nível mínimo (opcional) e se
 * está ativo.
 */

namespace App\Http\Requests\Cursos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModuloRequest extends FormRequest
{
    /**
     * Quem pode enviar: a rota já exige "cursos.gerenciar".
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
            'titulo' => ['required', 'string', 'max:150'],
            // Nível que exista; vazio = todos os inscritos veem.
            'nivel_minimo_id' => ['nullable', 'integer', Rule::exists('niveis', 'id')],
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['titulo.required' => 'Informe o nome do módulo.'];
    }
}
