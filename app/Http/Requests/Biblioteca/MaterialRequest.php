<?php

/*
 * Confere o formulário de MATERIAL DA BIBLIOTECA (#107): nome da técnica,
 * explicação, área, nível mínimo, vídeo (link do YouTube/.mp4 por https OU
 * arquivo .mp4/.webm até 200 MB) e apostila em PDF (até 10 MB).
 * As mesmas regras da aula de um curso (AulaRequest), para o Comando Geral não
 * precisar aprender dois jeitos de enviar vídeo.
 */

namespace App\Http\Requests\Biblioteca;

use App\Models\CursoAula;
use App\Support\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MaterialRequest extends FormRequest
{
    /**
     * Quem pode enviar: a rota já exige "biblioteca.gerenciar".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * As regras de cada campo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Nome da técnica. Ex.: "Defesa contra faca: ataque de cima".
            'titulo' => ['required', 'string', 'max:150'],
            // Explicação curta (o que observar, erros comuns).
            'descricao' => ['nullable', 'string', 'max:2000'],
            // Área e nível precisam existir no cadastro; vazio = "todas" / "todos".
            'area_atuacao_id' => ['nullable', 'integer', Rule::exists('areas_atuacao', 'id')],
            'nivel_minimo_id' => ['nullable', 'integer', Rule::exists('niveis', 'id')],
            // De onde vem o vídeo: 'link' (YouTube/.mp4) ou 'arquivo' (enviado).
            'fonte_video' => ['required', 'in:link,arquivo'],
            'video_url' => ['nullable', 'string', 'max:500'],
            // Vídeo enviado: só .mp4/.webm, até o mesmo limite das aulas (200 MB).
            'video_arquivo' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.CursoAula::VIDEO_MAXIMO_KB],
            // Só PDF, até 10 MB (10240 KB).
            'apostila' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            // Marcado = tira a apostila que já existe (sem enviar outra).
            'remover_apostila' => ['nullable', 'boolean'],
            // false = retirado da biblioteca (some da lista de quem estuda).
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * Conferências que dependem de mais de um campo.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Link preenchido que não é YouTube nem .mp4/.webm por https: recusa
                // (só quando a fonte escolhida é o link). Um link qualquer poderia
                // levar o instrutor para um site falso.
                if ($this->input('fonte_video') === 'link' && $this->filled('video_url') && Video::paraTocar((string) $this->input('video_url')) === null) {
                    $validator->errors()->add('video_url', 'Use um link do YouTube ou de um arquivo .mp4/.webm (https). (VIDEO_INVALIDO)');
                }
            },
        ];
    }

    /**
     * Os campos que vão direto para o banco (os arquivos o controlador grava à parte).
     *
     * @return array<string, mixed> Ex.: ['titulo' => 'Defesa contra faca', 'ativo' => true, ...]
     */
    public function paraGravar(): array
    {
        $dados = $this->safe()->only(['titulo', 'descricao', 'area_atuacao_id', 'nivel_minimo_id', 'ativo']);
        // Fonte "arquivo": o link é limpo (senão o link antigo apareceria por baixo do arquivo).
        $dados['video_url'] = $this->input('fonte_video') === 'link' ? $this->input('video_url') : null;

        return $dados;
    }

    /**
     * Mensagens em português simples.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Informe o nome da técnica.',
            'video_arquivo.mimetypes' => 'O vídeo precisa ser .mp4 ou .webm.',
            'video_arquivo.max' => 'O vídeo pode ter no máximo 200 MB. Vídeos maiores: use um link do YouTube não listado.',
            'apostila.mimes' => 'A apostila precisa ser um PDF.',
            'apostila.max' => 'A apostila pode ter no máximo 10 MB.',
        ];
    }
}
