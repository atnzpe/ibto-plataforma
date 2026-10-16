<?php

/*
 * Testes do MODO PRIVADO DA GALERIA (padrão desde 16/10/2026): fotos dos eventos
 * só para filiados e cargos acima, com login. Visitante vai entrar; aluno sem
 * filiação vê o aviso; a API do site entrega o evento SEM fotos; cada foto só
 * sai para quem pode ver. O blog continua público. Dados INVENTADOS.
 */

use App\Actions\Blog\PublicacaoAlbum;
use App\Actions\Filiados\CriarFiliado;
use App\Models\Equipe;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Notification::fake();
    // O padrão: modo privado.
    config(['app.galeria_publica' => false]);

    // Um álbum publicado, com uma foto descrita e a autorização de imagem.
    $this->album = GaleriaAlbum::create(['titulo' => 'Estande de tiro PE', 'data_evento' => '2026-10-11', 'local_evento' => 'Recife - PE', 'credito_padrao' => 'IBTO']);
    $this->foto = (new GaleriaFoto)->forceFill([
        'album_id' => $this->album->id,
        'caminho' => UploadedFile::fake()->image('turma.jpg')->store('galeria', 'public'),
        'ordem' => 1,
        'descricao' => 'Turma no estande',
    ]);
    $this->foto->save();
    $this->album->update(['autorizacao_imagem' => true, 'autorizacao_origem' => 'Termo assinado']);
    app(PublicacaoAlbum::class)->aplicar($this->album->refresh(), 'publicar');

    // Um filiado com conta (CPF de teste).
    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Filiado Teste', 'email' => 'filiado@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => Equipe::factory()->create()->id,
    ])->usuario;
    $this->filiado->markEmailAsVerified();
});

test('visitante vai para a tela de entrar (lista, álbum e foto)', function () {
    $this->get(route('galeria.index'))->assertRedirect(route('login'));
    $this->get(route('galeria.album', $this->album->slug))->assertRedirect(route('login'));
    $this->get(route('galeria.foto', $this->foto->id))->assertNotFound();
});

test('aluno sem filiação vê o aviso e não abre as fotos', function () {
    $aluno = User::factory()->create();

    $this->actingAs($aluno)->get(route('galeria.index'))
        ->assertInertia(fn ($tela) => $tela->where('restrita', true)->where('albuns.data', []));
    $this->actingAs($aluno)->get(route('galeria.album', $this->album->slug))->assertRedirect(route('galeria.index'));
    $this->actingAs($aluno)->get(route('galeria.foto', $this->foto->id))->assertNotFound();
});

test('filiado e cargos acima veem os álbuns e as fotos (cache privado)', function () {
    $this->actingAs($this->filiado)->get(route('galeria.index'))
        ->assertInertia(fn ($tela) => $tela->where('restrita', false)->has('albuns.data', 1));
    $this->actingAs($this->filiado)->get(route('galeria.foto', $this->foto->id))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, private');

    // Instrutor (cargo acima), mesmo sem registro de filiado.
    $instrutor = User::factory()->create();
    $instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->actingAs($instrutor)->get(route('galeria.foto', $this->foto->id))->assertOk();
});

test('a API do site entrega o evento sem fotos nem capa, com o aviso e o link para entrar', function () {
    $this->getJson(route('api.publico.eventos'))
        ->assertOk()
        ->assertJsonPath('albuns.0.titulo', 'Estande de tiro PE')
        ->assertJsonPath('albuns.0.capa_url', null)
        ->assertJsonPath('albuns.0.fotos_restritas', true);

    $this->getJson(route('api.publico.evento', $this->album->slug))
        ->assertOk()
        ->assertJsonPath('album.fotos', [])
        ->assertJsonPath('album.fotos_restritas', true)
        ->assertJsonPath('album.fotos_url', route('galeria.album', $this->album->slug));
});

test('modo público (GALERIA_PUBLICA=true): todo mundo vê, como no #61', function () {
    config(['app.galeria_publica' => true]);

    $this->get(route('galeria.foto', $this->foto->id))->assertOk()->assertHeader('Cache-Control', 'max-age=86400, public');
    $this->getJson(route('api.publico.evento', $this->album->slug))->assertJsonCount(1, 'album.fotos');
});
