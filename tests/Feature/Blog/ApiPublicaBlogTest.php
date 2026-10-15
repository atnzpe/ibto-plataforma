<?php

/*
 * Testes da API PÚBLICA DO BLOG (#61, bloco B3): só os posts já no site, no
 * máximo 6, sem dados de ninguém; o site do IBTO pode ler pelo navegador
 * (CORS) e um site de fora não. Dados INVENTADOS.
 */

use App\Actions\Blog\PublicacaoPost;
use App\Models\Post;

/**
 * Um post pronto para publicar.
 */
function postDaApi(string $titulo): Post
{
    $post = Post::create(['titulo' => $titulo, 'categoria' => 'noticia', 'resumo' => 'Resumo.', 'capa_descricao' => 'Capa']);
    $post->forceFill(['capa' => 'blog/capa.jpg'])->save();

    return $post;
}

test('traz só os posts já no site, os mais novos primeiro, sem o autor', function () {
    $antigo = postDaApi('Antigo');
    app(PublicacaoPost::class)->aplicar($antigo, 'publicar', now()->subDay()->format('Y-m-d\TH:i'));
    $novo = postDaApi('Novo');
    app(PublicacaoPost::class)->aplicar($novo, 'publicar');
    postDaApi('Rascunho');
    $agendado = postDaApi('Agendado');
    app(PublicacaoPost::class)->aplicar($agendado, 'publicar', now()->addDay()->format('Y-m-d\TH:i'));

    $this->getJson(route('api.publico.blog'))
        ->assertOk()
        ->assertJsonCount(2, 'posts')
        ->assertJsonPath('posts.0.titulo', 'Novo')
        ->assertJsonPath('posts.0.url', route('blog.post', $novo->slug))
        ->assertJsonMissingPath('posts.0.autor_id');
});

test('o site do IBTO pode ler pelo navegador; um site de fora não (CORS)', function () {
    $this->getJson(route('api.publico.blog'), ['Origin' => 'https://homolog.ib-to.org'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://homolog.ib-to.org');

    $this->getJson(route('api.publico.blog'), ['Origin' => 'https://site-estranho.com'])
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});
