<?php

/*
 * Confere o formulário de TRILHA (#39): nome único, descrição, ativa e os
 * cursos NA ORDEM em que foram escolhidos (a 1ª da lista é a 1ª etapa).
 */

namespace App\Http\Requests\Cursos;

use App\Models\Trilha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrilhaRequest extends FormRequest
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
        $trilha = $this->route('trilha');

        return [
            'nome' => ['required', 'string', 'max:120', Rule::unique('trilhas', 'nome')->ignore($trilha instanceof Trilha ? $trilha->id : null)],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'ativa' => ['required', 'boolean'],
            // Os cursos em ordem; pelo menos 1, sem repetir.
            'cursos' => ['required', 'array', 'min:1', 'max:50'],
            'cursos.*' => ['integer', 'distinct', Rule::exists('cursos', 'id')],
        ];
    }

    /**
     * Os cursos com a posição de cada um, como a tabela curso_trilha guarda.
     * Ex.: [7 => ['ordem' => 1], 3 => ['ordem' => 2]]
     *
     * @return array<int, array{ordem: int}>
     */
    public function cursosEmOrdem(): array
    {
        $ordem = [];
        foreach (array_values((array) $this->input('cursos', [])) as $posicao => $cursoId) {
            // A posição na lista vira a etapa (começando em 1).
            $ordem[(int) $cursoId] = ['ordem' => $posicao + 1];
        }

        return $ordem;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da trilha.',
            'nome.unique' => 'Já existe uma trilha com este nome.',
            'cursos.required' => 'Escolha pelo menos um curso para a trilha.',
            'cursos.*.distinct' => 'Um curso não pode aparecer duas vezes na mesma trilha.',
        ];
    }
}
