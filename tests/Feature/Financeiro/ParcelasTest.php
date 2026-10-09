<?php

/*
 * Testes das PARCELAS (#59, pedido do Gleyson em 09/10/2026): um título em
 * N vezes vira N contas, uma por mês, que somam exatamente o total.
 * Dados INVENTADOS.
 */

use App\Exceptions\RegraFinanceira;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\LivroCaixa;
use App\Support\Dinheiro;

beforeEach(function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin);
    $this->categoria = FinCategoria::where('nome', 'Material de curso')->value('id');
});

test('dividir: as parcelas somam exatamente o total, a sobra vai para a 1ª', function () {
    expect(Dinheiro::dividirEmParcelas(90000, 3))->toBe([30000, 30000, 30000]);
    expect(Dinheiro::dividirEmParcelas(1000, 3))->toBe([334, 333, 333]);
    expect(array_sum(Dinheiro::dividirEmParcelas(100001, 7)))->toBe(100001);
});

test('R$ 900,00 em 3x pela tela: 3 contas, uma por mês, ligadas pelo grupo', function () {
    $this->post(route('financeiro.lancamentos.store'), [
        'tipo' => 'despesa',
        'categoria_id' => $this->categoria,
        'descricao' => 'Material',
        'valor' => '900,00',
        'vencimento' => '2026-11-10',
        'parcelas' => 3,
    ])->assertSessionHas('sucesso', 'Lançamento cadastrado em 3 parcelas.');

    $parcelas = FinLancamento::orderBy('parcela_numero')->get();
    expect($parcelas)->toHaveCount(3);
    expect($parcelas->pluck('descricao')->all())->toBe(['Material (1/3)', 'Material (2/3)', 'Material (3/3)']);
    expect($parcelas->map(fn ($p) => $p->vencimento->toDateString())->all())->toBe(['2026-11-10', '2026-12-10', '2027-01-10']);
    expect($parcelas->pluck('valor_centavos')->all())->toBe([30000, 30000, 30000]);
    expect($parcelas->pluck('grupo_parcelas')->unique())->toHaveCount(1);
    expect($parcelas->pluck('situacao')->unique()->all())->toBe(['pendente']);
});

test('vencimento no dia 31: a parcela de fevereiro cai no último dia do mês', function () {
    $criadas = app(LivroCaixa::class)->lancarParcelado([
        'tipo' => 'despesa',
        'categoria_id' => $this->categoria,
        'descricao' => 'Material',
        'valor_centavos' => 3000,
        'vencimento' => '2027-01-31',
    ], 2);

    expect($criadas[1]->vencimento->toDateString())->toBe('2027-02-28');
});

test('cada parcela tem a sua baixa: pagar a 1ª não mexe nas outras', function () {
    $criadas = app(LivroCaixa::class)->lancarParcelado([
        'tipo' => 'despesa',
        'categoria_id' => $this->categoria,
        'descricao' => 'Material',
        'valor_centavos' => 90000,
        'vencimento' => '2026-10-09',
    ], 3);

    app(LivroCaixa::class)->pagar($criadas[0], '2026-10-09', FinFormaPagamento::value('id'));

    expect(FinLancamento::where('situacao', 'pago')->count())->toBe(1);
    expect(FinLancamento::where('situacao', 'pendente')->count())->toBe(2);
});

test('parcelas fora de 2 a 24 são recusadas', function () {
    expect(fn () => app(LivroCaixa::class)->lancarParcelado([
        'tipo' => 'despesa',
        'categoria_id' => $this->categoria,
        'descricao' => 'X',
        'valor_centavos' => 1000,
        'vencimento' => '2026-10-09',
    ], 25))->toThrow(RegraFinanceira::class);
});
