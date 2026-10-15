<?php

/*
 * BLOG PÚBLICO (#61): qualquer pessoa, mesmo sem login, vê os posts que já
 * estão no site (publicados e com a data chegada), com o filtro por categoria
 * (Notícias ou Eventos), e a página de cada post.
 *
 * Curso ligado ao post: o link para a página do curso só aparece se o curso
 * está publicado e NÃO é fechado (ex.: a Formação de instrutor não leva a
 * inscrição nenhuma, decisão de 14/10/2026).
 */

namespace App\Http\Controllers\Blog;

use App\Enums\CategoriaPost;
use App\Enums\SituacaoCurso;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    /**
     * A lista: os posts no site, os mais novos primeiro, 12 por página.
     *
     * @param  Request  $request  Ex.: /blog?categoria=evento
     * @return Response A tela "blog/index".
     */
    public function index(Request $request): Response
    {
        $categoria = CategoriaPost::tryFrom((string) $request->query('categoria', ''));

        $consulta = Post::query()->noSite()->orderByDesc('publicar_em')->orderByDesc('id');
        // Categoria escolhida: só ela. Sem escolha: todas.
        if ($categoria !== null) {
            $consulta->where('categoria', $categoria);
        }

        // paginate: 12 por página, com os links de "Anterior" e "Próxima".
        $pagina = $consulta->paginate(12)->withQueryString();

        return Inertia::render('blog/index', [
            'categoria' => $categoria->value ?? '',
            'categorias' => array_map(fn (CategoriaPost $caso) => ['valor' => $caso->value, 'rotulo' => $caso->rotulo()], CategoriaPost::cases()),
            'posts' => [
                'data' => collect($pagina->items())->map(fn (Post $post) => $this->cartao($post))->values(),
                'links' => $pagina->linkCollection()->toArray(),
            ],
        ]);
    }

    /**
     * A página de um post. Post que ainda não está no site: 404 ("não existe").
     *
     * @param  Post  $post  O post do endereço, pelo slug (ex.: /blog/formacao-no-estande).
     * @return Response A tela "blog/post".
     */
    public function show(Post $post): Response
    {
        // Rascunho, em revisão, agendado ou arquivado: para o público, não existe.
        abort_unless($post->visivelNoSite(), 404);

        $curso = $post->curso;
        // O link do curso: só curso publicado e aberto (curso fechado não aparece no site).
        $cursoNoSite = $curso !== null && $curso->situacao === SituacaoCurso::Publicado && ! $curso->fechado;

        return Inertia::render('blog/post', [
            'post' => [
                ...$this->cartao($post),
                'texto' => $post->texto,
                'credito' => $post->credito,
                // Como tocar o vídeo (YouTube ou arquivo), ou null.
                'video' => $post->video(),
                // O curso ligado: id e título, ou null (sem curso, ou curso fechado).
                'curso' => $cursoNoSite ? ['id' => $curso->id, 'titulo' => $curso->titulo] : null,
            ],
        ]);
    }

    /**
     * Os dados do cartão do post na lista (e o topo da página do post).
     *
     * @return array<string, mixed>
     */
    private function cartao(Post $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'titulo' => $post->titulo,
            'categoria' => $post->categoria->rotulo(),
            'resumo' => $post->resumo,
            'capa_url' => $post->capaUrl(),
            'capa_descricao' => $post->capa_descricao,
            // A data em que foi para o site. Ex.: '2026-10-15'.
            'data' => $post->publicar_em?->toDateString(),
        ];
    }
}
