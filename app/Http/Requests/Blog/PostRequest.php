<?php

/*
 * Confere o formulário de POST do blog (#61): título, categoria, resumo,
 * texto, capa (imagem até 2 MB) com a descrição e o crédito, vídeo (YouTube
 * ou .mp4/.webm por https; outro link é recusado com VIDEO_INVALIDO), curso
 * ligado e as caixas da autorização de uso de imagem (LGPD).
 *
 * Salvar aceita o post incompleto (rascunho). O que é obrigatório para ir ao
 * site é conferido na hora de PUBLICAR (App\Actions\Blog\PublicacaoPost).
 */

namespace App\Http\Requests\Blog;

use App\Enums\CategoriaPost;
use App\Support\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostRequest extends FormRequest
{
    /**
     * Quem pode enviar: a rota já exige "blog.publicar".
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Antes de conferir: as caixas de marcar desmarcadas não vêm no envio do
     * navegador; aqui elas viram "false" (0), para a regra "boolean" valer.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tem_pessoas' => $this->boolean('tem_pessoas'),
            'autorizacao_imagem' => $this->boolean('autorizacao_imagem'),
            'tem_menores' => $this->boolean('tem_menores'),
            'autorizacao_responsaveis' => $this->boolean('autorizacao_responsaveis'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'categoria' => ['required', Rule::enum(CategoriaPost::class)],
            'resumo' => ['nullable', 'string', 'max:300'],
            'texto' => ['nullable', 'string', 'max:20000'],
            // Capa: jpg, png ou webp, até 2 MB ("max" em arquivo conta em KB).
            'capa' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'capa_descricao' => ['nullable', 'string', 'max:200'],
            'credito' => ['nullable', 'string', 'max:120'],
            'video_url' => ['nullable', 'string', 'max:500'],
            // Curso ligado (opcional): precisa existir.
            'curso_id' => ['nullable', 'integer', Rule::exists('cursos', 'id')],
            // LGPD: as caixas da autorização de uso de imagem.
            'tem_pessoas' => ['boolean'],
            'autorizacao_imagem' => ['boolean'],
            'autorizacao_origem' => ['nullable', 'string', 'max:200'],
            'tem_menores' => ['boolean'],
            'autorizacao_responsaveis' => ['boolean'],
        ];
    }

    /**
     * Regras que olham o conteúdo do campo.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Link de vídeo que não é YouTube nem .mp4/.webm por https: recusa.
                // Vazio: sem vídeo (tudo bem).
                if ($this->filled('video_url') && Video::paraTocar((string) $this->input('video_url')) === null) {
                    $validator->errors()->add('video_url', 'Use um link do YouTube ou de um arquivo .mp4/.webm (https). (VIDEO_INVALIDO)');
                }
            },
        ];
    }

    /**
     * Os dados prontos para gravar (sem a capa, que é gravada à parte).
     *
     * @return array<string, mixed>
     */
    public function paraGravar(): array
    {
        return $this->safe()->except(['capa']);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Informe o título do post.',
            'capa.image' => 'A capa precisa ser uma imagem.',
            'capa.mimes' => 'A capa precisa ser JPG, PNG ou WEBP.',
            'capa.max' => 'A capa pode ter no máximo 2 MB.',
        ];
    }
}
