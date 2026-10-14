<?php

/*
 * Testes do painel "COMANDO GERAL" (#59 bloco 4 / #45): quem entra, os
 * totais, o fluxo de 6 meses, a anuidade (% e régua sem nome) e os filiados.
 * Datas fixas para os números serem previsíveis. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Actions\Filiados\CriarFiliado;
use App\Models\Curso;
use App\Models\Filiado;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\Papel;
use App\Models\User;
use App\Services\Anuidades;
use App\Services\ComandoGeral;
use App\Services\LivroCaixa;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // "Hoje" = 15/10/2027 (2027 tem anuidade de R$ 90,00).
    Carbon::setTestNow('2027-10-15 10:00:00');

    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($this->admin);

    $this->caixa = app(LivroCaixa::class);
    $this->pix = FinFormaPagamento::where('nome', 'PIX')->value('id');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Cria um filiado inventado. Ex.: novoFiliado(1)
 */
function novoFiliado(int $numero, string $cpf): Filiado
{
    return app(CriarFiliado::class)->executar([
        'nome' => "Pessoa {$numero}",
        'email' => "pessoa{$numero}@ibto.test",
        'cpf' => $cpf,
        'nascimento' => '1990-05-20',
        'municipio' => 'Recife',
        'uf' => 'PE',
    ]);
}

test('aluno não entra no Comando Geral (403); administrador entra', function () {
    $aluno = User::factory()->create();
    $aluno->papeis()->attach(Papel::where('slug', 'aluno')->value('id'));
    $this->actingAs($aluno)->get(route('comando-geral'))->assertForbidden();

    $this->actingAs($this->admin)->get(route('comando-geral'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('comando-geral')->has('fluxo', 6));
});

test('totais e fluxo de 6 meses, com estorno descontado', function () {
    $receita = FinCategoria::where('nome', 'Doação')->value('id');
    $despesa = FinCategoria::where('nome', 'Taxas bancárias')->value('id');
    $lancar = fn (string $tipo, int $categoria, int $valor, string $venc) => $this->caixa->lancar([
        'tipo' => $tipo, 'categoria_id' => $categoria, 'descricao' => 'Teste', 'valor_centavos' => $valor, 'vencimento' => $venc,
    ]);

    // Outubro: receita de 100,00 paga; despesa de 30,00 paga; receita de 50,00 paga e estornada.
    $this->caixa->pagar($lancar('receita', $receita, 10000, '2027-10-01'), '2027-10-02', $this->pix);
    $this->caixa->pagar($lancar('despesa', $despesa, 3000, '2027-10-01'), '2027-10-03', $this->pix);
    $estornada = $this->caixa->pagar($lancar('receita', $receita, 5000, '2027-10-01'), '2027-10-04', $this->pix);
    $this->caixa->estornar($estornada, 'Devolvido', '2027-10-05');
    // Junho: receita de 20,00 paga.
    $this->caixa->pagar($lancar('receita', $receita, 2000, '2027-06-01'), '2027-06-10', $this->pix);
    // Pendentes: a pagar em outubro (12,50, vence dia 20) e uma vencida (dia 01).
    $lancar('despesa', $despesa, 1250, '2027-10-20');
    $lancar('despesa', $despesa, 700, '2027-10-01');

    $painel = app(ComandoGeral::class)->painel();

    // Saldo de outubro: 100 − 30 + 50 − 50 = 70,00.
    expect($painel['totais']['saldo_centavos'])->toBe(7000);
    expect($painel['totais']['a_pagar_centavos'])->toBe(1950);
    expect($painel['totais']['vencidos'])->toBe(1);

    // Fluxo: 6 meses, de maio a outubro; o estorno desconta nas entradas de outubro.
    expect(array_column($painel['fluxo'], 'mes'))->toBe(['2027-05', '2027-06', '2027-07', '2027-08', '2027-09', '2027-10']);
    expect($painel['fluxo'][5])->toBe(['mes' => '2027-10', 'entradas_centavos' => 10000, 'saidas_centavos' => 3000]);
    expect($painel['fluxo'][1]['entradas_centavos'])->toBe(2000);

    // Próximos vencimentos: só de hoje em diante (o vencido fica de fora).
    expect($painel['vencimentos'])->toHaveCount(1);
    expect($painel['vencimentos'][0]['valor_centavos'])->toBe(1250);
});

test('anuidade: % em dia e régua só com o registro (sem nome)', function () {
    $pagou = novoFiliado(1, '529.982.247-25');
    $devendo = novoFiliado(2, '111.444.777-35');
    novoFiliado(3, '123.456.789-09');

    app(Anuidades::class)->registrarPagamento($pagou, 2027, '2027-03-15', $this->pix);

    $anuidade = app(ComandoGeral::class)->painel()['anuidade'];

    expect($anuidade['total'])->toBe(3);
    expect($anuidade['em_dia'])->toBe(1);
    // 1 de 3 = 33% (número inteiro).
    expect($anuidade['percentual'])->toBe(33);
    expect($anuidade['recebido_centavos'])->toBe(9000);
    expect($anuidade['em_aberto'])->toBe(2);
    expect(array_column($anuidade['regua'], 'registro'))->toContain($devendo->registro);
    // LGPD: nenhum nome na régua.
    expect(json_encode($anuidade['regua']))->not->toContain('Pessoa');
});

test('sem filiados, a porcentagem é 0 (sem dividir por zero)', function () {
    expect(app(ComandoGeral::class)->painel()['anuidade']['percentual'])->toBe(0);
});

test('filiados: ativos, convites pendentes e todas as categorias (mesmo com zero)', function () {
    novoFiliado(1, '529.982.247-25');

    $filiados = app(ComandoGeral::class)->painel()['filiados'];

    expect($filiados['ativos'])->toBe(1);
    // Recém-criado: ainda não usou o convite.
    expect($filiados['convites_pendentes'])->toBe(1);
    expect($filiados['por_categoria'])->toHaveCount(5);
});

test('cursos: turmas abertas, vagas livres, aguardando pagamento e confirmadas no mês', function () {
    $hoje = Carbon::parse('2026-10-15');
    Carbon::setTestNow($hoje);

    // Aberto (sem data), 3 vagas, com 1 aguardando e 1 confirmada; outro já começou (não conta).
    $aberto = Curso::create(['titulo' => 'Aberto', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000, 'vagas' => 3]);
    $aberto->forceFill(['situacao' => 'publicado'])->save();
    $passado = Curso::create(['titulo' => 'Passado', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'vagas' => 5, 'data_inicio' => '2026-10-01', 'local' => 'Recife']);
    $passado->forceFill(['situacao' => 'publicado'])->save();

    $matriculas = app(Matriculas::class);
    $matriculas->matricular($aberto, User::factory()->create());
    $pago = $matriculas->matricular($aberto, User::factory()->create());
    $matriculas->confirmarPagamento($pago, '2026-10-15', (int) FinFormaPagamento::where('nome', 'PIX')->value('id'));

    expect(app(ComandoGeral::class)->painel($hoje)['cursos'])->toBe([
        'turmas_abertas' => 1,
        'vagas_livres' => 1,
        'aguardando_pagamento' => 1,
        'confirmadas_no_mes' => 1,
    ]);

    Carbon::setTestNow();
});
