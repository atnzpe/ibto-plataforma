<?php

/*
 * Testes da GALERIA DE EVENTOS (#61, bloco B2): enviar várias fotos;
 * publicar exige fotos, todas descritas e a autorização de imagem (e a dos
 * responsáveis, com menores); o público vê só o álbum publicado; a foto não
 * vaza antes; as fotos aparecem no post ligado; quem não tem
 * "blog.publicar" recebe 403. Dados INVENTADOS.
 */

use App\Actions\Blog\PublicacaoAlbum;
use App\Actions\Blog\PublicacaoPost;
use App\Enums\SituacaoAlbum;
use App\Exceptions\RegraBlog;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Models\Papel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    // Estes testes conferem o MODO PÚBLICO da galeria (GALERIA_PUBLICA=true, #61).
    // O modo privado (o padrão desde 16/10/2026) está em GaleriaPrivadaTest.
    config(['app.galeria_publica' => true]);
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

/**
 * Cria um álbum pela tela e envia 2 fotos.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['tem_menores' => '1']
 */
function albumComFotos($teste, array $troca = []): GaleriaAlbum
{
    $teste->actingAs($teste->admin)->post(route('comando-geral.galeria.store'), [
        'titulo' => 'Estande de tiro (PE)', 'data_evento' => '2026-10-11', 'local_evento' => 'Recife - PE',
        'credito_padrao' => 'Divulgação / IBTO', ...$troca,
    ]);
    $album = GaleriaAlbum::latest('id')->firstOrFail();
    $teste->post(route('comando-geral.galeria.fotos.enviar', $album), [
        'fotos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]);

    return $album;
}

/**
 * O código do erro de regra, ou null se deu certo.
 */
function codigoDaGaleria(callable $acao): ?string
{
    try {
        $acao();
    } catch (RegraBlog $erro) {
        return $erro->codigo;
    }

    return null;
}

test('envia várias fotos de uma vez, na ordem, guardadas no disco', function () {
    $album = albumComFotos($this);

    expect($album->fotos()->pluck('ordem')->all())->toBe([1, 2]);
    foreach ($album->fotos as $foto) {
        Storage::disk('public')->assertExists($foto->caminho);
    }
});

test('publicar exige fotos descritas e a autorização de imagem (e dos responsáveis, com menores)', function () {
    $regras = app(PublicacaoAlbum::class);

    // Sem fotos.
    $vazio = GaleriaAlbum::create(['titulo' => 'Vazio']);
    expect(codigoDaGaleria(fn () => $regras->aplicar($vazio, 'publicar')))->toBe('ALBUM_SEM_FOTOS');

    // Com fotos sem descrição.
    $album = albumComFotos($this, ['tem_menores' => '1']);
    expect(codigoDaGaleria(fn () => $regras->aplicar($album, 'publicar')))->toBe('ALBUM_FOTO_SEM_DESCRICAO');

    // Descrição curta demais (menos de 5 letras) ainda não vale.
    $fotos = $album->fotos;
    $this->put(route('comando-geral.galeria.fotos.salvar', $album), ['fotos' => [
        $fotos[0]->id => ['descricao' => 'Turma no estande', 'ordem' => 2],
        $fotos[1]->id => ['descricao' => 'Oi', 'ordem' => 1],
    ]]);
    expect(codigoDaGaleria(fn () => $regras->aplicar($album, 'publicar')))->toBe('ALBUM_FOTO_SEM_DESCRICAO');

    // As duas descritas; a ordem trocou.
    $this->put(route('comando-geral.galeria.fotos.salvar', $album), ['fotos' => [
        $fotos[1]->id => ['descricao' => 'Instrutor orienta a pegada', 'ordem' => 1],
    ]]);
    expect($album->fotos()->first()->id)->toBe($fotos[1]->id);

    // Falta a autorização de imagem; depois a dos responsáveis (há menores).
    expect(codigoDaGaleria(fn () => $regras->aplicar($album, 'publicar')))->toBe('ALBUM_SEM_AUTORIZACAO_IMAGEM');
    $album->update(['autorizacao_imagem' => true, 'autorizacao_origem' => 'Termo assinado na inscrição']);
    expect(codigoDaGaleria(fn () => $regras->aplicar($album, 'publicar')))->toBe('ALBUM_SEM_AUTORIZACAO_RESPONSAVEIS');
    $album->update(['autorizacao_responsaveis' => true]);

    // Agora publica (pela tela).
    $this->post(route('comando-geral.galeria.situacao', $album), ['acao' => 'publicar'])->assertSessionHas('sucesso');
    expect($album->fresh()->situacao)->toBe(SituacaoAlbum::Publicado);
});

/**
 * Deixa o álbum pronto e publicado (fotos descritas e autorização).
 */
function publicarAlbum(GaleriaAlbum $album): void
{
    $album->fotos->each(fn (GaleriaFoto $foto) => $foto->update(['descricao' => 'Turma no estande de tiro']));
    $album->update(['autorizacao_imagem' => true, 'autorizacao_origem' => 'Termo assinado na inscrição']);
    app(PublicacaoAlbum::class)->aplicar($album, 'publicar');
}

test('público: só o álbum publicado aparece, e a foto não vaza antes', function () {
    $rascunho = albumComFotos($this);
    $publicado = albumComFotos($this);
    publicarAlbum($publicado);
    auth()->logout();

    $this->get(route('galeria.index'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('galeria/index')->has('albuns.data', 1));
    $this->get(route('galeria.album', $publicado->slug))
        ->assertInertia(fn ($tela) => $tela->has('album.fotos', 2)->where('album.fotos.0.credito', 'Divulgação / IBTO'));
    $this->get(route('galeria.album', $rascunho->slug))->assertNotFound();
    $this->get(route('galeria.foto', $rascunho->fotos()->first()))->assertNotFound();
    $this->get(route('galeria.foto', $publicado->fotos()->first()))->assertOk();
});

test('as fotos do álbum publicado aparecem no post ligado', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.blog.store'), [
        'titulo' => 'Formação no estande', 'categoria' => 'evento', 'resumo' => 'Treino.',
        'capa' => UploadedFile::fake()->image('capa.jpg'), 'capa_descricao' => 'Turma',
    ]);
    $post = Post::firstOrFail();
    app(PublicacaoPost::class)->aplicar($post, 'publicar');

    $album = albumComFotos($this, ['post_id' => $post->id]);
    publicarAlbum($album);

    $this->get(route('blog.post', $post->slug))
        ->assertInertia(fn ($tela) => $tela->has('post.fotos', 2));
});

test('quem não tem blog.publicar não entra na galeria do Comando Geral (403)', function () {
    $this->actingAs(User::factory()->create())->get(route('comando-geral.galeria.index'))->assertForbidden();
});
