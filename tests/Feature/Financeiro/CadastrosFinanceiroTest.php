<?php

/*
 * Testes dos CADASTROS do financeiro (#59): categorias e formas de pagamento.
 * Sem apagar (só desativar); categoria em uso não troca de tipo; auditoria.
 * Dados INVENTADOS.
 */

use App\Models\Auditoria;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\LivroCaixa;

beforeEach(function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin);
});

test('as listas abrem com os cadastros iniciais', function () {
    $this->get(route('financeiro.categorias.index'))
        ->assertInertia(fn ($tela) => $tela->component('financeiro/categorias/index')->has('categorias', 12)); // 9 iniciais + Venda de fotos e Fotógrafos (#90) + Aulas regulares (#80)
    $this->get(route('financeiro.formas.index'))
        ->assertInertia(fn ($tela) => $tela->component('financeiro/formas/index')->has('formas', 4));
});

test('nova categoria entra e vai para a auditoria; nome repetido no mesmo tipo é recusado', function () {
    $this->post(route('financeiro.categorias.store'), ['nome' => 'Patrocínio', 'tipo' => 'receita', 'ativa' => '1'])
        ->assertRedirect(route('financeiro.categorias.index'));
    expect(FinCategoria::where('nome', 'Patrocínio')->exists())->toBeTrue();
    expect(Auditoria::where('acao', 'CATEGORIA_CRIADA')->exists())->toBeTrue();

    $this->post(route('financeiro.categorias.store'), ['nome' => 'Patrocínio', 'tipo' => 'receita', 'ativa' => '1'])
        ->assertSessionHasErrors('nome');
    // O mesmo nome em OUTRO tipo pode.
    $this->post(route('financeiro.categorias.store'), ['nome' => 'Patrocínio', 'tipo' => 'despesa', 'ativa' => '1'])
        ->assertSessionHasNoErrors();
});

test('categoria SEM lançamentos pode trocar de tipo', function () {
    $doacao = FinCategoria::where('nome', 'Doação')->first();

    $this->put(route('financeiro.categorias.update', $doacao), ['nome' => 'Doação', 'tipo' => 'despesa', 'ativa' => '1'])
        ->assertSessionHasNoErrors();
    expect($doacao->fresh()->tipo)->toBe('despesa');
});

test('categoria EM USO não troca de tipo (CATEGORIA_EM_USO), mas pode ser desativada', function () {
    $anuidade = FinCategoria::where('nome', 'Anuidade')->first();
    app(LivroCaixa::class)->lancar([
        'tipo' => 'receita',
        'categoria_id' => $anuidade->id,
        'descricao' => 'Anuidade teste',
        'valor_centavos' => 9000,
        'vencimento' => '2027-01-10',
    ]);

    $this->put(route('financeiro.categorias.update', $anuidade), ['nome' => 'Anuidade', 'tipo' => 'despesa', 'ativa' => '1'])
        ->assertSessionHasErrors('tipo');
    expect($anuidade->fresh()->tipo)->toBe('receita');

    // Desativar (sem trocar o tipo) pode.
    $this->put(route('financeiro.categorias.update', $anuidade), ['nome' => 'Anuidade', 'tipo' => 'receita', 'ativa' => '0'])
        ->assertSessionHasNoErrors();
    expect($anuidade->fresh()->ativa)->toBeFalse();
});

test('categoria desativada some das opções do novo lançamento', function () {
    FinCategoria::where('nome', 'Doação')->update(['ativa' => false]);

    $this->get(route('financeiro.lancamentos.create'))
        ->assertInertia(fn ($tela) => $tela->has('categorias', 11)); // 12 menos a desativada
});

test('forma de pagamento: nova, nome único, desativar tira da baixa', function () {
    $this->post(route('financeiro.formas.store'), ['nome' => 'Boleto', 'ativa' => '1'])->assertSessionHasNoErrors();
    $this->post(route('financeiro.formas.store'), ['nome' => 'Boleto', 'ativa' => '1'])->assertSessionHasErrors('nome');

    $boleto = FinFormaPagamento::where('nome', 'Boleto')->first();
    $this->put(route('financeiro.formas.update', $boleto), ['nome' => 'Boleto', 'ativa' => '0'])->assertSessionHasNoErrors();

    $this->get(route('financeiro.lancamentos.index'))
        ->assertInertia(fn ($tela) => $tela->has('formas', 4));
});

test('não existe rota para apagar categoria ou forma de pagamento', function () {
    expect(Route::has('financeiro.categorias.destroy'))->toBeFalse();
    expect(Route::has('financeiro.formas.destroy'))->toBeFalse();
});
