<?php

/*
 * Imagem de CAPA de um curso (#39): pública, porque aparece no catálogo e na
 * landpage. Só entrega a de curso PUBLICADO ou ENCERRADO (rascunho não vaza).
 */

namespace App\Http\Controllers;

use App\Enums\SituacaoCurso;
use App\Models\Curso;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CursoImagemController extends Controller
{
    /**
     * @param  Curso  $curso  O curso do endereço (ex.: /imagens/cursos/3).
     * @return StreamedResponse A imagem, guardada no navegador por 1 dia.
     */
    public function __invoke(Curso $curso): StreamedResponse
    {
        $disco = Storage::disk('public');

        // Rascunho, sem imagem ou arquivo que sumiu: "não encontrado" (404).
        abort_if($curso->situacao === SituacaoCurso::Rascunho && ! request()->user()?->can('cursos.gerenciar'), 404);
        abort_if($curso->imagem === null || ! $disco->exists($curso->imagem), 404);

        // Cache de 1 dia; quando a imagem muda, o endereço muda junto (?v=...).
        return $disco->response($curso->imagem, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
