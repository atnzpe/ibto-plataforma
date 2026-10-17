<?php

/*
 * Testes da CARGA DO CONTEÚDO EXTRA DO PILOTO (17/10/2026): cria o Curso Tático
 * Avançado como rascunho, os 6 posts (um de cada situação) e os 4 álbuns com as
 * fotos geradas; não duplica ao rodar de novo; o público vê só o que está
 * publicado e já no ar (nem o agendado, nem o rascunho).
 */

use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('cria o curso em rascunho, 6 posts e 4 álbuns com fotos, sem duplicar', function () {
    $this->artisan('plataforma:carregar-conteudo-piloto')->assertSuccessful();
    $this->artisan('plataforma:carregar-conteudo-piloto')->assertSuccessful();

    $curso = Curso::where('titulo', 'like', 'Curso Tático Avançado%')->sole();
    // Rascunho (sem valor no cartaz), com o cartaz como capa.
    Storage::disk('public')->assertExists($curso->imagem);
    expect($curso->situacao)->toBe(SituacaoCurso::Rascunho)
        ->and($curso->data_inicio->toDateString())->toBe('2027-01-23')
        ->and($curso->modulos()->first()->aulas()->count())->toBe(2)
        ->and(Post::count())->toBe(6)
        ->and(GaleriaAlbum::count())->toBe(4)
        ->and(GaleriaFoto::count())->toBe(11);

    // As imagens geradas foram gravadas.
    Storage::disk('public')->assertExists(GaleriaFoto::first()->caminho);
});

test('o blog público mostra só os posts publicados e já no ar', function () {
    $this->artisan('plataforma:carregar-conteudo-piloto');

    // Os títulos que a lista pública recebe (só os 2 publicados e já no ar).
    $this->get(route('blog.index'))->assertOk()->assertInertia(fn ($tela) => $tela
        ->has('posts.data', 2)
        ->where('posts.data', fn ($posts) => collect($posts)->pluck('titulo')->sort()->values()->all() === [
            '[TESTE] Inscrições abertas: Curso Tático Avançado em janeiro',
            '[TESTE] Relato do encontro de instrutores',
        ]));
});
