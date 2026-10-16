<?php

/*
 * Testes do BLOG E EVENTOS NO SITE (#61, bloco B4):
 * - a API pública entrega a lista paginada (com filtro), o post inteiro e a
 *   galeria, só com o que já está no site, e 404 com código estável no resto;
 * - as páginas públicas da plataforma levam ao site quando ele está
 *   configurado, e continuam abrindo quando não está (plano B);
 * - o endereço do site é deduzido do endereço da plataforma.
 * Dados INVENTADOS.
 */

use App\Actions\Blog\PublicacaoAlbum;
use App\Actions\Blog\PublicacaoPost;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Models\Post;
use App\Support\SitePublico;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    // Estes testes conferem o MODO PÚBLICO da galeria (GALERIA_PUBLICA=true, #61).
    // O modo privado (o padrão desde 16/10/2026) está em GaleriaPrivadaTest.
    config(['app.galeria_publica' => true]);
});

/**
 * Um post publicado agora (ou na data pedida), com capa.
 *
 * @param  string  $titulo  Ex.: 'Formação no estande'
 * @param  string  $categoria  'noticia' ou 'evento'
 * @param  string|null  $quando  Data de publicação no formato do formulário, ou null para agora.
 */
function postPublicado(string $titulo, string $categoria = 'noticia', ?string $quando = null): Post
{
    $post = Post::create(['titulo' => $titulo, 'categoria' => $categoria, 'resumo' => 'Resumo.', 'capa_descricao' => 'Capa', 'texto' => "Primeiro parágrafo.\n\nSegundo."]);
    $post->forceFill(['capa' => 'blog/capa.jpg'])->save();
    app(PublicacaoPost::class)->aplicar($post, 'publicar', $quando);

    return $post->refresh();
}

/**
 * Um álbum com 2 fotos descritas e autorização de imagem; publicado se pedido.
 */
function albumDoSite(string $titulo, bool $publicar = true, ?int $postId = null): GaleriaAlbum
{
    $album = GaleriaAlbum::create(['titulo' => $titulo, 'data_evento' => '2026-10-11', 'local_evento' => 'Recife - PE', 'credito_padrao' => 'Divulgação / IBTO', 'post_id' => $postId]);
    foreach (['a.jpg', 'b.jpg'] as $ordem => $nome) {
        $caminho = UploadedFile::fake()->image($nome)->store('galeria', 'public');
        // forceFill: album_id e caminho não são preenchíveis pelo formulário (proteção do modelo).
        (new GaleriaFoto)->forceFill(['album_id' => $album->id, 'caminho' => $caminho, 'ordem' => $ordem + 1, 'descricao' => 'Turma no estande de tiro'])->save();
    }
    $album->update(['autorizacao_imagem' => true, 'autorizacao_origem' => 'Termo assinado na inscrição']);
    if ($publicar) {
        app(PublicacaoAlbum::class)->aplicar($album->refresh(), 'publicar');
    }

    return $album->refresh();
}

test('a lista pagina, filtra por categoria e traz o slug', function () {
    foreach (range(1, 5) as $numero) {
        postPublicado("Notícia {$numero}", 'noticia', now()->subDays(10 - $numero)->format('Y-m-d\TH:i'));
    }
    postPublicado('Evento no estande', 'evento');

    // 2 por página: 6 posts = 3 páginas; a página 1 traz o mais novo (o evento).
    $this->getJson(route('api.publico.blog', ['quantos' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'posts')
        ->assertJsonPath('posts.0.titulo', 'Evento no estande')
        ->assertJsonPath('posts.0.slug', 'evento-no-estande')
        ->assertJsonPath('posts.0.categoria_valor', 'evento')
        ->assertJsonPath('pagina', ['atual' => 1, 'ultima' => 3, 'total' => 6]);

    // Filtro: só os eventos.
    $this->getJson(route('api.publico.blog', ['categoria' => 'evento', 'quantos' => 12]))
        ->assertJsonCount(1, 'posts')
        ->assertJsonPath('pagina.total', 1);

    // Pedido absurdo: o limite segura em 12.
    $this->getJson(route('api.publico.blog', ['quantos' => 100000]))
        ->assertJsonCount(6, 'posts');
});

test('o post inteiro sai só quando está no site; o resto é 404 com código', function () {
    $post = postPublicado('Formação no estande', 'evento');
    $agendado = postPublicado('Ainda não', 'noticia', now()->addDay()->format('Y-m-d\TH:i'));
    albumDoSite('Estande de tiro (PE)', true, $post->id);
    albumDoSite('Rascunho de fotos', false, $post->id);

    $this->getJson(route('api.publico.post', $post->slug))
        ->assertOk()
        ->assertJsonPath('post.titulo', 'Formação no estande')
        ->assertJsonPath('post.texto', "Primeiro parágrafo.\n\nSegundo.")
        // Só as 2 fotos do álbum PUBLICADO (o rascunho não vaza).
        ->assertJsonCount(2, 'post.fotos')
        ->assertJsonPath('post.curso', null)
        ->assertJsonMissingPath('post.autor_id');

    $this->getJson(route('api.publico.post', $agendado->slug))
        ->assertNotFound()
        ->assertJsonPath('codigo', 'POST_NAO_ENCONTRADO');
    $this->getJson('/api/publico/blog/nao-existe')->assertNotFound();
});

test('a galeria lista só os álbuns publicados; álbum fora do site é 404', function () {
    $publicado = albumDoSite('Estande de tiro (PE)');
    $rascunho = albumDoSite('Ainda em revisão', false);

    $this->getJson(route('api.publico.eventos'))
        ->assertOk()
        ->assertJsonCount(1, 'albuns')
        ->assertJsonPath('albuns.0.slug', $publicado->slug)
        ->assertJsonPath('albuns.0.fotos', 2)
        ->assertJsonPath('pagina.total', 1);

    $this->getJson(route('api.publico.evento', $publicado->slug))
        ->assertOk()
        ->assertJsonCount(2, 'album.fotos')
        ->assertJsonPath('album.fotos.0.credito', 'Divulgação / IBTO');

    $this->getJson(route('api.publico.evento', $rascunho->slug))
        ->assertNotFound()
        ->assertJsonPath('codigo', 'EVENTO_NAO_ENCONTRADO');
});

test('as fotos públicas podem ser lidas pelo site (CORS), e não por um site de fora', function () {
    $album = albumDoSite('Estande de tiro (PE)');
    $foto = $album->fotos()->first();

    $this->get(route('galeria.foto', $foto), ['Origin' => 'https://homolog.ib-to.org'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://homolog.ib-to.org');
    $this->get(route('galeria.foto', $foto), ['Origin' => 'https://site-estranho.com'])
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

test('com o site configurado, as páginas públicas levam ao site', function () {
    config(['app.site_url' => 'https://homolog.ib-to.org']);
    $post = postPublicado('Formação no estande', 'evento');
    $album = albumDoSite('Estande de tiro (PE)');

    $this->get(route('blog.index', ['categoria' => 'evento']))
        ->assertRedirect('https://homolog.ib-to.org/blog?categoria=evento');
    $this->get(route('blog.post', $post->slug))
        ->assertRedirect('https://homolog.ib-to.org/blog/formacao-no-estande');
    $this->get(route('galeria.index'))->assertRedirect('https://homolog.ib-to.org/eventos');
    $this->get(route('galeria.album', $album->slug))
        ->assertRedirect('https://homolog.ib-to.org/eventos/'.$album->slug);
});

test('sem site configurado, as páginas da plataforma continuam abrindo (plano B)', function () {
    config(['app.site_url' => '']);
    postPublicado('Formação no estande');

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('blog/index'));
    expect(SitePublico::endereco('/blog'))->toBeNull();
});

test('o endereço do site é deduzido do endereço da plataforma', function (string $appUrl, string $esperado) {
    // Guarda os valores de antes, para devolver no fim (os outros testes não podem ser afetados).
    $antes = [getenv('APP_URL'), $_ENV['APP_URL'] ?? null, $_SERVER['APP_URL'] ?? null, getenv('SITE_URL')];

    try {
        // Recalcula a configuração com outro APP_URL, como o servidor faria ao iniciar.
        putenv('SITE_URL=');
        putenv("APP_URL={$appUrl}");
        $_ENV['APP_URL'] = $appUrl;
        $_SERVER['APP_URL'] = $appUrl;
        $config = require base_path('config/app.php');

        expect($config['site_url'])->toBe($esperado);
    } finally {
        // "finally" roda mesmo se o expect falhar: devolve tudo como estava.
        putenv($antes[0] === false ? 'APP_URL' : 'APP_URL='.$antes[0]);
        $_ENV['APP_URL'] = $antes[1];
        $_SERVER['APP_URL'] = $antes[2];
        putenv($antes[3] === false ? 'SITE_URL' : 'SITE_URL='.$antes[3]);
    }
})->with([
    'homologação' => ['https://cursos-homolog.ib-to.org', 'https://homolog.ib-to.org'],
    'oficial' => ['https://cursos.ib-to.org', 'https://ib-to.org'],
    'computador local' => ['http://localhost:8000', ''],
]);
