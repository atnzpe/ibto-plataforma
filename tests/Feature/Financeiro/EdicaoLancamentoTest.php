<?php

/*
 * Testes da EDIÇÃO de lançamento (#59, pedido do Gleyson em 09/10/2026) e do
 * valor digitado com PONTO decimal ("12.50", comum no celular). Só pendente
 * se edita; a auditoria guarda o antes e o depois. Dados INVENTADOS.
 */

use App\Models\Auditoria;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\LivroCaixa;
use App\Support\Dinheiro;

beforeEach(function () {
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin);

    $this->categoria = FinCategoria::where('nome', 'Taxas bancárias')->value('id');
    // O caso real do teste humano: digitou errado e saiu R$ 1.250,00.
    $this->lancamento = app(LivroCaixa::class)->lancar([
        'tipo' => 'despesa',
        'categoria_id' => $this->categoria,
        'descricao' => 'Tarifa de teste',
        'valor_centavos' => 125000,
        'vencimento' => '2026-10-09',
    ]);
});

/**
 * Os campos do formulário de edição, com um valor novo.
 *
 * @param  string  $valor  Ex.: '12,50'
 * @return array<string, mixed>
 */
function camposEditados(int $categoria, string $valor): array
{
    return [
        'tipo' => 'despesa',
        'categoria_id' => $categoria,
        'descricao' => 'Tarifa de teste (corrigida)',
        'valor' => $valor,
        'vencimento' => '2026-10-10',
    ];
}

test('a edição abre com o valor no formato do campo', function () {
    $this->get(route('financeiro.lancamentos.edit', $this->lancamento))
        ->assertInertia(fn ($tela) => $tela
            ->component('financeiro/lancamentos/formulario')
            ->where('lancamento.valor', '1250,00'));
});

test('editar um pendente corrige o valor e registra antes e depois', function () {
    $this->put(route('financeiro.lancamentos.update', $this->lancamento), camposEditados($this->categoria, '12,50'))
        ->assertRedirect(route('financeiro.lancamentos.index', ['mes' => '2026-10']));

    $atual = $this->lancamento->fresh();
    expect($atual->valor_centavos)->toBe(1250);
    expect($atual->vencimento->toDateString())->toBe('2026-10-10');

    $auditoria = Auditoria::where('acao', 'LANCAMENTO_EDITADO')->first();
    expect($auditoria->detalhes['antes']['valor_centavos'])->toBe(125000);
    expect($auditoria->detalhes['depois']['valor_centavos'])->toBe(1250);
});

test('pago não se edita: a tela volta com LANCAMENTO_NAO_PENDENTE', function () {
    app(LivroCaixa::class)->pagar($this->lancamento, '2026-10-09', FinFormaPagamento::value('id'));

    $this->get(route('financeiro.lancamentos.edit', $this->lancamento))
        ->assertRedirect(route('financeiro.lancamentos.index'));
    // Seguindo o redirecionamento, a lista recebe o aviso com o código.
    $this->followingRedirects()->get(route('financeiro.lancamentos.edit', $this->lancamento))
        ->assertInertia(fn ($tela) => $tela->where('errors.acao', fn (string $aviso) => str_contains($aviso, 'LANCAMENTO_NAO_PENDENTE')));

    $this->put(route('financeiro.lancamentos.update', $this->lancamento), camposEditados($this->categoria, '1,00'))
        ->assertSessionHasErrors('categoria_id');
    expect($this->lancamento->fresh()->valor_centavos)->toBe(125000);
});

test('valor com ponto decimal (12.50) vira 12,50; com 3 números (1.250) continua milhar', function () {
    expect(Dinheiro::paraCentavos('12.50'))->toBe(1250);
    expect(Dinheiro::paraCentavos('12.5'))->toBe(1250);
    expect(Dinheiro::paraCentavos('1.250'))->toBe(125000);
    expect(Dinheiro::paraCentavos('1.250,00'))->toBe(125000);
    expect(Dinheiro::paraCampo(1250))->toBe('12,50');
});
