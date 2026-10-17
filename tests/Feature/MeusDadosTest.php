<?php

/*
 * Testes de MEUS DADOS (LGPD, #47): abrir a ficha com CPF fica registrado; o
 * filiado vê quem abriu (nome abreviado) e os próprios dados MASCARADOS; o CPF
 * inteiro nunca sai na tela "Meus dados" nem na auditoria. Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Http\Controllers\Settings\MeusDadosController;
use App\Models\Auditoria;
use App\Models\Equipe;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Beltrano Teste', 'email' => 'beltrano@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => Equipe::factory()->create()->id,
    ]);
    $this->filiado->usuario->markEmailAsVerified();
    $this->admin = User::factory()->create(['name' => 'Ciclana Admin Souza']);
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

test('abrir a ficha com CPF fica na auditoria, só com o número do filiado', function () {
    $this->actingAs($this->admin)->get(route('cadastros.filiados.edit', $this->filiado))->assertOk();

    $linha = Auditoria::where('acao', MeusDadosController::ACESSO_DADOS_SENSIVEIS)->sole();
    expect($linha->user_id)->toBe($this->admin->id)
        ->and($linha->detalhes)->toBe(['filiado_id' => $this->filiado->id]);
});

test('o filiado vê quem abriu a ficha e os dados mascarados, sem o CPF inteiro', function () {
    $this->actingAs($this->admin)->get(route('cadastros.filiados.edit', $this->filiado));

    $resposta = $this->actingAs($this->filiado->usuario)->get(route('meus-dados.show'));
    $resposta->assertOk()->assertInertia(fn ($tela) => $tela
        ->where('filiado.cpf', '•••••••••25')
        ->where('acessos.0.quem', 'Ciclana A. S.')
        ->has('acessos', 1));
    expect($resposta->getContent())->not->toContain('52998224725');
});

test('quem não é filiado vê a conta, sem ficha e sem acessos', function () {
    $this->actingAs(User::factory()->create())->get(route('meus-dados.show'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->where('filiado', null)->has('acessos', 0));
});
