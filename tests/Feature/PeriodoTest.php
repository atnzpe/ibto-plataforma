<?php

/*
 * Testes do FILTRO DE PERÍODO (pedido do Gleyson, 10/10/2026): "de" e "até"
 * em todos os filtros de data. Padrão = mês atual; data inválida = padrão;
 * datas invertidas = trocadas; o período pode atravessar meses.
 * Dados INVENTADOS.
 */

use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\LivroCaixa;
use App\Support\Periodo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-10-15 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

/**
 * Monta um pedido com os filtros. Ex.: pedido(['de' => '2026-09-15'])
 *
 * @param  array<string, string>  $filtros
 */
function pedido(array $filtros): Request
{
    return Request::create('/financeiro', 'GET', $filtros);
}

test('sem datas: o mês atual inteiro', function () {
    expect(Periodo::doPedido(pedido([]))->paraTela())->toBe(['de' => '2026-10-01', 'ate' => '2026-10-31']);
});

test('datas escolhidas, atravessando meses', function () {
    expect(Periodo::doPedido(pedido(['de' => '2026-09-15', 'ate' => '2026-10-15']))->paraTela())
        ->toBe(['de' => '2026-09-15', 'ate' => '2026-10-15']);
});

test('data inválida (31/02 ou texto) volta para o padrão', function () {
    expect(Periodo::doPedido(pedido(['de' => '2026-02-31', 'ate' => 'abc']))->paraTela())
        ->toBe(['de' => '2026-10-01', 'ate' => '2026-10-31']);
});

test('datas invertidas são trocadas (sem erro)', function () {
    expect(Periodo::doPedido(pedido(['de' => '2026-10-20', 'ate' => '2026-10-05']))->paraTela())
        ->toBe(['de' => '2026-10-05', 'ate' => '2026-10-20']);
});

test('o fim do período conta o dia inteiro (até 23:59:59)', function () {
    expect(Periodo::doPedido(pedido(['ate' => '2026-10-20']))->fim->format('H:i:s'))->toBe('23:59:59');
});

test('livro-caixa: totais e lista pelo período escolhido', function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin);

    $caixa = app(LivroCaixa::class);
    $categoria = FinCategoria::where('nome', 'Taxas bancárias')->value('id');
    $lancar = fn (int $valor, string $vencimento) => $caixa->lancar([
        'tipo' => 'despesa', 'categoria_id' => $categoria, 'descricao' => 'Tarifa', 'valor_centavos' => $valor, 'vencimento' => $vencimento,
    ]);
    // Dentro do período (20/09 e 10/10) e fora dele (20/10).
    $lancar(100, '2026-09-20');
    $paga = $lancar(200, '2026-10-10');
    $lancar(400, '2026-10-20');
    $caixa->pagar($paga, '2026-10-10', FinFormaPagamento::value('id'));

    $this->get(route('financeiro.lancamentos.index', ['de' => '2026-09-15', 'ate' => '2026-10-15']))
        ->assertInertia(fn ($tela) => $tela
            ->where('filtros.de', '2026-09-15')
            ->where('filtros.ate', '2026-10-15')
            ->has('lancamentos', 2)
            ->where('cartoes.a_pagar', 100)
            ->where('cartoes.saldo', -200));
});
