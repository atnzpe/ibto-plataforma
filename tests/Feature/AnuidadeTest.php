<?php

/*
 * Testes da regra da ANUIDADE (#37, decisões do Gleyson em 09/10/2026):
 * ano civil, 2026 isento, 2027 com R$ 90,00, em dia = pagou o ano corrente.
 * Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Anuidade;
use App\Models\AnuidadePagamento;
use App\Models\Filiado;
use Illuminate\Database\QueryException;
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
 * Registra o pagamento da anuidade de um ano, como a baixa manual faria.
 *
 * @param  Filiado  $filiado  Quem pagou.
 * @param  int  $ano  De qual ano. Ex.: 2027
 */
function pagarAnuidade(Filiado $filiado, int $ano): void
{
    AnuidadePagamento::create([
        'filiado_id' => $filiado->id,
        'ano' => $ano,
        'valor_centavos' => 9000,
        'pago_em' => "{$ano}-03-15",
        'forma' => 'pix',
    ]);
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

test('o banco recusa dois pagamentos do mesmo filiado no mesmo ano', function () {
    $filiado = filiadoDaAnuidade();
    pagarAnuidade($filiado, 2027);

    expect(fn () => pagarAnuidade($filiado, 2027))->toThrow(QueryException::class);
});
