<?php

/*
 * Testes da FOTO 3x4 DO FILIADO e da AUTORIZAÇÃO DE IMAGEM (#78).
 * Quem pode: a própria pessoa e quem edita aquele filiado. A autorização começa
 * desmarcada, grava a data e vai para a auditoria. Dados INVENTADOS (CPFs de teste).
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Auditoria;
use App\Models\Equipe;
use App\Models\Papel;
use App\Models\User;
use App\Support\FotoDePerfil;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(FotoDePerfil::DISCO);
    Notification::fake();
    // Um filiado com conta (CPF de teste, válido só no formato).
    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Filiado Teste', 'email' => 'filiado@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => Equipe::factory()->create()->id,
    ]);
    $this->dono = $this->filiado->usuario;
    $this->dono->markEmailAsVerified();
});

test('a própria pessoa envia a 3x4; a autorização começa desmarcada', function () {
    expect($this->filiado->fresh()->autoriza_imagem_rede)->toBeFalse();

    $this->actingAs($this->dono)
        ->post(route('filiados.foto.update', $this->filiado->id), ['foto' => UploadedFile::fake()->image('3x4.jpg', 300, 400)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $caminho = $this->filiado->fresh()->foto;
    expect($caminho)->toStartWith('fotos/filiados/');
    Storage::disk(FotoDePerfil::DISCO)->assertExists($caminho);

    $this->actingAs($this->dono)->get(route('filiados.foto', $this->filiado->id))->assertOk();
});

test('outra pessoa não vê nem troca a 3x4; o Comando Geral sim', function () {
    $this->actingAs($this->dono)->post(route('filiados.foto.update', $this->filiado->id), ['foto' => UploadedFile::fake()->image('3x4.jpg', 300, 400)]);

    $estranho = User::factory()->create();
    $this->actingAs($estranho)->get(route('filiados.foto', $this->filiado->id))->assertNotFound();
    $this->actingAs($estranho)
        ->post(route('filiados.foto.update', $this->filiado->id), ['foto' => UploadedFile::fake()->image('x.jpg', 300, 400)])
        ->assertForbidden();

    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin)->get(route('filiados.foto', $this->filiado->id))->assertOk();

    // Tirar a foto apaga o arquivo.
    $caminho = $this->filiado->fresh()->foto;
    $this->actingAs($admin)->delete(route('filiados.foto.destroy', $this->filiado->id))->assertRedirect();
    Storage::disk(FotoDePerfil::DISCO)->assertMissing($caminho);
    expect($this->filiado->fresh()->foto)->toBeNull();
});

test('autorização de imagem: grava a data e quem marcou na auditoria; desmarcar limpa a data', function () {
    $this->actingAs($this->dono)
        ->put(route('filiados.autorizacao-imagem', $this->filiado->id), ['autoriza' => true])
        ->assertRedirect();

    $filiado = $this->filiado->fresh();
    expect($filiado->autoriza_imagem_rede)->toBeTrue()
        ->and($filiado->autoriza_imagem_em)->not->toBeNull();
    expect(Auditoria::where('acao', 'AUTORIZACAO_IMAGEM_MARCADA')->where('user_id', $this->dono->id)->exists())->toBeTrue();

    $this->actingAs($this->dono)->put(route('filiados.autorizacao-imagem', $this->filiado->id), ['autoriza' => false]);

    $filiado = $this->filiado->fresh();
    expect($filiado->autoriza_imagem_rede)->toBeFalse()
        ->and($filiado->autoriza_imagem_em)->toBeNull();
    expect(Auditoria::where('acao', 'AUTORIZACAO_IMAGEM_DESMARCADA')->exists())->toBeTrue();
});

test('a tela de perfil mostra a seção da 3x4 só para quem é filiado', function () {
    $this->actingAs($this->dono)->get(route('profile.edit'))
        ->assertInertia(fn ($tela) => $tela->where('filiado.id', $this->filiado->id)->where('filiado.autoriza_imagem_rede', false));

    $this->actingAs(User::factory()->create())->get(route('profile.edit'))
        ->assertInertia(fn ($tela) => $tela->where('filiado', null));
});
