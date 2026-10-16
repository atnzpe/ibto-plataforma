<?php

/*
 * Testes da FOTO DE PERFIL (#78): envio em Configurações, recorte em quadrado de
 * 400 px, tipo e tamanho recusados, troca que apaga a antiga, e a rota PRIVADA
 * que só entrega a foto para quem pode ver. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Models\Curso;
use App\Models\Papel;
use App\Models\User;
use App\Support\FotoDePerfil;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Disco privado de mentira: nada é gravado de verdade no computador.
    Storage::fake(FotoDePerfil::DISCO);
    Notification::fake();
    $this->pessoa = User::factory()->create();
});

test('enviar a foto: recorta em quadrado de 400 px, grava na pasta privada e aparece no avatar', function () {
    // Foto "em pé" (retrato), como a do celular.
    $this->actingAs($this->pessoa)
        ->post(route('foto.update'), ['foto' => UploadedFile::fake()->image('eu.jpg', 600, 900)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $caminho = $this->pessoa->fresh()->foto;
    expect($caminho)->toStartWith('fotos/usuarios/');
    Storage::disk(FotoDePerfil::DISCO)->assertExists($caminho);

    // O arquivo final é um quadrado de 400 x 400.
    [$largura, $altura] = getimagesizefromstring(Storage::disk(FotoDePerfil::DISCO)->get($caminho));
    expect([$largura, $altura])->toBe([400, 400]);

    // O avatar é o endereço PROTEGIDO, nunca o caminho do arquivo.
    expect($this->pessoa->fresh()->avatar)->toContain('/fotos/usuarios/'.$this->pessoa->id)
        ->and($this->pessoa->fresh()->toArray())->not->toHaveKey('foto');
});

test('tipo e tamanho errados são recusados com mensagem em português', function () {
    $this->actingAs($this->pessoa)
        ->post(route('foto.update'), ['foto' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('foto');

    $this->actingAs($this->pessoa)
        ->post(route('foto.update'), ['foto' => UploadedFile::fake()->image('grande.jpg')->size(6000)])
        ->assertSessionHasErrors(['foto' => 'A foto pode ter no máximo 5 MB.']);

    expect($this->pessoa->fresh()->foto)->toBeNull();
});

test('trocar a foto apaga a antiga; tirar a foto apaga o arquivo e volta às iniciais', function () {
    $this->actingAs($this->pessoa)->post(route('foto.update'), ['foto' => UploadedFile::fake()->image('1.png', 500, 500)]);
    $primeira = $this->pessoa->fresh()->foto;

    $this->actingAs($this->pessoa)->post(route('foto.update'), ['foto' => UploadedFile::fake()->image('2.png', 500, 500)]);
    $segunda = $this->pessoa->fresh()->foto;

    Storage::disk(FotoDePerfil::DISCO)->assertMissing($primeira);
    Storage::disk(FotoDePerfil::DISCO)->assertExists($segunda);

    $this->actingAs($this->pessoa)->delete(route('foto.destroy'))->assertRedirect();

    Storage::disk(FotoDePerfil::DISCO)->assertMissing($segunda);
    expect($this->pessoa->fresh()->foto)->toBeNull()
        ->and($this->pessoa->fresh()->avatar)->toBeNull();
});

test('a rota da foto é privada: dona e Comando Geral veem; outra pessoa e visitante não', function () {
    $this->actingAs($this->pessoa)->post(route('foto.update'), ['foto' => UploadedFile::fake()->image('eu.jpg', 400, 400)]);
    $endereco = route('usuarios.foto', $this->pessoa->id);

    // A própria pessoa.
    $this->actingAs($this->pessoa)->get($endereco)->assertOk()->assertHeader('Cache-Control', 'max-age=86400, private');

    // O Comando Geral (administrador).
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin)->get($endereco)->assertOk();

    // Outra pessoa qualquer: 404 (não confirma nem que a foto existe).
    $this->actingAs(User::factory()->create())->get($endereco)->assertNotFound();

    // Visitante sem login: vai para a tela de entrar.
    auth()->logout();
    $this->get($endereco)->assertRedirect(route('login'));
});

test('instrutor vê a foto só dos alunos matriculados no curso DELE', function () {
    $this->actingAs($this->pessoa)->post(route('foto.update'), ['foto' => UploadedFile::fake()->image('eu.jpg', 400, 400)]);
    $endereco = route('usuarios.foto', $this->pessoa->id);

    $instrutor = User::factory()->create();
    $instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));

    // Ainda sem matrícula no curso dele: não vê.
    $this->actingAs($instrutor)->get($endereco)->assertNotFound();

    // Curso gratuito do instrutor, com a pessoa matriculada: passa a ver.
    $curso = Curso::create(['titulo' => 'Tiro tático', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $curso->forceFill(['situacao' => 'publicado', 'responsavel_id' => $instrutor->id])->save();
    app(Matriculas::class)->matricular($curso, $this->pessoa);

    $this->actingAs($instrutor->fresh())->get($endereco)->assertOk();
});

test('a lista de inscritos traz a foto para quem pode ver (e nunca o nome)', function () {
    $this->actingAs($this->pessoa)->post(route('foto.update'), ['foto' => UploadedFile::fake()->image('eu.jpg', 400, 400)]);

    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $curso = Curso::create(['titulo' => 'Defesa pessoal', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $curso->forceFill(['situacao' => 'publicado'])->save();
    app(Matriculas::class)->matricular($curso, $this->pessoa);

    $this->actingAs($admin)
        ->get(route('comando-geral.cursos.inscritos', $curso->id))
        ->assertInertia(fn ($tela) => $tela
            ->where('inscritos.0.foto', $this->pessoa->fresh()->avatar)
            ->missing('inscritos.0.nome'));
});
