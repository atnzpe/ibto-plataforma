<?php

/*
 * Testes do cadastro de níveis (#37): os 13 níveis iniciais, a ordem da
 * hierarquia, quem pode entrar e a REGRA DE NEGÓCIO do grau por categoria
 * (Praticante sem grau; Monitor até II; Instrutor e Instrutor Expert até V).
 */

use App\Enums\CategoriaNivel;
use App\Models\Nivel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.admin_emails' => ['admin@ibto.test']]);
    $this->admin = User::factory()->create(['email' => 'admin@ibto.test']);
});

test('a migration traz os 13 níveis da lista, todos ativos', function () {
    expect(Nivel::count())->toBe(13);
    expect(Nivel::where('ativo', false)->count())->toBe(0);
    // Praticante: um só, sem grau.
    expect(Nivel::where('categoria', 'Praticante')->whereNull('grau')->count())->toBe(1);
    // Monitor só até II; Instrutor Expert até V.
    expect(Nivel::where('categoria', 'Monitor')->max('grau'))->toBe(2);
    expect(Nivel::where('categoria', 'Instrutor Expert')->max('grau'))->toBe(5);
});

test('o grau máximo de cada categoria segue a lista do IBTO', function () {
    expect(CategoriaNivel::Praticante->grauMaximo())->toBe(0);
    expect(CategoriaNivel::Monitor->grauMaximo())->toBe(2);
    expect(CategoriaNivel::Instrutor->grauMaximo())->toBe(5);
    expect(CategoriaNivel::InstrutorExpert->grauMaximo())->toBe(5);
});

test('o nome é montado em algarismos romanos', function () {
    expect(Nivel::where('categoria', 'Instrutor Expert')->where('grau', 4)->firstOrFail()->nome())
        ->toBe('Instrutor Expert IV');
    expect(Nivel::where('categoria', 'Praticante')->firstOrFail()->nome())->toBe('Praticante');
});

test('a lista vem na ordem da hierarquia, não em ordem alfabética', function () {
    $this->actingAs($this->admin)
        ->get(route('cadastros.niveis.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/niveis/index')
            ->has('niveis', 13)
            ->where('niveis.0.nome', 'Praticante')
            ->where('niveis.1.nome', 'Monitor I')
            ->where('niveis.3.nome', 'Instrutor I')
            ->where('niveis.12.nome', 'Instrutor Expert V'));
});

test('usuário que não é administrador recebe 403', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cadastros.niveis.index'))
        ->assertForbidden();
});

test('regra do grau: recusa Monitor III, Praticante com grau e Instrutor sem grau', function () {
    $rota = route('cadastros.niveis.store');

    $this->actingAs($this->admin)
        ->post($rota, ['categoria' => 'Monitor', 'grau' => 3, 'ativo' => true])
        ->assertSessionHasErrors(['grau' => 'Monitor vai só até o grau 2.']);

    $this->actingAs($this->admin)
        ->post($rota, ['categoria' => 'Praticante', 'grau' => 1, 'ativo' => true])
        ->assertSessionHasErrors(['grau' => 'Praticante não tem grau.']);

    $this->actingAs($this->admin)
        ->post($rota, ['categoria' => 'Instrutor', 'grau' => '', 'ativo' => true])
        ->assertSessionHasErrors(['grau' => 'Informe o grau de Instrutor.']);

    // Categoria que não existe.
    $this->actingAs($this->admin)
        ->post($rota, ['categoria' => 'Grão-Mestre', 'grau' => 1, 'ativo' => true])
        ->assertSessionHasErrors(['categoria' => 'Categoria inválida.']);

    // Nada foi criado.
    expect(Nivel::count())->toBe(13);
});

test('não deixa repetir um nível (inclusive o Praticante, sem grau)', function () {
    $rota = route('cadastros.niveis.store');

    $this->actingAs($this->admin)
        ->post($rota, ['categoria' => 'Instrutor', 'grau' => 3, 'ativo' => true])
        ->assertSessionHasErrors(['grau' => 'Esse nível já está cadastrado.']);

    $this->actingAs($this->admin)
        ->post($rota, ['categoria' => 'Praticante', 'grau' => '', 'ativo' => true])
        ->assertSessionHasErrors(['grau' => 'Esse nível já está cadastrado.']);
});

test('cadastra um nível que faltava e desativa outro', function () {
    // Tira o Monitor II e cadastra de novo pela tela.
    Nivel::where('categoria', 'Monitor')->where('grau', 2)->delete();

    $this->actingAs($this->admin)
        ->post(route('cadastros.niveis.store'), ['categoria' => 'Monitor', 'grau' => 2, 'ativo' => true])
        ->assertRedirect(route('cadastros.niveis.index'))
        ->assertSessionHas('sucesso', 'Nível "Monitor II" cadastrado.');

    // Desativar o Praticante (salvar sem mudar categoria/grau não acusa repetido).
    $praticante = Nivel::where('categoria', 'Praticante')->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('cadastros.niveis.update', $praticante), ['categoria' => 'Praticante', 'grau' => '', 'ativo' => false])
        ->assertSessionHasNoErrors();

    expect($praticante->fresh()->ativo)->toBeFalse();
    expect(Nivel::ativos()->count())->toBe(12);
});

test('formulário recebe as categorias com o grau máximo de cada uma', function () {
    $this->actingAs($this->admin)
        ->get(route('cadastros.niveis.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/niveis/formulario')
            ->where('nivel', null)
            ->has('categorias', 4)
            ->where('categorias.1', ['valor' => 'Monitor', 'grau_maximo' => 2]));
});
