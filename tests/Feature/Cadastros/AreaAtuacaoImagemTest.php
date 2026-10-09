<?php

/*
 * Testes da imagem de cada área de atuação (#37): enviar, trocar (a antiga é
 * apagada), recusar arquivo que não é imagem e entregar a imagem pela rota pública.
 */

use App\Models\AreaAtuacao;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Disco "de mentira": os arquivos ficam numa pasta temporária do teste.
    Storage::fake('public');
    config(['app.admin_emails' => ['admin@ibto.test']]);
    $this->admin = User::factory()->create(['email' => 'admin@ibto.test']);
});

test('cadastra área com imagem: o arquivo é guardado com nome aleatório', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.areas-atuacao.store'), [
            'nome' => 'Tiro Defensivo',
            'ativa' => true,
            'imagem' => UploadedFile::fake()->image('Foto do Tiro (Gleyson).jpg', 800, 600),
        ])
        ->assertRedirect(route('cadastros.areas-atuacao.index'));

    $area = AreaAtuacao::where('nome', 'Tiro Defensivo')->firstOrFail();

    // O arquivo existe na pasta das áreas, sem o nome original (que pode ter dados pessoais).
    expect($area->imagem)->toStartWith('areas-atuacao/')
        ->not->toContain('Gleyson');
    Storage::disk('public')->assertExists($area->imagem);
});

test('editar sem escolher imagem mantém a atual; trocar apaga a antiga', function () {
    $area = AreaAtuacao::where('nome', 'Selva')->firstOrFail();
    $rota = route('cadastros.areas-atuacao.update', $area);

    // 1) Primeira imagem.
    $this->actingAs($this->admin)->put($rota, [
        'nome' => 'Selva', 'ativa' => true,
        'imagem' => UploadedFile::fake()->image('selva.png'),
    ]);
    $primeira = $area->fresh()->imagem;

    // 2) Salvar sem imagem nova: continua a mesma.
    $this->actingAs($this->admin)->put($rota, ['nome' => 'Selva', 'ativa' => true])
        ->assertSessionHasNoErrors();
    expect($area->fresh()->imagem)->toBe($primeira);

    // 3) Trocar: a nova fica, a antiga é apagada (não acumula arquivo).
    $this->actingAs($this->admin)->put($rota, [
        'nome' => 'Selva', 'ativa' => true,
        'imagem' => UploadedFile::fake()->image('selva-nova.webp'),
    ]);
    $segunda = $area->fresh()->imagem;

    expect($segunda)->not->toBe($primeira);
    Storage::disk('public')->assertExists($segunda);
    Storage::disk('public')->assertMissing($primeira);
});

test('recusa arquivo que não é imagem, SVG e imagem acima de 2 MB', function () {
    $rota = route('cadastros.areas-atuacao.store');

    // PDF com nome de imagem não engana: o Laravel olha o conteúdo.
    $this->actingAs($this->admin)->post($rota, [
        'nome' => 'Área A', 'ativa' => true,
        'imagem' => UploadedFile::fake()->create('foto.jpg', 100, 'application/pdf'),
    ])->assertSessionHasErrors('imagem');

    // SVG fica de fora (pode carregar código escondido).
    $this->actingAs($this->admin)->post($rota, [
        'nome' => 'Área B', 'ativa' => true,
        'imagem' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('imagem');

    // 3 MB: acima do limite de 2 MB.
    $this->actingAs($this->admin)->post($rota, [
        'nome' => 'Área C', 'ativa' => true,
        'imagem' => UploadedFile::fake()->image('grande.jpg')->size(3072),
    ])->assertSessionHasErrors(['imagem' => 'A imagem pode ter no máximo 2 MB.']);

    // Nenhuma das três áreas foi criada.
    expect(AreaAtuacao::whereIn('nome', ['Área A', 'Área B', 'Área C'])->count())->toBe(0);
});

test('a rota pública entrega a imagem, e dá 404 para área sem imagem', function () {
    $comImagem = AreaAtuacao::where('nome', 'KAPAP')->firstOrFail();
    $comImagem->forceFill([
        'imagem' => Storage::disk('public')->putFile('areas-atuacao', UploadedFile::fake()->image('k.png')),
    ])->save();

    // Visitante sem login consegue ver (o site usa esta imagem).
    $this->get(route('areas-atuacao.imagem', $comImagem))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, public');

    // Área criada só para o teste, sem imagem (as 15 do site já vêm com imagem).
    $semImagem = AreaAtuacao::factory()->create();
    $this->get(route('areas-atuacao.imagem', $semImagem))->assertNotFound();
});

test('a migration liga as 15 áreas do site às imagens que o site já usa', function () {
    // Nenhuma das 15 ficou sem imagem.
    expect(AreaAtuacao::whereNull('imagem')->count())->toBe(0);
    // Confere uma ligação pelo nome (sigla diferente do nome: TCCC).
    expect(AreaAtuacao::where('nome', 'Tactical Combat Casualty Care')->value('imagem'))
        ->toBe('areas-atuacao/tccc.webp');
});
