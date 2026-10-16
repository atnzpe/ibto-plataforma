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
use App\Enums\SituacaoAlbum;
use App\Enums\SituacaoCurso;
use App\Http\Controllers\Controller;
use App\Models\GaleriaAlbum;
use App\Models\Post;
use App\Support\SitePublico;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespostaHttp;

class BlogController extends Controller
{
    /**
     * A lista: os posts no site, os mais novos primeiro, 12 por página.
     *
     * Com o site configurado (#61, B4), leva a pessoa para a lista do SITE,
     * mantendo o filtro de categoria. Ex.: /blog?categoria=evento →
     * https://homolog.ib-to.org/blog?categoria=evento
     *
     * @param  Request  $request  Ex.: /blog?categoria=evento
     * @return Response|RespostaHttp A tela "blog/index", ou o redirecionamento para o site.
     */
    public function index(Request $request): Response|RespostaHttp
    {
        $categoria = CategoriaPost::tryFrom((string) $request->query('categoria', ''));

        // Site configurado: o blog público mora lá. Inertia::location troca a página
        // inteira (funciona também quando o clique veio de dentro da plataforma).
        // Sem site: segue e mostra a lista da própria plataforma (plano B).
        $noSite = SitePublico::endereco('/blog', ['categoria' => $categoria->value ?? '']);
        if ($noSite !== null) {
            return Inertia::location($noSite);
        }

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
     * Com o site configurado (#61, B4), leva a pessoa para a página do post no SITE.
     *
     * @param  Post  $post  O post do endereço, pelo slug (ex.: /blog/formacao-no-estande).
     * @return Response|RespostaHttp A tela "blog/post", ou o redirecionamento para o site.
     */
    public function show(Post $post): Response|RespostaHttp
    {
        // Rascunho, em revisão, agendado ou arquivado: para o público, não existe.
        abort_unless($post->visivelNoSite(), 404);

        // Site configurado: a página do post mora lá. Sem site: mostra aqui (plano B).
        $noSite = SitePublico::endereco('/blog/'.$post->slug);
        if ($noSite !== null) {
            return Inertia::location($noSite);
        }

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
                // As fotos dos álbuns PUBLICADOS ligados a este post (galeria, B2).
                'fotos' => $post->albuns()->where('situacao', SituacaoAlbum::Publicado)->get()
                    ->flatMap(fn (GaleriaAlbum $album) => GaleriaController::fotosParaTela($album))
                    ->values()->all(),
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
