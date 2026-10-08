<?php

/*
 * Validação do formulário de área de atuação (#37): confere no SERVIDOR o que
 * chega do formulário, antes de gravar. A tela também avisa, mas só a
 * conferência do servidor é garantida (a da tela pode ser burlada).
 */

namespace App\Http\Requests\Cadastros;

use App\Models\AreaAtuacao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaAtuacaoRequest extends FormRequest
{
    /**
     * Regras de cada campo.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Na edição, a área que está sendo editada (vem do endereço, ex.: /cadastros/areas-atuacao/4/edit).
        // No cadastro de uma nova, não existe: fica null.
        $areaAtual = $this->route('area');

        return [
            'nome' => [
                // Obrigatório: vazio (ou só espaços) volta com "Informe o nome da área."
                'required',
                // Tem que ser texto.
                'string',
                // No máximo 100 caracteres, o mesmo tamanho da coluna no banco.
                'max:100',
                // Não pode repetir o nome de outra área. Na edição, ignora a própria
                // área (senão salvar sem mudar o nome daria "nome repetido").
                Rule::unique('areas_atuacao', 'nome')->ignore($areaAtual instanceof AreaAtuacao ? $areaAtual->id : null),
            ],
            // Ativa: obrigatória e só aceita sim/não (true/false, 1/0).
            'ativa' => ['required', 'boolean'],
            // Numa LISTA, cada item é uma regra só (nada de juntar com "|").
            'imagem' => [
                // Opcional: editar sem escolher imagem nova não dá erro (mantém a atual).
                'nullable',
                // Tem que ser imagem de verdade (o Laravel olha o conteúdo, não só o nome).
                'image',
                // Só estes formatos. SVG fica de fora de propósito: ele pode
                // carregar código escondido e virar um risco de segurança.
                'mimes:jpg,jpeg,png,webp',
                // No máximo 2 MB (em arquivo, o "max" conta KB: 2 x 1024 = 2048).
                'max:2048',
            ],
        ];
    }

    /**
     * Mensagens de erro em português, mostradas embaixo de cada campo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome da área.',
            'nome.max' => 'O nome pode ter no máximo 100 caracteres.',
            'nome.unique' => 'Já existe uma área com esse nome.',
            'ativa.required' => 'Informe se a área está ativa.',
            'ativa.boolean' => 'Valor inválido para "ativa".',
            'imagem.image' => 'O arquivo precisa ser uma imagem.',
            'imagem.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'imagem.max' => 'A imagem pode ter no máximo 2 MB.',
            'imagem.uploaded' => 'Não foi possível receber a imagem. Tente uma menor que 2 MB.',
        ];
    }

    // Espaços nas pontas do nome (ex.: "  Krav Maga ") não precisam ser tirados
    // aqui: o Laravel já faz isso em todo formulário (middleware TrimStrings), e
    // um nome só de espaços chega vazio, caindo na regra "obrigatório".
}
