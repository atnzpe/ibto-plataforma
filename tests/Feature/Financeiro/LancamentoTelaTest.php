<?php

/*
 * Testes das TELAS do livro-caixa (#59): permissões, filtros, totais, novo
 * lançamento (valor "90,00" → 9000 centavos) e conversão de dinheiro.
 * Dados INVENTADOS.
 */

use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\LivroCaixa;
use App\Support\Dinheiro;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Cria uma pessoa com o papel indicado. Ex.: pessoaCom('administrador')
 */
function pessoaCom(string $papel): User
{
    $usuario = User::factory()->create();
    $usuario->papeis()->attach(Papel::where('slug', $papel)->value('id'));

    return $usuario;
}

/**
 * Cria um lançamento pendente direto pelo LivroCaixa.
 */
function lancar(string $tipo, int $valor, string $vencimento): FinLancamento
{
    return app(LivroCaixa::class)->lancar([
        'tipo' => $tipo,
        'categoria_id' => FinCategoria::where('tipo', $tipo)->value('id'),
        'descricao' => "Teste {$tipo}",
        'valor_centavos' => $valor,
        'vencimento' => $vencimento,
    ]);
}

test('aluno não entra no financeiro (403)', function () {
    $this->actingAs(pessoaCom('aluno'))
        ->get(route('financeiro.lancamentos.index'))
        ->assertForbidden();
});

test('administrador vê a lista do mês com os totais', function () {
    $admin = pessoaCom('administrador');
    $this->actingAs($admin);

    lancar('receita', 9000, '2027-03-10');
    lancar('despesa', 4990, '2027-03-20');
    // De abril: não aparece em março.
    lancar('despesa', 100, '2027-04-05');

    $this->get(route('financeiro.lancamentos.index', ['mes' => '2027-03']))
        ->assertOk()
        ->assertInertia(fn (Assert $tela) => $tela
            ->component('financeiro/lancamentos/index')
            ->has('lancamentos', 2)
            ->where('totais.a_receber_centavos', 9000)
            ->where('totais.a_pagar_centavos', 4990)
            ->where('pode_lancar', true));
});

test('visão "a pagar" mostra só despesas', function () {
    $this->actingAs(pessoaCom('administrador'));
    lancar('receita', 9000, '2027-03-10');
    lancar('despesa', 4990, '2027-03-20');

    $this->get(route('financeiro.lancamentos.index', ['mes' => '2027-03', 'visao' => 'pagar']))
        ->assertInertia(fn (Assert $tela) => $tela
            ->has('lancamentos', 1)
            ->where('lancamentos.0.tipo', 'despesa'));
});

test('visão "vencidos" mostra atrasados de qualquer mês, marcados como vencido', function () {
    $this->actingAs(pessoaCom('administrador'));
    lancar('despesa', 100, now()->subMonths(2)->toDateString());
    // Pago, mesmo antigo: não é vencido.
    $pago = lancar('despesa', 200, now()->subMonths(2)->toDateString());
    app(LivroCaixa::class)->pagar($pago, now()->toDateString(), FinFormaPagamento::value('id'));

    $this->get(route('financeiro.lancamentos.index', ['visao' => 'vencidos']))
        ->assertInertia(fn (Assert $tela) => $tela
            ->has('lancamentos', 1)
            ->where('lancamentos.0.situacao', 'vencido'));
});

test('novo lançamento: "90,00" vira 9000 centavos e nasce pendente', function () {
    $this->actingAs(pessoaCom('administrador'));

    $this->post(route('financeiro.lancamentos.store'), [
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Taxas bancárias')->value('id'),
        'descricao' => 'Tarifa do banco',
        'valor' => '90,00',
        'vencimento' => '2027-03-31',
    ])->assertRedirect(route('financeiro.lancamentos.index', ['mes' => '2027-03']));

    $lancamento = FinLancamento::first();
    expect($lancamento->valor_centavos)->toBe(9000);
    expect($lancamento->situacao)->toBe('pendente');
});

test('novo lançamento com valor inválido volta com erro em português', function () {
    $this->actingAs(pessoaCom('administrador'));

    $this->post(route('financeiro.lancamentos.store'), [
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Taxas bancárias')->value('id'),
        'descricao' => 'Tarifa',
        'valor' => 'abc',
        'vencimento' => '2027-03-31',
    ])->assertSessionHasErrors('valor');

    expect(FinLancamento::count())->toBe(0);
});

test('categoria de receita numa despesa volta com o código CATEGORIA_INVALIDA', function () {
    $this->actingAs(pessoaCom('administrador'));

    $this->post(route('financeiro.lancamentos.store'), [
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('nome', 'Anuidade')->value('id'),
        'descricao' => 'Errado',
        'valor' => '10',
        'vencimento' => '2027-03-31',
    ])->assertSessionHasErrors('categoria_id');

    expect(session('errors')->first('categoria_id'))->toContain('CATEGORIA_INVALIDA');
});

test('Dinheiro::paraCentavos entende o jeito brasileiro', function () {
    expect(Dinheiro::paraCentavos('90'))->toBe(9000);
    expect(Dinheiro::paraCentavos('90,5'))->toBe(9050);
    expect(Dinheiro::paraCentavos('R$ 1.234,56'))->toBe(123456);
    expect(Dinheiro::paraCentavos('abc'))->toBeNull();
    expect(Dinheiro::paraCentavos('1,234'))->toBeNull();
});
