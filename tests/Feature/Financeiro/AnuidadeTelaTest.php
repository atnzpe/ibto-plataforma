<?php

/*
 * Testes do QUADRO DA ANUIDADE na ficha do filiado (#37 + #59, bloco 3b):
 * quem vê, quem registra, o pagamento gera a receita e os erros de regra
 * voltam com código. Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Filiado;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Datas fixas: o "ano de hoje" do quadro é 2027 (tem valor e não é isento).
    Carbon::setTestNow('2027-03-20 10:00:00');

    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($this->admin);

    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Pessoa Quadro',
        'email' => 'quadro@ibto.test',
        'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20',
        'municipio' => 'Recife',
        'uf' => 'PE',
    ]);
    $this->pix = FinFormaPagamento::where('nome', 'PIX')->value('id');
});

afterEach(fn () => Carbon::setTestNow());

test('a ficha mostra o quadro: 2027 vencida e sem pagamentos', function () {
    $this->get(route('cadastros.filiados.edit', $this->filiado))
        ->assertInertia(fn ($tela) => $tela
            ->where('anuidade.ano', 2027)
            ->where('anuidade.situacao', 'vencida')
            ->where('anuidade.valor_centavos', 9000)
            ->has('anuidade.pagamentos', 0)
            ->where('anuidade.pode_registrar', true));
});

test('registrar pela ficha: vira "em dia" e a receita aparece no financeiro', function () {
    $this->from(route('cadastros.filiados.edit', $this->filiado))
        ->post(route('cadastros.filiados.anuidade', $this->filiado), [
            'ano' => 2027,
            'pago_em' => '2027-03-15',
            'forma_pagamento_id' => $this->pix,
        ])
        ->assertRedirect(route('cadastros.filiados.edit', $this->filiado))
        ->assertSessionHas('sucesso', 'Anuidade 2027 registrada e lançada no financeiro.');

    expect(Filiado::find($this->filiado->id)->anuidadeEmDia())->toBeTrue();
    expect(FinLancamento::where('origem', 'anuidade')->where('situacao', 'pago')->count())->toBe(1);

    $this->get(route('cadastros.filiados.edit', $this->filiado))
        ->assertInertia(fn ($tela) => $tela
            ->where('anuidade.situacao', 'em_dia')
            ->where('anuidade.pagamentos.0.forma', 'PIX'));
});

test('registrar duas vezes volta com ANUIDADE_JA_PAGA, sem erro 500', function () {
    $campos = ['ano' => 2027, 'pago_em' => '2027-03-15', 'forma_pagamento_id' => $this->pix];
    $this->post(route('cadastros.filiados.anuidade', $this->filiado), $campos);

    $this->post(route('cadastros.filiados.anuidade', $this->filiado), $campos)
        ->assertSessionHasErrors('anuidade');
    expect(session('errors')->first('anuidade'))->toContain('ANUIDADE_JA_PAGA');
});

test('ano isento (2026) é recusado pelo formulário', function () {
    $this->post(route('cadastros.filiados.anuidade', $this->filiado), [
        'ano' => 2026, 'pago_em' => '2027-03-15', 'forma_pagamento_id' => $this->pix,
    ])->assertSessionHasErrors('ano');
});

test('quem não vê o financeiro não vê o quadro, nem registra (403)', function () {
    // Administrador de equipe: cuida de filiados, mas não tem financeiro.
    $semFinanceiro = User::factory()->create();
    $semFinanceiro->papeis()->attach(Papel::where('slug', 'tecnologia')->value('id'));
    // Tira o financeiro da Tecnologia só neste teste.
    Papel::where('slug', 'tecnologia')->first()->permissoes()->detach(
        Permissao::whereIn('slug', ['financeiro.ver', 'financeiro.lancar'])->pluck('id'),
    );

    $this->actingAs($semFinanceiro)
        ->get(route('cadastros.filiados.edit', $this->filiado))
        ->assertInertia(fn ($tela) => $tela->where('anuidade', null));

    $this->actingAs($semFinanceiro)
        ->post(route('cadastros.filiados.anuidade', $this->filiado), [
            'ano' => 2027, 'pago_em' => '2027-03-15', 'forma_pagamento_id' => $this->pix,
        ])->assertForbidden();
});
