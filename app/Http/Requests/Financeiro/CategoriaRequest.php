<?php

/*
 * Confere o formulário de CATEGORIA do financeiro (#59): nome, tipo (receita ou
 * despesa) e se está ativa. Regra: uma categoria que JÁ FOI USADA em algum
 * lançamento não troca de tipo (os relatórios antigos ficariam errados).
 */

namespace App\Http\Requests\Financeiro;

use App\Models\FinCategoria;
use App\Models\FinLancamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoriaRequest extends FormRequest
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
        // A categoria do endereço, na edição (null no cadastro novo).
        $categoria = $this->route('categoria');
        $id = $categoria instanceof FinCategoria ? $categoria->id : null;

        return [
            // Nome único DENTRO do mesmo tipo (pode existir "Outras" em receita e em despesa).
            'nome' => [
                'required', 'string', 'max:100',
                Rule::unique('fin_categorias', 'nome')->where('tipo', $this->input('tipo'))->ignore($id),
            ],
            'tipo' => ['required', Rule::in(['receita', 'despesa'])],
            'ativa' => ['required', 'boolean'],
        ];
    }

    /**
     * Conferência extra: categoria já usada não troca de tipo.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categoria = $this->route('categoria');
                // Cadastro novo (sem categoria no endereço): não há o que conferir.
                if (! $categoria instanceof FinCategoria) {
                    return;
                }

                // Proibido quando o tipo MUDA e a categoria JÁ TEM lançamentos (Gleyson).
                // A comparação barata vem primeiro: o banco só é consultado se o tipo mudou.
                $trocaProibida = $this->input('tipo') !== $categoria->tipo && FinLancamento::where('categoria_id', $categoria->id)->exists();

                // Proibida: mostra o erro no campo "tipo" (com o código).
                if ($trocaProibida) {
                    $validator->errors()->add('tipo', 'Esta categoria já tem lançamentos: o tipo não pode mudar. Crie uma categoria nova. (CATEGORIA_EM_USO)');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da categoria.',
            'nome.unique' => 'Já existe uma categoria com este nome neste tipo.',
            'tipo.in' => 'Escolha receita ou despesa.',
        ];
    }
}
