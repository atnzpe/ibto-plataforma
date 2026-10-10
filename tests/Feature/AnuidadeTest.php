<?php

/*
 * Testes da regra da ANUIDADE (#37, decisões do Gleyson em 09/10/2026):
 * ano civil, 2026 isento, 2027 com R$ 90,00, em dia = pagou o ano corrente.
 * Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Exceptions\RegraFinanceira;
use App\Models\Anuidade;
use App\Models\AnuidadePagamento;
use App\Models\Auditoria;
use App\Models\Filiado;
use App\Models\FinFormaPagamento;
use App\Services\Anuidades;
use App\Services\LivroCaixa;
use Illuminate\Support\Carbon;

/**
 * Cria um filiado inventado para os testes da anuidade.
 *
 * @return Filiado O filiado criado (com a conta dele).
 */
function filiadoDaAnuidade(): Filiado
{
    return app(CriarFiliado::class)->executar([
        'nome' => 'Pessoa Anuidade',
        'email' => 'anuidade@ibto.test',
        'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20',
        'municipio' => 'Recife',
        'uf' => 'PE',
    ]);
}

/**
 * Registra o pagamento da anuidade de um ano pelo serviço (pagamento + receita
 * no livro-caixa), como a baixa manual faz.
 *
 * @param  Filiado  $filiado  Quem pagou.
 * @param  int  $ano  De qual ano. Ex.: 2027
 * @return AnuidadePagamento O pagamento criado.
 */
function pagarAnuidade(Filiado $filiado, int $ano): AnuidadePagamento
{
    return app(Anuidades::class)->registrarPagamento($filiado, $ano, "{$ano}-03-15", FinFormaPagamento::where('nome', 'PIX')->value('id'));
}

test('a migration já traz 2026 isento e 2027 com R$ 90,00', function () {
    expect(Anuidade::where('ano', 2026)->first()->isenta)->toBeTrue();
    expect(Anuidade::where('ano', 2027)->first()->valor_centavos)->toBe(9000);
});

test('2026 é isento: todo filiado está em dia sem pagar', function () {
    expect(filiadoDaAnuidade()->anuidadeEmDia(Carbon::parse('2026-11-30')))->toBeTrue();
});

test('2027 sem pagamento: vencida', function () {
    expect(filiadoDaAnuidade()->anuidadeEmDia(Carbon::parse('2027-01-10')))->toBeFalse();
});

test('2027 pago: em dia o ano todo, até 31/12', function () {
    $filiado = filiadoDaAnuidade();
    pagarAnuidade($filiado, 2027);

    expect($filiado->anuidadeEmDia(Carbon::parse('2027-12-31')))->toBeTrue();
});

test('ano civil: o pagamento de 2027 não vale para 2028', function () {
    $filiado = filiadoDaAnuidade();
    pagarAnuidade($filiado, 2027);
    // Valor de 2028 cadastrado (não isento), para a regra ter o que cobrar.
    Anuidade::create(['ano' => 2028, 'valor_centavos' => 9500, 'isenta' => false]);

    expect($filiado->anuidadeEmDia(Carbon::parse('2028-01-01')))->toBeFalse();
});

test('ano sem valor cadastrado: ninguém deve nada', function () {
    expect(filiadoDaAnuidade()->anuidadeEmDia(Carbon::parse('2030-06-01')))->toBeTrue();
});

test('pagar duas vezes o mesmo ano é recusado (ANUIDADE_JA_PAGA)', function () {
    $filiado = filiadoDaAnuidade();
    pagarAnuidade($filiado, 2027);

    expect(fn () => pagarAnuidade($filiado, 2027))->toThrow(RegraFinanceira::class, 'já está paga');
});

test('o pagamento gera a receita no livro-caixa, já paga, ligada a ele (#59)', function () {
    $filiado = filiadoDaAnuidade();
    $pagamento = pagarAnuidade($filiado, 2027);

    $receita = $pagamento->lancamento;
    expect($receita->tipo)->toBe('receita');
    expect($receita->situacao)->toBe('pago');
    expect($receita->valor_centavos)->toBe(9000);
    expect($receita->categoria->nome)->toBe('Anuidade');
    expect($receita->origem)->toBe('anuidade');
    expect($receita->origem_id)->toBe($pagamento->id);
    // LGPD: a descrição leva o registro, não o nome da pessoa.
    expect($receita->descricao)->toContain($filiado->registro);
    expect($receita->descricao)->not->toContain('Pessoa Anuidade');
    expect(Auditoria::where('acao', 'ANUIDADE_PAGA')->exists())->toBeTrue();
});

test('estornar a receita desfaz o "em dia", e a pessoa pode pagar de novo', function () {
    $filiado = filiadoDaAnuidade();
    $pagamento = pagarAnuidade($filiado, 2027);

    app(LivroCaixa::class)->estornar($pagamento->lancamento, 'Pagamento devolvido', '2027-03-20');
    expect($filiado->anuidadeEmDia(Carbon::parse('2027-06-01')))->toBeFalse();

    pagarAnuidade($filiado, 2027);
    expect($filiado->anuidadeEmDia(Carbon::parse('2027-06-01')))->toBeTrue();
});

test('ano isento ou sem valor não aceita pagamento', function () {
    $filiado = filiadoDaAnuidade();

    expect(fn () => pagarAnuidade($filiado, 2026))->toThrow(RegraFinanceira::class, 'isenta');
    expect(fn () => pagarAnuidade($filiado, 2031))->toThrow(RegraFinanceira::class, 'Não há valor');
});
