<?php

/*
 * Testes das telas do cadastro de áreas de atuação (#37): quem pode entrar,
 * cadastrar, editar, desativar e as mensagens de erro em português.
 */

use App\Models\AreaAtuacao;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Só este e-mail é administrador nos testes (como se viesse do .env).
    config(['app.admin_emails' => ['admin@ibto.test']]);
    $this->admin = User::factory()->create(['email' => 'admin@ibto.test']);
});

test('visitante sem login vai para a tela de entrar', function () {
    $this->get(route('cadastros.areas-atuacao.index'))
        ->assertRedirect(route('login'));
});

test('usuário que não é administrador recebe 403', function () {
    $aluno = User::factory()->create();

    $this->actingAs($aluno)
        ->get(route('cadastros.areas-atuacao.index'))
        ->assertForbidden();

    // Também não consegue gravar, mesmo mandando o formulário "na mão".
    $this->actingAs($aluno)
        ->post(route('cadastros.areas-atuacao.store'), ['nome' => 'Invasora', 'ativa' => true])
        ->assertForbidden();
    expect(AreaAtuacao::where('nome', 'Invasora')->exists())->toBeFalse();
});

test('administrador vê a lista com as 15 áreas', function () {
    $this->actingAs($this->admin)
        ->get(route('cadastros.areas-atuacao.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/areas-atuacao/index')
            ->has('areas', 15));
});

test('administrador cadastra uma área nova (espaços nas pontas são tirados)', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.areas-atuacao.store'), ['nome' => '  Tiro Defensivo ', 'ativa' => true])
        ->assertRedirect(route('cadastros.areas-atuacao.index'))
        ->assertSessionHas('sucesso');

    expect(AreaAtuacao::where('nome', 'Tiro Defensivo')->where('ativa', true)->exists())->toBeTrue();
});

test('nome repetido ou vazio volta com a mensagem em português', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.areas-atuacao.store'), ['nome' => 'Krav Maga', 'ativa' => true])
        ->assertSessionHasErrors(['nome' => 'Já existe uma área com esse nome.']);

    $this->actingAs($this->admin)
        ->post(route('cadastros.areas-atuacao.store'), ['nome' => '   ', 'ativa' => true])
        ->assertSessionHasErrors(['nome' => 'Informe o nome da área.']);

    $this->actingAs($this->admin)
        ->post(route('cadastros.areas-atuacao.store'), ['nome' => str_repeat('a', 101), 'ativa' => true])
        ->assertSessionHasErrors(['nome' => 'O nome pode ter no máximo 100 caracteres.']);
});

test('administrador desativa uma área, e salvar sem mudar o nome não acusa repetido', function () {
    $area = AreaAtuacao::where('nome', 'Selva')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('cadastros.areas-atuacao.update', $area), ['nome' => 'Selva', 'ativa' => false])
        ->assertRedirect(route('cadastros.areas-atuacao.index'))
        ->assertSessionHasNoErrors();

    expect($area->fresh()->ativa)->toBeFalse();
    // Desativada, ela some do filtro usado nas listas de escolha.
    expect(AreaAtuacao::ativas()->pluck('nome'))->not->toContain('Selva');
});

test('administrador abre o formulário de nova área e o de edição', function () {
    // Nova: a tela recebe area = null.
    $this->actingAs($this->admin)
        ->get(route('cadastros.areas-atuacao.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/areas-atuacao/formulario')
            ->where('area', null));

    // Edição: a tela recebe só id, nome e ativa (nada de datas internas).
    $area = AreaAtuacao::where('nome', 'KAPAP')->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('cadastros.areas-atuacao.edit', $area))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/areas-atuacao/formulario')
            ->where('area', ['id' => $area->id, 'nome' => 'KAPAP', 'ativa' => true]));
});

test('o menu "Áreas de atuação" só é liberado para o administrador', function () {
    // Administrador: pode.administrar = true (o menu aparece).
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('pode.administrar', true));

    // Usuário comum: false (o menu fica escondido).
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('pode.administrar', false));
});
