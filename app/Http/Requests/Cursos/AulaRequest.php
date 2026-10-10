<?php

/*
 * Confere o formulário de AULA (#39): título, vídeo (YouTube ou .mp4/.webm
 * por https; outro link é recusado com VIDEO_INVALIDO), texto, duração, se
 * está ativa e a apostila em PDF (até 10 MB).
 */

namespace App\Http\Requests\Cursos;

use App\Models\CursoAula;
use App\Support\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AulaRequest extends FormRequest
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
            // De onde vem o vídeo: 'link' (YouTube/.mp4) ou 'arquivo' (enviado).
            'fonte_video' => ['required', 'in:link,arquivo'],
            'video_url' => ['nullable', 'string', 'max:500'],
            // Vídeo enviado: só .mp4/.webm, até o limite do modelo (200 MB).
            'video_arquivo' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.CursoAula::VIDEO_MAXIMO_KB],
            'conteudo' => ['nullable', 'string', 'max:10000'],
            'duracao_minutos' => ['nullable', 'integer', 'min:1', 'max:600'],
            // Encontro presencial (cronograma): dia, horário e quem aplica. Vazios no online.
            'data' => ['nullable', 'date_format:Y-m-d'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'quem_aplica' => ['nullable', 'string', 'max:100'],
            // Introdução PÚBLICA do encontro (janela do cronograma, C4d): curta, até 500 letras.
            'introducao' => ['nullable', 'string', 'max:500'],
            'ativa' => ['required', 'boolean'],
            // Só PDF, até 10 MB (10240 KB).
            'apostila' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * O link do vídeo precisa ser algo que o player sabe tocar.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Link preenchido que não é YouTube nem .mp4/.webm por https: recusa
                // (só quando a fonte escolhida é o link).
                if ($this->input('fonte_video') === 'link' && $this->filled('video_url') && Video::paraTocar((string) $this->input('video_url')) === null) {
                    $validator->errors()->add('video_url', 'Use um link do YouTube ou de um arquivo .mp4/.webm (https). (VIDEO_INVALIDO)');
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
            'titulo.required' => 'Informe o nome da aula.',
            'hora_fim.after' => 'O horário de fim precisa ser depois do início.',
            'video_arquivo.mimetypes' => 'O vídeo precisa ser .mp4 ou .webm.',
            'video_arquivo.max' => 'O vídeo pode ter no máximo 200 MB. Vídeos maiores: use um link do YouTube.',
            'apostila.mimes' => 'A apostila precisa ser um PDF.',
            'apostila.max' => 'A apostila pode ter no máximo 10 MB.',
        ];
    }
}
