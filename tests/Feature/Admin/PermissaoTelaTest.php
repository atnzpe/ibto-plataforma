<?php

/*
 * Testes da tela de papéis × permissões (#36): quem entra, gravar a grade,
 * a auditoria de cada mudança e a trava (a Tecnologia nunca perde
 * "permissoes.gerenciar").
 */

use App\Models\Auditoria;
use App\Models\Papel;
use App\Models\Permissao;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.admin_emails' => []]);
    // Uma pessoa do Time de Tecnologia (sem ser chave de emergência).
    $this->tecnologia = User::factory()->create();
    $this->tecnologia->papeis()->attach(Papel::where('slug', 'tecnologia')->value('id'));
});

/**
 * Monta a grade atual (como a tela envia), para cada teste mudar só uma caixa.
 *
 * @return array<int, list<int>>
 */
function gradeAtual(): array
{
    return Papel::with('permissoes:id')->get()
        ->mapWithKeys(fn (Papel $papel) => [$papel->id => $papel->permissoes->pluck('id')->all()])
        ->all();
}

test('Tecnologia abre a grade; Administrador recebe 403', function () {
    $this->actingAs($this->tecnologia)
        ->get(route('admin.permissoes.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('admin/permissoes')
            ->has('papeis', 6)
            ->has('permissoes', 11)
            ->where('travadas.0', ['papel' => 'tecnologia', 'permissao' => 'permissoes.gerenciar']));

    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    $this->actingAs($admin)->get(route('admin.permissoes.edit'))->assertForbidden();
});

test('tirar uma permissão grava a mudança e registra na auditoria', function () {
    $administrador = Papel::where('slug', 'administrador')->firstOrFail();
    $equipes = Permissao::where('slug', 'cadastros.equipes')->value('id');

    // Tira "cadastros.equipes" do Administrador.
    $grade = gradeAtual();
    $grade[$administrador->id] = array_values(array_diff($grade[$administrador->id], [$equipes]));

    $this->actingAs($this->tecnologia)
        ->put(route('admin.permissoes.update'), ['vinculos' => $grade])
        ->assertRedirect(route('admin.permissoes.edit'))
        ->assertSessionHas('sucesso', 'Permissões salvas (1 mudança(s)).');

    expect($administrador->permissoes()->pluck('slug'))->not->toContain('cadastros.equipes');

    // Auditoria: quem, o quê (com códigos, sem dados pessoais).
    $linha = Auditoria::latest('id')->firstOrFail();
    expect($linha->acao)->toBe('PERMISSAO_REMOVIDA');
    expect($linha->user_id)->toBe($this->tecnologia->id);
    // toEqual (e não toBe): compara o conteúdo sem ligar para a ORDEM das chaves,
    // porque o MySQL (servidor e CI) reordena as chaves de um JSON e o SQLite não.
    expect($linha->detalhes)->toEqual(['papel' => 'administrador', 'permissao' => 'cadastros.equipes']);
});

test('trava: mesmo enviando a grade vazia, a Tecnologia continua com permissoes.gerenciar', function () {
    $this->actingAs($this->tecnologia)
        ->put(route('admin.permissoes.update'), ['vinculos' => []])
        ->assertRedirect();

    $tecnologia = Papel::where('slug', 'tecnologia')->firstOrFail();
    expect($tecnologia->permissoes()->pluck('slug')->all())->toBe(['permissoes.gerenciar']);

    // E a pessoa da Tecnologia ainda consegue abrir a tela.
    $this->actingAs($this->tecnologia->fresh())->get(route('admin.permissoes.edit'))->assertOk();
});

test('salvar sem mudar nada não grava auditoria', function () {
    $this->actingAs($this->tecnologia)
        ->put(route('admin.permissoes.update'), ['vinculos' => gradeAtual()])
        ->assertSessionHas('sucesso', 'Nada mudou.');

    expect(Auditoria::count())->toBe(0);
});

test('recusa permissão que não existe', function () {
    $grade = gradeAtual();
    $grade[array_key_first($grade)][] = 99999;

    $this->actingAs($this->tecnologia)
        ->put(route('admin.permissoes.update'), ['vinculos' => $grade])
        ->assertSessionHasErrors();
});
