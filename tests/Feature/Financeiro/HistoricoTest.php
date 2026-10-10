<?php

/*
 * Testes do HISTÓRICO do financeiro (#59, pedido do Gleyson em 09/10/2026):
 * toda movimentação aparece, com quem fez; o filtro por lançamento mostra a
 * "vida inteira" dele; e não existe rota para apagar o histórico.
 * Dados INVENTADOS.
 */

use App\Models\Auditoria;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\LivroCaixa;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->admin = User::factory()->create(['name' => 'Admin Teste']);
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($this->admin);

    $caixa = app(LivroCaixa::class);
    // Vida de um lançamento: criado → pago → estornado.
    $this->lancamento = $caixa->lancar([
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('tipo', 'despesa')->value('id'),
        'descricao' => 'Tarifa',
        'valor_centavos' => 1250,
        'vencimento' => today()->toDateString(),
    ]);
    $caixa->pagar($this->lancamento, today()->toDateString(), FinFormaPagamento::value('id'));
    $caixa->estornar($this->lancamento, 'Valor errado', today()->toDateString());
    // Outro lançamento qualquer, que NÃO deve aparecer no filtro do primeiro.
    $caixa->lancar([
        'tipo' => 'despesa',
        'categoria_id' => FinCategoria::where('tipo', 'despesa')->value('id'),
        'descricao' => 'Outro',
        'valor_centavos' => 500,
        'vencimento' => today()->toDateString(),
    ]);
});

test('o histórico mostra toda movimentação, com quem fez, do mais novo ao mais antigo', function () {
    $this->get(route('financeiro.historico'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela
            ->component('financeiro/historico')
            ->where('paginacao.total', 4)
            ->where('linhas.0.acao', 'LANCAMENTO_CRIADO')
            ->where('linhas.3.acao', 'LANCAMENTO_CRIADO')
            ->where('linhas.0.quem', 'Admin Teste'));
});

test('filtro por lançamento: a vida inteira dele (criado, pago, estornado)', function () {
    $this->get(route('financeiro.historico', ['lancamento' => $this->lancamento->id]))
        ->assertInertia(fn ($tela) => $tela
            ->where('paginacao.total', 3)
            ->where('linhas.0.acao', 'LANCAMENTO_ESTORNADO')
            ->where('linhas.0.estorno_de', $this->lancamento->id)
            ->where('linhas.2.acao', 'LANCAMENTO_CRIADO'));
});

test('ações que não são do financeiro ficam de fora', function () {
    Auditoria::registrar('PAPEL_CONCEDIDO', ['papel' => 'aluno']);

    $this->get(route('financeiro.historico'))
        ->assertInertia(fn ($tela) => $tela->where('paginacao.total', 4));
});

test('aluno não vê o histórico (403)', function () {
    $aluno = User::factory()->create();
    $aluno->papeis()->attach(Papel::where('slug', 'aluno')->value('id'));

    $this->actingAs($aluno)->get(route('financeiro.historico'))->assertForbidden();
});

test('não existe rota para apagar ou alterar o histórico', function () {
    // Procura qualquer rota de escrita (DELETE, PUT, PATCH, POST) com "historico" ou "auditoria".
    $escrita = collect(Route::getRoutes()->getRoutes())->filter(fn ($rota) => (str_contains($rota->uri(), 'historico') || str_contains($rota->uri(), 'auditoria'))
        && array_intersect($rota->methods(), ['DELETE', 'PUT', 'PATCH', 'POST']) !== []);

    expect($escrita)->toBeEmpty();
});
