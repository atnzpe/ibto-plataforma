<?php

/*
 * Testes das telas de filiados (#37, bloco F2): quem entra, cadastrar pelo
 * formulário (cria a conta), editar, as mensagens de erro em português e a
 * LGPD (a lista não leva CPF, nascimento nem telefone). Dados INVENTADOS.
 */

use App\Enums\CategoriaFiliado;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\Papel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.admin_emails' => []]);
    $this->administradora = User::factory()->create();
    $this->administradora->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

/**
 * Formulário de filiado inventado.
 *
 * @param  array<string, mixed>  $troca
 * @return array<string, mixed>
 */
function formularioFiliado(array $troca = []): array
{
    return [
        'nome' => 'Filiado Teste',
        'email' => 'filiado@ibto.test',
        'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20',
        'telefone' => '(81) 99999-0000',
        'municipio' => 'Recife',
        'uf' => 'pe',
        'equipe_id' => Equipe::where('nome', 'Vulcan')->value('id'),
        'status' => 'ativo',
        ...$troca,
    ];
}

test('Administrador cadastra um filiado: a conta é criada e o registro é gerado', function () {
    $this->actingAs($this->administradora)
        ->post(route('cadastros.filiados.store'), formularioFiliado())
        ->assertRedirect(route('cadastros.filiados.index'))
        ->assertSessionHasNoErrors();

    $filiado = Filiado::firstOrFail();
    expect($filiado->registro)->toBe('BR'.now()->format('Y').'0001PE');
    expect($filiado->usuario->email)->toBe('filiado@ibto.test');
    expect($filiado->cpf)->toBe('52998224725');
    expect($filiado->telefone)->toBe('81999990000');
});

test('LGPD: a lista não leva CPF, nascimento nem telefone', function () {
    $this->actingAs($this->administradora)->post(route('cadastros.filiados.store'), formularioFiliado());

    $this->actingAs($this->administradora)
        ->get(route('cadastros.filiados.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/filiados/index')
            ->has('filiados.data', 1)
            ->missing('filiados.data.0.cpf')
            ->missing('filiados.data.0.nascimento')
            ->missing('filiados.data.0.telefone'));
});

test('recusa CPF inválido, CPF repetido, e-mail repetido e nascimento no futuro', function () {
    $this->actingAs($this->administradora)->post(route('cadastros.filiados.store'), formularioFiliado());

    $this->actingAs($this->administradora)
        ->post(route('cadastros.filiados.store'), formularioFiliado([
            'email' => 'filiado@ibto.test',
            'nascimento' => now()->addDay()->format('Y-m-d'),
        ]))
        ->assertSessionHasErrors([
            'cpf' => 'Já existe um filiado com esse CPF.',
            'email' => 'Já existe uma conta com esse e-mail.',
            'nascimento' => 'A data de nascimento precisa ser antes de hoje.',
        ]);

    $this->actingAs($this->administradora)
        ->post(route('cadastros.filiados.store'), formularioFiliado(['email' => 'outro@ibto.test', 'cpf' => '529.982.247-26']))
        ->assertSessionHasErrors(['cpf' => 'CPF inválido. Confira os números.']);

    expect(Filiado::count())->toBe(1);
});

test('editar troca o nome e o e-mail da conta e a equipe, sem acusar CPF repetido', function () {
    $this->actingAs($this->administradora)->post(route('cadastros.filiados.store'), formularioFiliado());
    $filiado = Filiado::firstOrFail();

    $this->actingAs($this->administradora)
        ->put(route('cadastros.filiados.update', $filiado), formularioFiliado([
            'nome' => 'Nome Trocado',
            'email' => 'novo@ibto.test',
            'equipe_id' => Equipe::where('nome', 'IBTO')->value('id'),
        ]))
        ->assertSessionHasNoErrors();

    $filiado->refresh();
    expect($filiado->usuario->name)->toBe('Nome Trocado');
    expect($filiado->usuario->email)->toBe('novo@ibto.test');
    expect($filiado->equipe->nome)->toBe('IBTO');
});

test('quem não tem filiados.gerenciar recebe 403', function () {
    $aluno = User::factory()->create();
    $aluno->papeis()->attach(Papel::where('slug', 'aluno')->value('id'));

    $this->actingAs($aluno)->get(route('cadastros.filiados.index'))->assertForbidden();
});

test('categoria (função) é gravada, aparece na lista e recusa valor fora da lista', function () {
    $this->actingAs($this->administradora)
        ->post(route('cadastros.filiados.store'), formularioFiliado(['categoria' => 'adm_equipe']))
        ->assertSessionHasNoErrors();

    expect(Filiado::firstOrFail()->categoria)->toBe(CategoriaFiliado::AdmEquipe);

    $this->actingAs($this->administradora)
        ->get(route('cadastros.filiados.index'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('filiados.data.0.categoria', 'Administrador de Equipe'));

    $this->actingAs($this->administradora)
        ->post(route('cadastros.filiados.store'), formularioFiliado(['email' => 'x@ibto.test', 'cpf' => '111.444.777-35', 'categoria' => 'grao_mestre']))
        ->assertSessionHasErrors('categoria');
});

test('documentação do IBTO: padrão "sim"; desmarcada fica "não"; a edição sem o campo mantém', function () {
    // Sem o campo: vale o padrão (a maioria dos filiados já tem carteirinha).
    $this->actingAs($this->administradora)->post(route('cadastros.filiados.store'), formularioFiliado());
    $comPadrao = Filiado::firstOrFail();
    expect($comPadrao->documentacao_emitida)->toBeTrue();

    // Veio de outra entidade: desmarcada (a tela manda "0").
    $this->actingAs($this->administradora)->post(route('cadastros.filiados.store'), formularioFiliado([
        'email' => 'outra@ibto.test', 'cpf' => '111.444.777-35', 'documentacao_emitida' => '0',
    ]));
    expect(Filiado::whereHas('usuario', fn ($q) => $q->where('email', 'outra@ibto.test'))->value('documentacao_emitida'))->toBeFalse();
});
