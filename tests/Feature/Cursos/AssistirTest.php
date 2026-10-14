<?php

/*
 * Testes da SALA DE AULA (#39, bloco C4c): aulas em sequência (módulo por
 * módulo), "Anterior" / "Próxima", posição "2 / 3", aulas e módulos
 * desativados fora da sequência, aula de outro curso = 404, sem acesso = 403,
 * e o botão "Assistir as aulas" na página do curso só para quem pode.
 * Dados INVENTADOS.
 */

use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoModulo;
use App\Models\Papel;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    $this->curso = Curso::create([
        'titulo' => 'APH Tático', 'modalidade' => 'online', 'acesso' => 'restrito',
        'valor_centavos' => 0,
    ]);
    // A situação não entra pelo create (só muda pelo fluxo da chancela): forceFill.
    $this->curso->forceFill(['situacao' => 'publicado'])->save();

    // Módulo 1 com 2 aulas (+ 1 desativada); módulo 2 com 1 aula; módulo 3 desativado.
    $m1 = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Básico', 'ordem' => 1, 'ativo' => true]);
    $m2 = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Avançado', 'ordem' => 2, 'ativo' => true]);
    $m3 = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Escondido', 'ordem' => 3, 'ativo' => false]);

    $this->a1 = $m1->aulas()->create(['titulo' => 'Aula 1', 'ordem' => 1, 'ativa' => true, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
    $this->desativada = $m1->aulas()->create(['titulo' => 'Rascunho', 'ordem' => 2, 'ativa' => false]);
    $this->a2 = $m1->aulas()->create(['titulo' => 'Aula 2', 'ordem' => 3, 'ativa' => true]);
    $this->a3 = $m2->aulas()->create(['titulo' => 'Aula 3', 'ordem' => 1, 'ativa' => true]);
    $this->escondida = $m3->aulas()->create(['titulo' => 'Do módulo escondido', 'ordem' => 1, 'ativa' => true]);
});

test('sequência: a aula do meio tem anterior, próxima e a posição "2 / 3"', function () {
    $this->actingAs($this->admin)
        ->get(route('cursos.assistir', ['curso' => $this->curso->id, 'aula' => $this->a2->id]))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('cursos/assistir')
            ->where('posicao', 2)
            ->where('total', 3)
            ->where('anterior_id', $this->a1->id)
            ->where('proxima_id', $this->a3->id)
            ->where('aula.modulo', 'Básico')
            ->has('aulas', 3));
});

test('primeira e última: sem anterior / sem próxima; o vídeo do YouTube vem pronto para tocar', function () {
    $this->actingAs($this->admin)
        ->get(route('cursos.assistir', ['curso' => $this->curso->id, 'aula' => $this->a1->id]))
        ->assertInertia(fn ($tela) => $tela->where('anterior_id', null)->where('aula.video.tipo', 'youtube'));

    $this->actingAs($this->admin)
        ->get(route('cursos.assistir', ['curso' => $this->curso->id, 'aula' => $this->a3->id]))
        ->assertInertia(fn ($tela) => $tela->where('proxima_id', null));
});

test('aula desativada, de módulo desativado ou de outro curso: 404', function () {
    $outro = Curso::create(['titulo' => 'Outro', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 0, 'situacao' => 'publicado']);

    foreach ([
        [$this->curso->id, $this->desativada->id],
        [$this->curso->id, $this->escondida->id],
        [$outro->id, $this->a1->id],
    ] as [$curso, $aula]) {
        $this->actingAs($this->admin)
            ->get(route('cursos.assistir', ['curso' => $curso, 'aula' => $aula]))
            ->assertNotFound();
    }
});

test('sem acesso ao conteúdo: 403; sem login: vai para o login', function () {
    $endereco = route('cursos.assistir', ['curso' => $this->curso->id, 'aula' => $this->a1->id]);

    $this->get($endereco)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($endereco)->assertForbidden();
});

test('página do curso: "pode_assistir" só para quem tem acesso ao conteúdo', function () {
    $this->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('pode_assistir', false));

    $this->actingAs($this->admin)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('pode_assistir', true));
});

test('a mesma aula não aparece duas vezes, e a ordem segue o módulo e depois a aula', function () {
    $this->actingAs($this->admin)
        ->get(route('cursos.assistir', ['curso' => $this->curso->id, 'aula' => $this->a1->id]))
        ->assertInertia(fn ($tela) => $tela->where('aulas', fn ($aulas) => collect($aulas)->pluck('id')->all() === [$this->a1->id, $this->a2->id, $this->a3->id]));

    expect(CursoAula::count())->toBe(5);
});
