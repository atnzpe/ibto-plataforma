<?php

/*
 * Testes do DESFOQUE POR ÁREA (#90, 17/10/2026): a área marcada muda de verdade
 * (o resto da foto não); o original vai para a pasta privada e "Restaurar" o
 * devolve; desfocar de novo parte do original; áreas fora da foto são recusadas;
 * apagar a foto apaga o original; quem não cuida do blog recebe 403.
 * Imagens INVENTADAS (geradas no teste, sem pessoas reais).
 */

use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Gera uma foto PNG de teste com um quadriculado (cheia de detalhe, como um rosto).
 *
 * @return string Os bytes do PNG.
 */
function fotoQuadriculada(): string
{
    $imagem = imagecreatetruecolor(200, 100);
    $preto = (int) imagecolorallocate($imagem, 0, 0, 0);
    $branco = (int) imagecolorallocate($imagem, 255, 255, 255);
    for ($x = 0; $x < 200; $x += 4) {
        for ($y = 0; $y < 100; $y += 4) {
            imagefilledrectangle($imagem, $x, $y, $x + 3, $y + 3, (($x + $y) / 4) % 2 === 0 ? $preto : $branco);
        }
    }
    ob_start();
    imagepng($imagem);

    return (string) ob_get_clean();
}

/**
 * A cor (RGB) de um ponto da foto gravada.
 *
 * @return array{0: int, 1: int, 2: int}
 */
function corDoPonto(string $bytes, int $x, int $y): array
{
    $imagem = imagecreatefromstring($bytes);
    $cor = imagecolorat($imagem, $x, $y);

    return [($cor >> 16) & 0xFF, ($cor >> 8) & 0xFF, $cor & 0xFF];
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $album = GaleriaAlbum::create(['titulo' => 'Evento de teste', 'data_evento' => '2026-10-11', 'local_evento' => 'Recife - PE', 'credito_padrao' => 'IBTO']);
    Storage::disk('public')->put('galeria/'.$album->id.'/foto.png', fotoQuadriculada());
    $this->foto = new GaleriaFoto(['descricao' => 'Foto de teste', 'ordem' => 1]);
    $this->foto->forceFill(['album_id' => $album->id, 'caminho' => 'galeria/'.$album->id.'/foto.png'])->save();
    // A área da esquerda (metade da foto).
    $this->areas = [['x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 1]];
});

test('desfoca só a área marcada e guarda o original na pasta privada', function () {
    $antes = Storage::disk('public')->get($this->foto->caminho);

    $this->actingAs($this->admin)->post(route('comando-geral.galeria.fotos.desfocar', $this->foto), ['areas' => $this->areas])->assertSessionHasNoErrors();

    $depois = Storage::disk('public')->get($this->foto->caminho);
    $foto = $this->foto->fresh();

    // Dentro da área: o quadriculado preto/branco virou cinza (o detalhe sumiu).
    [$r] = corDoPonto($depois, 50, 50);
    expect($r)->toBeGreaterThan(40)->toBeLessThan(215);
    // Fora da área: igual ao original.
    expect(corDoPonto($depois, 150, 50))->toBe(corDoPonto($antes, 150, 50));
    // O original ficou na pasta privada, idêntico ao que foi enviado.
    expect($foto->original)->not->toBeNull()
        ->and(Storage::disk('local')->get($foto->original))->toBe($antes)
        ->and(json_decode((string) $foto->desfoque, true))->toHaveCount(1);
    $this->assertDatabaseHas('auditoria', ['acao' => 'FOTO_DESFOCADA']);
});

test('restaurar devolve o original e apaga a cópia privada', function () {
    $antes = Storage::disk('public')->get($this->foto->caminho);
    $this->actingAs($this->admin)->post(route('comando-geral.galeria.fotos.desfocar', $this->foto), ['areas' => $this->areas]);
    $original = $this->foto->fresh()->original;

    $this->actingAs($this->admin)->delete(route('comando-geral.galeria.fotos.restaurar', $this->foto))->assertRedirect();

    expect(Storage::disk('public')->get($this->foto->caminho))->toBe($antes)
        ->and($this->foto->fresh()->original)->toBeNull();
    Storage::disk('local')->assertMissing($original);
});

test('área fora da foto ou sem áreas: recusa', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.galeria.fotos.desfocar', $this->foto), ['areas' => [['x' => 2, 'y' => 0, 'w' => 0.5, 'h' => 0.5]]])
        ->assertSessionHasErrors('areas.0.x');
    $this->actingAs($this->admin)->post(route('comando-geral.galeria.fotos.desfocar', $this->foto), ['areas' => []])
        ->assertSessionHasErrors('areas');
});

test('apagar a foto apaga também o original guardado', function () {
    $this->actingAs($this->admin)->post(route('comando-geral.galeria.fotos.desfocar', $this->foto), ['areas' => $this->areas]);
    $original = $this->foto->fresh()->original;

    $this->actingAs($this->admin)->delete(route('comando-geral.galeria.fotos.apagar', $this->foto));

    Storage::disk('local')->assertMissing($original);
});

test('quem não cuida do blog não desfoca (403)', function () {
    $this->actingAs(User::factory()->create())->post(route('comando-geral.galeria.fotos.desfocar', $this->foto), ['areas' => $this->areas])->assertForbidden();
});
