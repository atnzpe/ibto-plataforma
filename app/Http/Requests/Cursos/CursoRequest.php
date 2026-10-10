<?php

/*
 * Confere o formulário de CURSO do Comando Geral (#39 / #38) no servidor.
 * Regras do IBTO:
 * - só três modalidades de acesso: gratuito e restrito; pago e aberto; pago e
 *   restrito (curso gratuito e aberto é recusado: CURSO_GRATUITO_ABERTO);
 * - presencial exige data de início e local;
 * - o valor vem como texto ("90,00") e vira centavos (App\Support\Dinheiro).
 */

namespace App\Http\Requests\Cursos;

use App\Enums\AcessoCurso;
use App\Enums\ModalidadeCurso;
use App\Enums\SituacaoCurso;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CursoRequest extends FormRequest
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
            'descricao' => ['nullable', 'string', 'max:5000'],
            // Uma área ATIVA (0 em vez de false: a regra "exists" guarda o valor como texto).
            'area_atuacao_id' => ['nullable', 'integer', Rule::exists('areas_atuacao', 'id')->where('ativa', 1)],
            'modalidade' => ['required', Rule::enum(ModalidadeCurso::class)],
            'acesso' => ['required', Rule::enum(AcessoCurso::class)],
            // Texto do valor; vazio = gratuito. A conversão fica no after() e no paraGravar().
            'valor' => ['nullable', 'string', 'max:20'],
            'vagas' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            // O fim não pode ser antes do início.
            'data_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i'],
            'local' => ['nullable', 'string', 'max:150'],
            'carga_horaria' => ['nullable', 'integer', 'min:1', 'max:2000'],
            // Instrutor: uma conta que exista (o papel é conferido na tela de escolha).
            'instrutor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            // Só endereço https (link de pagamento).
            'link_pagamento' => ['nullable', 'url:https', 'max:500'],
            'situacao' => ['required', Rule::enum(SituacaoCurso::class)],
            // Capa: mesma regra das outras imagens (sem SVG, até 2 MB).
            'imagem' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Regras que olham mais de um campo ao mesmo tempo.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Valor digitado que não vira dinheiro (ex.: "abc"): erro no campo.
                $centavos = $this->centavos();
                if ($centavos === null) {
                    $validator->errors()->add('valor', 'Digite um valor como 90,00, ou deixe vazio para gratuito.');

                    return;
                }

                // Gratuito E aberto não existe no IBTO: gratuito é só para filiados.
                if ($centavos === 0 && $this->input('acesso') === AcessoCurso::Aberto->value) {
                    $validator->errors()->add('acesso', 'Curso gratuito é só para filiados: escolha "Só filiados" ou informe um valor. (CURSO_GRATUITO_ABERTO)');
                }

                // Presencial precisa de data de início e de local.
                if ($this->input('modalidade') === ModalidadeCurso::Presencial->value) {
                    if (! $this->filled('data_inicio')) {
                        $validator->errors()->add('data_inicio', 'Curso presencial precisa da data de início.');
                    }
                    if (! $this->filled('local')) {
                        $validator->errors()->add('local', 'Curso presencial precisa do local.');
                    }
                }
            },
        ];
    }

    /**
     * O valor em centavos: vazio = 0 (gratuito); texto inválido = null.
     */
    private function centavos(): ?int
    {
        $texto = trim((string) $this->input('valor', ''));

        return $texto === '' ? 0 : Dinheiro::paraCentavos($texto);
    }

    /**
     * Os dados prontos para gravar (com o valor em centavos e sem a imagem,
     * que é gravada à parte).
     *
     * @return array<string, mixed>
     */
    public function paraGravar(): array
    {
        return [
            ...$this->safe()->except(['valor', 'imagem']),
            'valor_centavos' => (int) $this->centavos(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Informe o nome do curso.',
            'data_fim.after_or_equal' => 'A data de fim não pode ser antes do início.',
            'link_pagamento.url' => 'O link de pagamento precisa começar com https://',
            'imagem.image' => 'O arquivo precisa ser uma imagem.',
            'imagem.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'imagem.max' => 'A imagem pode ter no máximo 2 MB.',
        ];
    }
}
