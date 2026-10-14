<?php

/*
 * ARQUIVOS das aulas (#39): a APOSTILA (PDF) e o VÍDEO ENVIADO. Só saem por
 * aqui, nunca por link público, e só para quem pode ver o conteúdo do curso
 * (Gate "curso.conteudo": o Comando Geral agora; os inscritos no bloco C4).
 * Critério de aceite da #39: sem acesso, não abre nem baixa, mesmo com o link.
 */

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Controller;
use App\Models\CursoAula;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AulaArquivoController extends Controller
{
    /**
     * Baixa a apostila da aula.
     *
     * @param  CursoAula  $aula  A aula do endereço (ex.: /cursos/aulas/7/apostila).
     * @return StreamedResponse O PDF, com um nome limpo. Ex.: 'aula-2-defesas.pdf'.
     */
    public function apostila(CursoAula $aula): StreamedResponse
    {
        Gate::authorize('curso.conteudo', $aula->modulo->curso);

        $disco = Storage::disk('local');
        abort_if($aula->apostila === null || ! $disco->exists($aula->apostila), 404);

        return $disco->download($aula->apostila, Str::slug($aula->titulo).'.pdf');
    }

    /**
     * Toca o vídeo enviado da aula. A resposta aceita "pedaços" (Range), então
     * o player consegue avançar e voltar sem baixar o vídeo inteiro.
     *
     * @param  CursoAula  $aula  A aula do endereço (ex.: /cursos/aulas/7/video).
     * @return BinaryFileResponse O vídeo, sem guardar em cache público.
     */
    public function video(CursoAula $aula): BinaryFileResponse
    {
        Gate::authorize('curso.conteudo', $aula->modulo->curso);

        $disco = Storage::disk('local');
        abort_if($aula->video_arquivo === null || ! $disco->exists($aula->video_arquivo), 404);

        // response()->file entrega por pedaços (Range) quando o navegador pede.
        return response()->file($disco->path($aula->video_arquivo), [
            // "private": só o navegador da pessoa guarda; nenhum servidor no meio.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
