<?php

/*
 * Validação do formulário de nível (#37), no SERVIDOR.
 *
 * Além do formato dos campos, confere a REGRA DE NEGÓCIO do grau, que vem de
 * CategoriaNivel::grauMaximo(): Praticante não tem grau; Monitor vai até II;
 * Instrutor e Instrutor Expert, até V. E não deixa repetir um nível.
 */

namespace App\Http\Requests\Cadastros;

use App\Enums\CategoriaNivel;
use App\Models\Nivel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NivelRequest extends FormRequest
{
    /**
     * Regras de formato de cada campo.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Obrigatória e só uma das 4 categorias do enum.
            'categoria' => ['required', Rule::enum(CategoriaNivel::class)],
            // Opcional aqui (o Praticante não tem); o limite por categoria é conferido em after().
            'grau' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * Regras que dependem de mais de um campo (rodam depois das de formato).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                // Se o formato já falhou (ex.: categoria inválida), não tem como conferir o resto.
                if ($validador->errors()->isNotEmpty()) {
                    return;
                }

                $categoria = CategoriaNivel::from($this->string('categoria')->toString());
                // Grau como número, ou null quando veio vazio.
                $grau = $this->filled('grau') ? $this->integer('grau') : null;
                $maximo = $categoria->grauMaximo();

                // Categoria sem grau (Praticante) recebeu um grau: recusa.
                if ($maximo === 0 && $grau !== null) {
                    $validador->errors()->add('grau', "{$categoria->value} não tem grau.");

                    return;
                }

                // Categoria com grau, mas o grau não veio: recusa.
                if ($maximo > 0 && $grau === null) {
                    $validador->errors()->add('grau', "Informe o grau de {$categoria->value}.");

                    return;
                }

                // Grau acima do máximo da categoria (ex.: Monitor IV): recusa.
                if ($grau !== null && $grau > $maximo) {
                    $validador->errors()->add('grau', "{$categoria->value} vai só até o grau {$maximo}.");

                    return;
                }

                // Não pode repetir: procura outro nível com a mesma categoria e o
                // mesmo grau (na edição, ignora o próprio nível).
                $nivelAtual = $this->route('nivel');
                // Número do nível em edição, ou null quando é um nível novo.
                $idAtual = $nivelAtual instanceof Nivel ? $nivelAtual->id : null;
                $repetido = Nivel::query()
                    ->where('categoria', $categoria->value)
                    // Grau vazio precisa de "whereNull": no banco, null = null não é verdadeiro.
                    ->when($grau === null, fn ($consulta) => $consulta->whereNull('grau'), fn ($consulta) => $consulta->where('grau', $grau))
                    ->when($idAtual !== null, fn ($consulta) => $consulta->whereKeyNot($idAtual))
                    ->exists();

                if ($repetido) {
                    $validador->errors()->add('grau', 'Esse nível já está cadastrado.');
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
            'categoria.required' => 'Escolha a categoria.',
            'categoria.enum' => 'Categoria inválida.',
            'grau.integer' => 'O grau precisa ser um número de 1 a 5.',
            'grau.min' => 'O grau precisa ser um número de 1 a 5.',
            'grau.max' => 'O grau precisa ser um número de 1 a 5.',
            'ativo.required' => 'Informe se o nível está ativo.',
            'ativo.boolean' => 'Valor inválido para "ativo".',
        ];
    }
}
