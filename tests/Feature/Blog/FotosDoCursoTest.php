<?php

/*
 * Testes das FOTOS DO CURSO PELO INSTRUTOR (Mestre Eduardo, 17/10/2026): o
 * responsável pelo curso abre o álbum da galeria (rascunho, ligado ao curso) e
 * o álbum à venda (ele é o fotógrafo, desativado); envia e edita as fotos do
 * álbum do curso dele, mas NÃO publica; outro instrutor e o aluno não mexem.
 * Dados INVENTADOS.
 */

use App\Enums\SituacaoAlbum;
use App\Models\Curso;
use App\Models\FotoAlbumVenda;
use App\Models\GaleriaAlbum;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->instrutor = User::factory()->create(['name' => 'Instrutor Responsável']);
    $this->instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->curso = Curso::create(['titulo' => 'Curso das fotos', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['responsavel_id' => $this->instrutor->id])->save();
});

test('o instrutor abre o álbum da galeria do curso (rascunho, ligado ao curso) e não duplica', function () {
    $this->actingAs($this->instrutor)->post(route('cursos.album-fotos', $this->curso))->assertRedirect();
    $this->actingAs($this->instrutor)->post(route('cursos.album-fotos', $this->curso));

    $album = GaleriaAlbum::sole();
    expect($album->curso_id)->toBe($this->curso->id)
        ->and($album->situacao)->toBe(SituacaoAlbum::Rascunho);
});

test('o instrutor envia fotos e edita o álbum do curso dele, mas não publica', function () {
    $this->actingAs($this->instrutor)->post(route('cursos.album-fotos', $this->curso));
    $album = GaleriaAlbum::sole();

    $this->actingAs($this->instrutor)->get(route('comando-geral.galeria.edit', $album))
        ->assertOk()
        // Sem os botões do caminho (publicar é só do Comando Geral).
        ->assertInertia(fn ($tela) => $tela->where('album.acoes', []));
    $this->actingAs($this->instrutor)->post(route('comando-geral.galeria.fotos.enviar', $album), ['fotos' => [UploadedFile::fake()->image('a.jpg', 800, 600)]])
        ->assertSessionHasNoErrors();
    expect($album->fotos()->count())->toBe(1);

    $this->actingAs($this->instrutor)->post(route('comando-geral.galeria.situacao', $album), ['acao' => 'publicar'])->assertForbidden();
});

test('outro instrutor e um aluno não mexem no álbum nem abrem o do curso', function () {
    $this->actingAs($this->instrutor)->post(route('cursos.album-fotos', $this->curso));
    $album = GaleriaAlbum::sole();
    $outro = User::factory()->create();
    $outro->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));

    $this->actingAs($outro)->get(route('comando-geral.galeria.edit', $album))->assertForbidden();
    $this->actingAs($outro)->post(route('cursos.album-fotos', $this->curso))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('cursos.fotos-venda', $this->curso))->assertForbidden();
});

test('o instrutor abre o álbum à venda: ele é o fotógrafo, desativado, e entra na tela de fotógrafo', function () {
    $this->actingAs($this->instrutor)->post(route('cursos.fotos-venda', $this->curso))->assertRedirect();

    $album = FotoAlbumVenda::sole();
    expect($album->fotografo_id)->toBe($this->instrutor->id)->and($album->ativo)->toBeFalse();
    $this->actingAs($this->instrutor)->get(route('fotos.fotografo.album', $album))->assertOk();
});

test('a tela de inscritos mostra o quadro de fotos só para o responsável', function () {
    $this->actingAs($this->instrutor)->get(route('comando-geral.cursos.inscritos', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('pode_fotos', true));
});
