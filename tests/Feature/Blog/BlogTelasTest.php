<?php

/*
 * Testes das TELAS DO BLOG (#61): o Comando Geral cria, edita e publica
 * (com a capa); quem não tem "blog.publicar" recebe 403; o público vê só o
 * que está no site; rascunho e agendado dão 404; a capa não vaza antes da
 * hora; curso fechado não aparece no post. Dados INVENTADOS.
 */

use App\Actions\Blog\PublicacaoPost;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoPost;
use App\Models\Curso;
use App\Models\Papel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

/**
 * Os campos de um post completo, como o formulário envia.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['titulo' => 'Outro']
 * @return array<string, mixed>
 */
function dadosDoPost(array $troca = []): array
{
    return [
        'titulo' => 'Formação no estande', 'categoria' => 'evento',
        'resumo' => 'Fim de semana de treino.', 'texto' => "Linha 1\nLinha 2",
        'capa' => UploadedFile::fake()->image('capa.jpg', 1200, 630),
        'capa_descricao' => 'Turma no estande', 'credito' => 'Divulgação / IBTO',
        ...$troca,
    ];
}

test('o Comando Geral cria o post (rascunho, com capa) e publica', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.blog.store'), dadosDoPost())
        ->assertSessionHasNoErrors();

    $post = Post::firstOrFail();
    expect($post->situacao)->toBe(SituacaoPost::Rascunho)
        ->and($post->autor_id)->toBe($this->admin->id)
        ->and($post->capa)->not->toBeNull();
    Storage::disk('public')->assertExists($post->capa);

    $this->post(route('comando-geral.blog.situacao', $post), ['acao' => 'publicar'])->assertSessionHas('sucesso');
    expect($post->fresh()->visivelNoSite())->toBeTrue();
});

test('publicar com pessoas e sem autorização: recusa com o código', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.blog.store'), dadosDoPost(['tem_pessoas' => '1']));
    $post = Post::firstOrFail();

    $this->post(route('comando-geral.blog.situacao', $post), ['acao' => 'publicar'])
        ->assertSessionHasErrors('acao');
    expect(session('errors')->first('acao'))->toContain('POST_SEM_AUTORIZACAO_IMAGEM');
});

test('link de vídeo que não é YouTube nem .mp4: recusado', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.blog.store'), dadosDoPost(['video_url' => 'https://exemplo.com/video']))
        ->assertSessionHasErrors('video_url');
});

test('quem não tem blog.publicar não entra no blog do Comando Geral (403)', function () {
    $this->actingAs(User::factory()->create())->get(route('comando-geral.blog.index'))->assertForbidden();
});

test('público: a lista e a página mostram só o que está no site', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.blog.store'), dadosDoPost());
    $this->post(route('comando-geral.blog.store'), dadosDoPost(['titulo' => 'Rascunho secreto']));
    $publicado = Post::where('titulo', 'Formação no estande')->firstOrFail();
    $rascunho = Post::where('titulo', 'Rascunho secreto')->firstOrFail();
    app(PublicacaoPost::class)->aplicar($publicado, 'publicar');
    auth()->logout();

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('blog/index')->has('posts.data', 1)->where('posts.data.0.titulo', 'Formação no estande'));

    $this->get(route('blog.post', $publicado->slug))
        ->assertInertia(fn ($tela) => $tela->where('post.texto', "Linha 1\nLinha 2"));
    // Rascunho: para o público, não existe; e a capa dele também não.
    $this->get(route('blog.post', $rascunho->slug))->assertNotFound();
    $this->get(route('blog.capa', $rascunho))->assertNotFound();
    $this->get(route('blog.capa', $publicado))->assertOk();
});

test('filtro por categoria: Notícias não mostra os Eventos', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.blog.store'), dadosDoPost());
    app(PublicacaoPost::class)->aplicar(Post::firstOrFail(), 'publicar');

    $this->get(route('blog.index', ['categoria' => 'noticia']))
        ->assertInertia(fn ($tela) => $tela->has('posts.data', 0));
});

test('post ligado a curso fechado não mostra o link do curso', function () {
    $curso = Curso::create(['titulo' => 'Formação de instrutor', 'modalidade' => 'presencial', 'acesso' => 'restrito', 'valor_centavos' => 0, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'fechado' => true])->save();

    $this->actingAs($this->admin)->post(route('comando-geral.blog.store'), dadosDoPost(['curso_id' => $curso->id]));
    $post = Post::firstOrFail();
    app(PublicacaoPost::class)->aplicar($post, 'publicar');

    $this->get(route('blog.post', $post->slug))
        ->assertInertia(fn ($tela) => $tela->where('post.curso', null));
});
