<?php

/*
 * Confere o formulário de NOVO LANÇAMENTO do financeiro (#59) no servidor,
 * antes de chegar ao LivroCaixa. O valor vem como texto ("90,00") e é
 * convertido para centavos (App\Support\Dinheiro).
 */

namespace App\Http\Requests\Financeiro;

use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LancamentoRequest extends FormRequest
{
    /**
     * Quem pode enviar: a rota já exige "financeiro.lancar" (routes/web.php).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de cada campo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Só "receita" ou "despesa".
            'tipo' => ['required', Rule::in(['receita', 'despesa'])],
            // Uma categoria ATIVA que exista (o tipo é conferido no LivroCaixa).
            'categoria_id' => ['required', 'integer', Rule::exists('fin_categorias', 'id')->where('ativa', true)],
            'descricao' => ['required', 'string', 'max:200'],
            // Texto curto; a conversão e a conferência ficam no after() abaixo.
            'valor' => ['required', 'string', 'max:20'],
            'vencimento' => ['required', 'date_format:Y-m-d'],
            // Equipe é opcional.
            'equipe_id' => ['nullable', 'integer', Rule::exists('equipes', 'id')],
            // Em quantas vezes (só no lançamento novo): vazio ou 1 = à vista; até 24.
            'parcelas' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    /**
     * Conferência extra: o valor precisa virar centavos maiores que zero.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Se o campo já falhou nas regras básicas, não repete o erro.
                if ($validator->errors()->has('valor')) {
                    return;
                }
                $centavos = Dinheiro::paraCentavos((string) $this->input('valor'));
                // Não deu para converter, ou deu zero: mostra o erro no campo.
                if ($centavos === null || $centavos <= 0) {
                    $validator->errors()->add('valor', 'Digite um valor maior que zero, como 90,00.');
                }
            },
        ];
    }

    /**
     * Os dados prontos para o LivroCaixa, com o valor já em centavos.
     *
     * @return array{tipo: string, categoria_id: int, descricao: string, valor_centavos: int, vencimento: string, equipe_id: int|null}
     */
    public function paraLivroCaixa(): array
    {
        return [
            'tipo' => $this->string('tipo')->toString(),
            'categoria_id' => $this->integer('categoria_id'),
            'descricao' => $this->string('descricao')->trim()->toString(),
            'valor_centavos' => (int) Dinheiro::paraCentavos((string) $this->input('valor')),
            'vencimento' => $this->string('vencimento')->toString(),
            'equipe_id' => $this->filled('equipe_id') ? $this->integer('equipe_id') : null,
        ];
    }

    /**
     * Mensagens em português.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'Escolha receita ou despesa.',
            'categoria_id.exists' => 'Escolha uma categoria ativa.',
            'descricao.required' => 'Descreva o lançamento.',
            'vencimento.date_format' => 'Informe a data de vencimento.',
            'parcelas.min' => 'Escolha de 1 a 24 parcelas.',
            'parcelas.max' => 'Escolha de 1 a 24 parcelas.',
        ];
    }
}
