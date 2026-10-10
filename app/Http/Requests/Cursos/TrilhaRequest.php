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
            // Etapa de cada curso (C4b, fluxograma): dois cursos podem ter a MESMA
            // etapa e aparecem lado a lado (os "galhos"). Ex.: etapas[7] = 3.
            'etapas' => ['nullable', 'array'],
            'etapas.*' => ['integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Os cursos com a ETAPA de cada um, como a tabela curso_trilha guarda.
     * A etapa informada vale; sem ela, vale a posição na lista (1, 2, 3...).
     * Ex.: [7 => ['ordem' => 1], 3 => ['ordem' => 2], 9 => ['ordem' => 2]]
     *
     * @return array<int, array{ordem: int}>
     */
    public function cursosEmOrdem(): array
    {
        $etapas = (array) $this->input('etapas', []);
        $ordem = [];
        foreach (array_values((array) $this->input('cursos', [])) as $posicao => $cursoId) {
            // Etapa escolhida para este curso, ou a posição na lista (começando em 1).
            $etapa = $etapas[$cursoId] ?? null;
            $ordem[(int) $cursoId] = ['ordem' => is_numeric($etapa) ? (int) $etapa : $posicao + 1];
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
