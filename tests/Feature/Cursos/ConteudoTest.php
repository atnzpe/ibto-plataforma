<?php

/*
 * Testes do CONTEÚDO dos cursos (#39, bloco C2): módulos e aulas em ordem,
 * vídeo por link ou enviado (escolha de quem cadastra), apostila em PDF no
 * disco privado, liberação por nível ("o meu nível e o seguinte", lógica do
 * Dojo Manager) e o critério de aceite: sem acesso, não abre nem baixa,
 * mesmo com o link. Dados INVENTADOS.
 */

use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoModulo;
use App\Models\Nivel;
use App\Models\Papel;
use App\Models\User;
use App\Support\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($this->admin);

    $this->curso = Curso::create([
        'titulo' => 'APH Tático', 'modalidade' => 'online', 'acesso' => 'restrito',
        'valor_centavos' => 0, 'situacao' => 'publicado',
    ]);
});

/**
 * Campos de uma aula com vídeo por link; o teste troca só o que precisa.
 *
 * @param  array<string, mixed>  $troca
 * @return array<string, mixed>
 */
function aulaValida(array $troca = []): array
{
    return ['titulo' => 'Aula 1', 'fonte_video' => 'link', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'ativa' => '1', ...$troca];
}

test('links do YouTube em vários formatos viram o mesmo código; .mp4 https toca direto; outro link é recusado', function () {
    foreach ([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s',
        'https://youtu.be/dQw4w9WgXcQ?si=abc',
        'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://youtube.com/shorts/dQw4w9WgXcQ',
    ] as $link) {
        expect(Video::paraTocar($link)['id'] ?? null)->toBe('dQw4w9WgXcQ');
    }
    expect(Video::paraTocar('https://cdn.exemplo.com/aula.mp4')['tipo'] ?? null)->toBe('arquivo');
    expect(Video::paraTocar('http://cdn.exemplo.com/aula.mp4'))->toBeNull();
    expect(Video::paraTocar('https://vimeo.com/123'))->toBeNull();
    expect(Video::paraTocar('https://evil.com/watch?v=dQw4w9WgXcQ'))->toBeNull();
});

test('módulos e aulas entram no fim e mudam de lugar com subir/descer', function () {
    $this->post(route('comando-geral.modulos.store', $this->curso), ['titulo' => 'M1', 'ativo' => '1']);
    $this->post(route('comando-geral.modulos.store', $this->curso), ['titulo' => 'M2', 'ativo' => '1']);
    [$m1, $m2] = CursoModulo::orderBy('id')->get();
    expect([$m1->ordem, $m2->ordem])->toBe([1, 2]);

    $this->post(route('comando-geral.modulos.mover', $m2), ['direcao' => 'subir']);
    expect($this->curso->modulos()->pluck('titulo')->all())->toBe(['M2', 'M1']);

    $this->post(route('comando-geral.aulas.store', $m1), aulaValida(['titulo' => 'A']));
    $this->post(route('comando-geral.aulas.store', $m1), aulaValida(['titulo' => 'B']));
    $b = CursoAula::where('titulo', 'B')->first();
    $this->post(route('comando-geral.aulas.mover', $b), ['direcao' => 'subir']);
    expect($m1->aulas()->pluck('titulo')->all())->toBe(['B', 'A']);
});

test('link de vídeo inválido é recusado (VIDEO_INVALIDO)', function () {
    $this->post(route('comando-geral.modulos.store', $this->curso), ['titulo' => 'M1', 'ativo' => '1']);

    $this->post(route('comando-geral.aulas.store', CursoModulo::first()), aulaValida(['video_url' => 'https://vimeo.com/123']))
        ->assertSessionHasErrors('video_url');
    expect(session('errors')->first('video_url'))->toContain('VIDEO_INVALIDO');
});

test('vídeo ENVIADO: guardado no disco privado; trocar para link apaga o arquivo', function () {
    Storage::fake('local');
    $this->post(route('comando-geral.modulos.store', $this->curso), ['titulo' => 'M1', 'ativo' => '1']);

    $this->post(route('comando-geral.aulas.store', CursoModulo::first()), aulaValida([
        'fonte_video' => 'arquivo',
        'video_url' => '',
        'video_arquivo' => UploadedFile::fake()->create('aula.mp4', 500, 'video/mp4'),
    ]))->assertSessionHasNoErrors();

    $aula = CursoAula::first();
    expect($aula->video()['tipo'])->toBe('enviado');
    Storage::disk('local')->assertExists($aula->video_arquivo);

    // Trocar para link: o arquivo sai do servidor.
    $arquivo = $aula->video_arquivo;
    $this->put(route('comando-geral.aulas.update', $aula), aulaValida(['titulo' => 'Aula 1']));
    Storage::disk('local')->assertMissing($arquivo);
    expect($aula->fresh()->video()['tipo'])->toBe('youtube');
});

test('vídeo acima do limite é recusado', function () {
    Storage::fake('local');
    $this->post(route('comando-geral.modulos.store', $this->curso), ['titulo' => 'M1', 'ativo' => '1']);

    $this->post(route('comando-geral.aulas.store', CursoModulo::first()), aulaValida([
        'fonte_video' => 'arquivo',
        'video_arquivo' => UploadedFile::fake()->create('grande.mp4', CursoAula::VIDEO_MAXIMO_KB + 1, 'video/mp4'),
    ]))->assertSessionHasErrors('video_arquivo');
});

test('apostila e vídeo: sem acesso, 403 mesmo com o link; com acesso, entrega', function () {
    Storage::fake('local');
    $this->post(route('comando-geral.modulos.store', $this->curso), ['titulo' => 'M1', 'ativo' => '1']);
    $this->post(route('comando-geral.aulas.store', CursoModulo::first()), aulaValida([
        'fonte_video' => 'arquivo',
        'video_arquivo' => UploadedFile::fake()->create('aula.mp4', 100, 'video/mp4'),
        'apostila' => UploadedFile::fake()->create('apostila.pdf', 100, 'application/pdf'),
    ]));
    $aula = CursoAula::first();

    // Comando Geral: baixa e assiste.
    $this->get(route('cursos.aulas.apostila', $aula))->assertOk();
    $this->get(route('cursos.aulas.video', $aula))->assertOk();

    // Aluno sem acesso: 403 nos dois, mesmo sabendo o endereço.
    $aluno = User::factory()->create();
    $aluno->papeis()->attach(Papel::where('slug', 'aluno')->value('id'));
    $this->actingAs($aluno)->get(route('cursos.aulas.apostila', $aula))->assertForbidden();
    $this->actingAs($aluno)->get(route('cursos.aulas.video', $aula))->assertForbidden();
});

test('liberação por nível: o meu nível e o seguinte (lógica do Dojo Manager)', function () {
    $posicoes = Nivel::posicoes();
    // Níveis em ordem: 1 Praticante, 2 Monitor I, 3 Monitor II, 4 Instrutor I...
    $porPosicao = array_flip($posicoes);
    $modulo = new CursoModulo(['nivel_minimo_id' => $porPosicao[3]]);

    // Monitor II (posição 3) exigido: Monitor I (2) vê (é o "seguinte" dele); Praticante (1) não.
    expect($modulo->liberadoPara($porPosicao[2], $posicoes))->toBeTrue();
    expect($modulo->liberadoPara($porPosicao[1], $posicoes))->toBeFalse();
    // Sem nível (público): só vê módulos do 1º nível.
    expect($modulo->liberadoPara(null, $posicoes))->toBeFalse();
    expect((new CursoModulo(['nivel_minimo_id' => $porPosicao[1]]))->liberadoPara(null, $posicoes))->toBeTrue();
    // Módulo sem nível mínimo: todos.
    expect((new CursoModulo)->liberadoPara(null, $posicoes))->toBeTrue();
});
