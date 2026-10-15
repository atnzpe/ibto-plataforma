<?php

/*
 * BLOG no Comando Geral (#61): a lista dos posts (com filtros), o cadastro e a
 * edição, e as ações do caminho (enviar para revisão, publicar/agendar,
 * arquivar, voltar para rascunho). Quem entra: "blog.publicar" (na rota).
 * As regras de publicar ficam em App\Actions\Blog\PublicacaoPost.
 */

namespace App\Http\Controllers\Blog;

use App\Actions\Blog\PublicacaoPost;
use App\Enums\CategoriaPost;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoPost;
use App\Exceptions\RegraBlog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\PostRequest;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Post;
use App\Support\ImagemPublica;
use App\Support\Periodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    // Pasta das capas dentro de storage/app/public.
    private const PASTA_CAPAS = 'blog';

    /**
     * Lista com filtros: busca pelo título, categoria, situação e período
     * (pela data de criação).
     *
     * @param  Request  $request  Ex.: /comando-geral/blog?categoria=evento&situacao=publicado&de=2026-10-01&ate=2026-10-31
     * @return Response A tela "blog-admin/index".
     */
    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));
        $categoria = CategoriaPost::tryFrom((string) $request->query('categoria', ''));
        $situacao = SituacaoPost::tryFrom((string) $request->query('situacao', ''));
        // Período só filtra se a pessoa escolher datas.
        $periodo = $request->filled('de') || $request->filled('ate') ? Periodo::doPedido($request) : null;

        // Os mais novos primeiro.
        $consulta = Post::query()->latest('id');
        if ($busca !== '') {
            $consulta->where('titulo', 'like', '%'.$busca.'%');
        }
        if ($categoria !== null) {
            $consulta->where('categoria', $categoria);
        }
        if ($situacao !== null) {
            $consulta->where('situacao', $situacao);
        }
        if ($periodo !== null) {
            $consulta->whereBetween('created_at', [$periodo->inicio->startOfDay(), $periodo->fim->endOfDay()]);
        }

        return Inertia::render('blog-admin/index', [
            'filtros' => [
                'busca' => $busca,
                'categoria' => $categoria->value ?? '',
                'situacao' => $situacao->value ?? '',
                'de' => $periodo?->inicio->toDateString() ?? '',
                'ate' => $periodo?->fim->toDateString() ?? '',
            ],
            'posts' => $consulta->limit(300)->get()->map(fn (Post $post) => [
                'id' => $post->id,
                'titulo' => $post->titulo,
                'categoria' => $post->categoria->rotulo(),
                'situacao' => $post->situacao->value,
                'situacao_rotulo' => $post->situacao->rotulo(),
                // Publicado com data no futuro = agendado (o rótulo diz).
                'agendado' => $post->situacao === SituacaoPost::Publicado && ! $post->visivelNoSite(),
                'publicar_em' => $post->publicar_em?->format('d/m/Y H:i'),
                'criado_em' => $post->created_at?->toDateString(),
            ])->values(),
            'categorias' => $this->opcoes(CategoriaPost::cases()),
            'situacoes' => $this->opcoes(SituacaoPost::cases()),
        ]);
    }

    /**
     * Formulário vazio.
     *
     * @return Response A tela "blog-admin/formulario".
     */
    public function create(): Response
    {
        return Inertia::render('blog-admin/formulario', ['post' => null, ...$this->opcoesDoFormulario()]);
    }

    /**
     * Grava o post novo como RASCUNHO (com a capa, o autor e a auditoria).
     *
     * @param  PostRequest  $request  O formulário, já conferido.
     * @return RedirectResponse A edição do post, para seguir até publicar.
     */
    public function store(PostRequest $request): RedirectResponse
    {
        $post = DB::transaction(function () use ($request): Post {
            $post = new Post($request->paraGravar());
            // Quem escreveu (não vem do formulário).
            $post->forceFill(['autor_id' => $request->user()?->id])->save();
            ImagemPublica::trocar($post, 'capa', self::PASTA_CAPAS, $request->file('capa'));
            Auditoria::registrar('POST_CRIADO', ['post_id' => $post->id]);

            return $post;
        });

        return to_route('comando-geral.blog.edit', $post)->with('sucesso', "Post \"{$post->titulo}\" salvo como rascunho.");
    }

    /**
     * Edição: o formulário e os botões das ações que fazem sentido agora.
     *
     * @param  Post  $blog  O post do endereço (ex.: /comando-geral/blog/3/edit).
     * @param  PublicacaoPost  $publicacao  As regras (quais botões mostrar).
     * @return Response A tela "blog-admin/formulario".
     */
    public function edit(Post $blog, PublicacaoPost $publicacao): Response
    {
        return Inertia::render('blog-admin/formulario', [
            'post' => [
                'id' => $blog->id,
                'titulo' => $blog->titulo,
                'slug' => $blog->slug,
                'categoria' => $blog->categoria->value,
                'resumo' => $blog->resumo,
                'texto' => $blog->texto,
                'capa_url' => $blog->capaUrl(),
                'capa_descricao' => $blog->capa_descricao,
                'credito' => $blog->credito,
                'video_url' => $blog->video_url,
                'curso_id' => $blog->curso_id,
                'tem_pessoas' => $blog->tem_pessoas,
                'autorizacao_imagem' => $blog->autorizacao_imagem,
                'autorizacao_origem' => $blog->autorizacao_origem,
                'tem_menores' => $blog->tem_menores,
                'autorizacao_responsaveis' => $blog->autorizacao_responsaveis,
                'situacao' => $blog->situacao->value,
                'situacao_rotulo' => $blog->situacao->rotulo(),
                'publicar_em' => $blog->publicar_em?->format('d/m/Y H:i'),
                // Aparece no site agora? (publicado e com a data chegada)
                'no_site' => $blog->visivelNoSite(),
                // Os botões do caminho que fazem sentido agora. Ex.: ['enviar_revisao', 'publicar'].
                'acoes' => $publicacao->acoesPara($blog),
            ],
            ...$this->opcoesDoFormulario(),
        ]);
    }

    /**
     * Grava as mudanças do post (a situação não muda aqui), com auditoria.
     *
     * @param  PostRequest  $request  O formulário, já conferido.
     * @param  Post  $blog  O post que está sendo editado.
     */
    public function update(PostRequest $request, Post $blog): RedirectResponse
    {
        DB::transaction(function () use ($request, $blog): void {
            $blog->update($request->paraGravar());
            ImagemPublica::trocar($blog, 'capa', self::PASTA_CAPAS, $request->file('capa'));
            Auditoria::registrar('POST_EDITADO', ['post_id' => $blog->id]);
        });

        return back()->with('sucesso', "Post \"{$blog->titulo}\" salvo.");
    }

    /**
     * Uma ação do caminho do post (enviar para revisão, publicar, arquivar,
     * voltar para rascunho). No "publicar", a data opcional agenda o post.
     *
     * @param  Request  $request  Com "acao" e, no publicar, "publicar_em" (opcional).
     * @param  Post  $blog  O post.
     * @param  PublicacaoPost  $publicacao  As regras.
     * @return RedirectResponse De volta, com a mensagem ou o erro da regra (com código).
     */
    public function situacao(Request $request, Post $blog, PublicacaoPost $publicacao): RedirectResponse
    {
        try {
            $publicacao->aplicar($blog, (string) $request->input('acao'), $request->input('publicar_em'));
        } catch (RegraBlog $erro) {
            return back()->withErrors(['acao' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        // Agendado: a mensagem diz quando aparece no site.
        $mensagem = $blog->situacao === SituacaoPost::Publicado && ! $blog->visivelNoSite()
            ? "Post agendado: aparece no site em {$blog->publicar_em?->format('d/m/Y H:i')}."
            : "Post agora: {$blog->situacao->rotulo()}.";

        return back()->with('sucesso', $mensagem);
    }

    /**
     * As listas de escolha do formulário: as categorias e os cursos publicados
     * (para ligar o post a um curso).
     *
     * @return array<string, mixed>
     */
    private function opcoesDoFormulario(): array
    {
        return [
            'categorias' => $this->opcoes(CategoriaPost::cases()),
            // Só cursos publicados ou encerrados (curso em rascunho não tem página pública).
            'cursos' => Curso::query()
                ->whereIn('situacao', [SituacaoCurso::Publicado, SituacaoCurso::Encerrado])
                ->orderBy('titulo')
                ->get(['id', 'titulo']),
        ];
    }

    /**
     * Opções de uma lista fixa (enum) para a tela. Ex.: [['valor' => 'evento', 'rotulo' => 'Eventos']]
     *
     * @param  list<CategoriaPost|SituacaoPost>  $casos
     * @return list<array{valor: string, rotulo: string}>
     */
    private function opcoes(array $casos): array
    {
        return array_map(fn ($caso) => ['valor' => $caso->value, 'rotulo' => $caso->rotulo()], $casos);
    }
}
