<?php

/*
 * Testes da tela de usuários (#36): busca, dar e tirar papéis com auditoria
 * e a proteção contra "escalada de privilégio" (um Administrador não pode se
 * dar o papel Tecnologia nem tirá-lo de ninguém).
 */

use App\Models\Auditoria;
use App\Models\Papel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.admin_emails' => []]);
    $this->idDe = fn (string $slug): int => Papel::where('slug', $slug)->value('id');

    // Uma pessoa com o papel Administrador (tem usuarios.gerenciar, não tem permissoes.gerenciar).
    $this->administradora = User::factory()->create(['name' => 'Administradora Teste']);
    $this->administradora->papeis()->attach(($this->idDe)('administrador'));

    // Uma pessoa do Time de Tecnologia (tem as duas).
    $this->tecnologia = User::factory()->create(['name' => 'Tecnologia Teste']);
    $this->tecnologia->papeis()->attach(($this->idDe)('tecnologia'));
});

test('busca por nome ou e-mail', function () {
    User::factory()->create(['name' => 'Fulano da Vulcan', 'email' => 'fulano@vulcan.test']);
    User::factory()->create(['name' => 'Beltrano', 'email' => 'beltrano@outra.test']);

    $this->actingAs($this->administradora)
        ->get(route('admin.usuarios.index', ['busca' => 'vulcan']))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('admin/usuarios/index')
            ->has('usuarios.data', 1)
            ->where('usuarios.data.0.nome', 'Fulano da Vulcan'));
});

test('dar o papel Filiado grava e registra na auditoria (só números e códigos)', function () {
    $pessoa = User::factory()->create(['email' => 'pessoa@ibto.test']);

    $this->actingAs($this->administradora)
        ->put(route('admin.usuarios.update', $pessoa), ['papeis' => [($this->idDe)('filiado')]])
        ->assertRedirect(route('admin.usuarios.index'));

    expect($pessoa->papeis()->pluck('slug')->all())->toBe(['filiado']);

    $linha = Auditoria::latest('id')->firstOrFail();
    expect($linha->acao)->toBe('PAPEL_CONCEDIDO');
    expect($linha->detalhes)->toBe(['usuario_id' => $pessoa->id, 'papel' => 'filiado']);
    // LGPD: o e-mail não vai para a auditoria.
    expect(json_encode($linha->detalhes))->not->toContain('pessoa@ibto.test');
});

test('escalada de privilégio: Administrador não consegue se dar o papel Tecnologia', function () {
    $this->actingAs($this->administradora)
        ->put(route('admin.usuarios.update', $this->administradora), [
            'papeis' => [($this->idDe)('administrador'), ($this->idDe)('tecnologia')],
        ])
        ->assertRedirect();

    expect($this->administradora->papeis()->pluck('slug')->all())->toBe(['administrador']);
});

test('Administrador também não consegue TIRAR o papel Tecnologia de ninguém', function () {
    $this->actingAs($this->administradora)
        ->put(route('admin.usuarios.update', $this->tecnologia), ['papeis' => []])
        ->assertRedirect();

    expect($this->tecnologia->papeis()->pluck('slug')->all())->toBe(['tecnologia']);
});

test('Tecnologia pode dar o papel Tecnologia', function () {
    $pessoa = User::factory()->create();

    $this->actingAs($this->tecnologia)
        ->put(route('admin.usuarios.update', $pessoa), ['papeis' => [($this->idDe)('tecnologia')]])
        ->assertRedirect();

    expect($pessoa->papeis()->pluck('slug')->all())->toBe(['tecnologia']);
});

test('quem não tem usuarios.gerenciar recebe 403', function () {
    $aluno = User::factory()->create();
    $aluno->papeis()->attach(($this->idDe)('aluno'));

    $this->actingAs($aluno)->get(route('admin.usuarios.index'))->assertForbidden();
});
