<?php

/*
 * CAPA de um post do blog (#61): pública, porque aparece no /blog e na
 * landpage. Só entrega a de post que JÁ ESTÁ NO SITE (publicado e com a data
 * chegada); rascunho, agendado ou arquivado só para quem tem "blog.publicar"
 * (a prévia da edição). Assim uma foto não vaza antes da hora.
 */

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostCapaController extends Controller
{
    /**
     * @param  Post  $post  O post do endereço (ex.: /imagens/blog/3).
     * @return StreamedResponse A imagem, guardada no navegador por 1 dia.
     */
    public function __invoke(Post $post): StreamedResponse
    {
        $disco = Storage::disk('public');

        // Ainda não está no site e a pessoa não cuida do blog: "não encontrado" (404).
        abort_if(! $post->visivelNoSite() && ! request()->user()?->can('blog.publicar'), 404);
        // Sem capa, ou o arquivo sumiu: 404.
        abort_if($post->capa === null || ! $disco->exists($post->capa), 404);

        // Cache de 1 dia; quando a capa muda, o endereço muda junto (?v=...).
        return $disco->response($post->capa, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
