<?php

/*
 * Testes do LIMITE do Administrador de equipe (#37, F3b): ele vê, abre,
 * edita e cadastra SÓ filiados da própria equipe; o Administrador do IBTO
 * continua vendo todos. Dados INVENTADOS (CPFs de exemplo público).
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    config(['app.admin_emails' => []]);

    $this->vulcan = Equipe::where('nome', 'Vulcan')->value('id');
    $this->dragao = Equipe::where('nome', 'Dragão Negro')->value('id');

    $criar = app(CriarFiliado::class);
    $base = ['nascimento' => '1990-05-20', 'municipio' => 'Recife', 'uf' => 'PE'];

    // O responsável da Vulcan: é um filiado da Vulcan com o papel Administrador de equipe.
    $this->responsavel = $criar->executar([...$base, 'nome' => 'Responsável Vulcan', 'email' => 'resp@ibto.test', 'cpf' => '529.982.247-25', 'equipe_id' => $this->vulcan])->usuario;
    $this->responsavel->papeis()->attach(Papel::where('slug', 'admin_equipe')->value('id'));
    // Ele já usou o convite (e-mail confirmado); sem isso, as telas com login
    // o mandariam para "confirme o seu e-mail".
    $this->responsavel->markEmailAsVerified();

    // Um filiado da Vulcan e um da Dragão Negro.
    $this->daVulcan = $criar->executar([...$base, 'nome' => 'Aluno Vulcan', 'email' => 'vulcan@ibto.test', 'cpf' => '111.444.777-35', 'equipe_id' => $this->vulcan]);
    $this->doDragao = $criar->executar([...$base, 'nome' => 'Aluno Dragão', 'email' => 'dragao@ibto.test', 'cpf' => '390.533.447-05', 'equipe_id' => $this->dragao]);
});

test('Administrador de equipe vê só os filiados da própria equipe, mesmo pedindo outra no filtro', function () {
    $this->actingAs($this->responsavel)
        ->get(route('cadastros.filiados.index', ['equipe' => $this->dragao]))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            // Ele mesmo + o aluno da Vulcan; o da Dragão Negro fica de fora.
            ->has('filiados.data', 2)
            ->where('equipe_escolhida', $this->vulcan)
            // Na caixa de equipes, só a dele.
            ->has('equipes', 1));
});

test('Administrador de equipe abre um filiado da sua equipe, mas não de outra (403)', function () {
    $this->actingAs($this->responsavel)
        ->get(route('cadastros.filiados.edit', $this->daVulcan))
        ->assertOk();

    $this->actingAs($this->responsavel)
        ->get(route('cadastros.filiados.edit', $this->doDragao))
        ->assertForbidden();
});

test('Administrador de equipe não consegue mudar um filiado de outra equipe, nem mandar o seu para outra', function () {
    $formulario = fn (Filiado $filiado, int $equipe) => [
        'nome' => 'Nome Trocado', 'email' => $filiado->usuario->email, 'cpf' => $filiado->cpf,
        'nascimento' => '1990-05-20', 'municipio' => 'Recife', 'uf' => 'PE',
        'equipe_id' => $equipe, 'status' => 'ativo',
    ];

    // De outra equipe: barrado.
    $this->actingAs($this->responsavel)
        ->put(route('cadastros.filiados.update', $this->doDragao), $formulario($this->doDragao, $this->dragao))
        ->assertForbidden();

    // Da equipe dele, tentando mandar para a Dragão Negro: salva, mas a equipe não muda.
    $this->actingAs($this->responsavel)
        ->put(route('cadastros.filiados.update', $this->daVulcan), $formulario($this->daVulcan, $this->dragao))
        ->assertRedirect();

    expect($this->daVulcan->fresh()->equipe_id)->toBe($this->vulcan);
});

test('Administrador de equipe cadastra sempre na própria equipe', function () {
    $this->actingAs($this->responsavel)
        ->post(route('cadastros.filiados.store'), [
            'nome' => 'Novo Aluno', 'email' => 'novo@ibto.test', 'cpf' => '935.411.347-80',
            'nascimento' => '2000-01-01', 'municipio' => 'Recife', 'uf' => 'PE',
            // Tenta cadastrar na Dragão Negro.
            'equipe_id' => $this->dragao, 'status' => 'ativo',
        ])
        ->assertSessionHasNoErrors();

    expect(Filiado::whereHas('usuario', fn ($q) => $q->where('email', 'novo@ibto.test'))->value('equipe_id'))
        ->toBe($this->vulcan);
});

test('Administrador do IBTO continua vendo todos', function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    $this->actingAs($admin)
        ->get(route('cadastros.filiados.index'))
        ->assertInertia(fn (Assert $pagina) => $pagina->has('filiados.data', 3));

    $this->actingAs($admin)->get(route('cadastros.filiados.edit', $this->doDragao))->assertOk();
});
