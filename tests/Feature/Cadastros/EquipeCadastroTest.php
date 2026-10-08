<?php

/*
 * Testes do cadastro de equipes (#37): as 9 equipes iniciais, a regra para
 * ATIVAR, a criptografia do contato e do documento (LGPD), as áreas e a logo.
 * Todos os dados são inventados (nunca dados reais de pessoas).
 */

use App\Enums\StatusEquipe;
use App\Models\AreaAtuacao;
use App\Models\Equipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    config(['app.admin_emails' => ['admin@ibto.test']]);
    $this->admin = User::factory()->create(['email' => 'admin@ibto.test']);
    // Uma área ativa qualquer, para marcar nas equipes.
    $this->areaId = AreaAtuacao::where('nome', 'Krav Maga')->value('id');
});

/**
 * Formulário de equipe COMPLETA (dados inventados), para cada teste mudar só o que precisa.
 *
 * @param  array<string, mixed>  $troca  Campos a trocar. Ex.: ['status' => 'ativa']
 * @return array<string, mixed>
 */
function equipeCompleta(array $troca = []): array
{
    return [
        'nome' => 'Equipe Nova',
        'status' => 'ativa',
        'cidade' => 'Recife',
        'uf' => 'pe',
        'responsavel_nome' => 'Responsável Teste',
        'responsavel_telefone' => '(81) 99999-0000',
        'responsavel_email' => 'responsavel@ibto.test',
        'documento' => '11.222.333/0001-81',
        'areas' => [test()->areaId],
        ...$troca,
    ];
}

test('a migration traz as 9 equipes da planilha, em homologação', function () {
    expect(Equipe::count())->toBe(9);
    expect(Equipe::where('status', StatusEquipe::EmHomologacao)->count())->toBe(9);
    expect(Equipe::where('nome', 'Vulcan')->exists())->toBeTrue();
});

test('cadastra equipe ativa completa: telefone e documento só com números e UF em maiúsculas', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.equipes.store'), equipeCompleta())
        ->assertRedirect(route('cadastros.equipes.index'))
        ->assertSessionHasNoErrors();

    $equipe = Equipe::where('nome', 'Equipe Nova')->firstOrFail();

    expect($equipe->status)->toBe(StatusEquipe::Ativa);
    expect($equipe->uf)->toBe('PE');
    expect($equipe->responsavel_telefone)->toBe('81999990000');
    expect($equipe->documento)->toBe('11222333000181');
    expect($equipe->areas()->pluck('nome')->all())->toBe(['Krav Maga']);
});

test('LGPD: telefone, e-mail e documento ficam criptografados no banco', function () {
    $this->actingAs($this->admin)->post(route('cadastros.equipes.store'), equipeCompleta());

    // Lê a linha DIRETO do banco, sem passar pelo Model (como um invasor veria).
    $linha = DB::table('equipes')->where('nome', 'Equipe Nova')->first();

    expect($linha->responsavel_telefone)->not->toContain('81999990000');
    expect($linha->responsavel_email)->not->toContain('responsavel@ibto.test');
    expect($linha->documento)->not->toContain('11222333000181');
});

test('LGPD: a lista não leva contato nem documento para a tela', function () {
    Equipe::factory()->completa()->create(['nome' => 'Equipe Com Contato']);

    $this->actingAs($this->admin)
        ->get(route('cadastros.equipes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/equipes/index')
            ->has('equipes', 10)
            ->missing('equipes.0.responsavel_telefone')
            ->missing('equipes.0.responsavel_email')
            ->missing('equipes.0.documento'));
});

test('regra para ativar: sem cidade, estado, nome e telefone do responsável ou área, volta com os avisos', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.equipes.store'), equipeCompleta([
            'cidade' => '', 'uf' => '', 'responsavel_nome' => '', 'responsavel_telefone' => '', 'areas' => [],
        ]))
        ->assertSessionHasErrors([
            'cidade' => 'Preencha este campo para ativar a equipe.',
            'uf' => 'Preencha este campo para ativar a equipe.',
            'responsavel_nome' => 'Preencha este campo para ativar a equipe.',
            'responsavel_telefone' => 'Preencha este campo para ativar a equipe.',
            'areas' => 'Escolha pelo menos uma área de atuação para ativar a equipe.',
        ]);

    expect(Equipe::where('nome', 'Equipe Nova')->exists())->toBeFalse();
});

test('em homologação pode ficar incompleta (só o nome)', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.equipes.store'), ['nome' => 'Equipe Incompleta', 'status' => 'em_homologacao'])
        ->assertSessionHasNoErrors();

    expect(Equipe::where('nome', 'Equipe Incompleta')->exists())->toBeTrue();
});

test('recusa CPF/CNPJ com dígito errado, telefone curto, UF inexistente e nome repetido', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.equipes.store'), equipeCompleta([
            'nome' => 'Vulcan',
            'documento' => '11.222.333/0001-82',
            'responsavel_telefone' => '9999',
            'uf' => 'XX',
        ]))
        ->assertSessionHasErrors([
            'nome' => 'Já existe uma equipe com esse nome.',
            'documento' => 'CPF ou CNPJ inválido. Confira os números.',
            'responsavel_telefone' => 'Telefone com DDD: 10 ou 11 números.',
            'uf' => 'Escolha um estado da lista.',
        ]);
});

test('editar troca as áreas marcadas e a logo; a logo sai pela rota pública', function () {
    $equipe = Equipe::where('nome', 'Vulcan')->firstOrFail();
    $kapap = AreaAtuacao::where('nome', 'KAPAP')->value('id');

    $this->actingAs($this->admin)
        ->put(route('cadastros.equipes.update', $equipe), equipeCompleta([
            'nome' => 'Vulcan',
            'areas' => [$kapap],
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]))
        ->assertSessionHasNoErrors();

    $equipe->refresh();
    expect($equipe->areas()->pluck('nome')->all())->toBe(['KAPAP']);
    Storage::disk('public')->assertExists($equipe->logo);

    // Visitante sem login vê a logo (a rede nacional do site usa).
    $this->get(route('equipes.logo', $equipe))->assertOk();
});

test('usuário que não é administrador recebe 403', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cadastros.equipes.index'))
        ->assertForbidden();
});
