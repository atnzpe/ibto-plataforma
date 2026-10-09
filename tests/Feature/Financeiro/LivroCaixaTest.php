<?php

/*
 * Testes das REGRAS do livro-caixa (#59): lançar, pagar, cancelar, estornar,
 * pago imutável, saldo do mês, vencidos e auditoria. Dados INVENTADOS.
 */

use App\Exceptions\RegraFinanceira;
use App\Models\Auditoria;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\User;
use App\Services\LivroCaixa;
use Illuminate\Support\Carbon;

/**
 * Atalho: o id de uma categoria inicial pelo nome. Ex.: categoria('Anuidade')
 */
function categoria(string $nome): int
{
    return FinCategoria::where('nome', $nome)->value('id');
}

/**
 * Atalho: o id da forma de pagamento 'PIX'.
 */
function pix(): int
{
    return FinFormaPagamento::where('nome', 'PIX')->value('id');
}

/**
 * Cria um lançamento pendente de teste.
 *
 * @param  string  $tipo  'receita' ou 'despesa'
 * @param  int  $valor  Em centavos. Ex.: 9000
 * @param  string  $vencimento  Ex.: '2027-03-31'
 */
function lancamentoDeTeste(string $tipo = 'receita', int $valor = 9000, string $vencimento = '2027-03-31'): FinLancamento
{
    return app(LivroCaixa::class)->lancar([
        'tipo' => $tipo,
        'categoria_id' => categoria($tipo === 'receita' ? 'Anuidade' : 'Taxas bancárias'),
        'descricao' => 'Lançamento de teste',
        'valor_centavos' => $valor,
        'vencimento' => $vencimento,
    ]);
}

beforeEach(function () {
    // Alguém logado: a auditoria anota quem fez.
    $this->actingAs(User::factory()->create());
    $this->caixa = app(LivroCaixa::class);
});

test('a migration já traz as 9 categorias e as 4 formas de pagamento', function () {
    expect(FinCategoria::where('tipo', 'receita')->count())->toBe(4);
    expect(FinCategoria::where('tipo', 'despesa')->count())->toBe(5);
    expect(FinFormaPagamento::pluck('nome')->all())->toEqualCanonicalizing(['PIX', 'Dinheiro', 'Transferência', 'Cartão']);
});

test('lançar cria pendente e registra na auditoria', function () {
    $lancamento = lancamentoDeTeste();

    expect($lancamento->situacao)->toBe('pendente');
    expect(Auditoria::where('acao', 'LANCAMENTO_CRIADO')->exists())->toBeTrue();
});

test('valor zero é recusado', function () {
    expect(fn () => lancamentoDeTeste(valor: 0))->toThrow(RegraFinanceira::class, 'maior que zero');
});

test('categoria de receita numa despesa é recusada', function () {
    expect(fn () => $this->caixa->lancar([
        'tipo' => 'despesa',
        'categoria_id' => categoria('Anuidade'),
        'descricao' => 'Errado',
        'valor_centavos' => 100,
        'vencimento' => '2027-01-10',
    ]))->toThrow(RegraFinanceira::class, 'mesmo tipo');
});

test('pagar: pendente vira pago, com data e forma', function () {
    $lancamento = $this->caixa->pagar(lancamentoDeTeste(), '2027-03-15', pix());

    expect($lancamento->fresh()->situacao)->toBe('pago');
    expect($lancamento->fresh()->pago_em->toDateString())->toBe('2027-03-15');
});

test('pago é imutável: não altera nem apaga', function () {
    $lancamento = $this->caixa->pagar(lancamentoDeTeste(), '2027-03-15', pix());

    // Mesmo "por fora" do LivroCaixa, o modelo recusa.
    expect(fn () => $lancamento->forceFill(['valor_centavos' => 1])->save())->toThrow(RegraFinanceira::class);
    expect(fn () => $lancamento->delete())->toThrow(RegraFinanceira::class);
    expect($lancamento->fresh()->valor_centavos)->toBe(9000);
});

test('pagar duas vezes é recusado', function () {
    $lancamento = $this->caixa->pagar(lancamentoDeTeste(), '2027-03-15', pix());

    expect(fn () => $this->caixa->pagar($lancamento, '2027-03-16', pix()))->toThrow(RegraFinanceira::class);
});

test('cancelar: só pendente, e com motivo', function () {
    $pendente = lancamentoDeTeste();
    expect(fn () => $this->caixa->cancelar($pendente, ''))->toThrow(RegraFinanceira::class, 'motivo');

    $this->caixa->cancelar($pendente, 'Lançado em duplicidade');
    expect($pendente->fresh()->situacao)->toBe('cancelado');

    $pago = $this->caixa->pagar(lancamentoDeTeste(), '2027-03-15', pix());
    expect(fn () => $this->caixa->cancelar($pago, 'Tentando cancelar pago'))->toThrow(RegraFinanceira::class, 'estorno');
});

test('estornar: cria o estorno ligado, uma vez só, e o original não muda', function () {
    $pago = $this->caixa->pagar(lancamentoDeTeste(), '2027-03-15', pix());

    $estorno = $this->caixa->estornar($pago, 'Valor lançado errado', '2027-03-20');

    expect($estorno->estorno_de_id)->toBe($pago->id);
    expect($estorno->situacao)->toBe('pago');
    expect($pago->fresh()->situacao)->toBe('pago');
    // Segundo estorno do mesmo original: recusado.
    expect(fn () => $this->caixa->estornar($pago, 'De novo, por engano', '2027-03-21'))->toThrow(RegraFinanceira::class, 'já foi estornado');
    // Estorno do estorno: recusado.
    expect(fn () => $this->caixa->estornar($estorno, 'Estorno do estorno', '2027-03-21'))->toThrow(RegraFinanceira::class);
});

test('pendente não se estorna (cancela-se)', function () {
    expect(fn () => $this->caixa->estornar(lancamentoDeTeste(), 'Não pode', '2027-03-20'))->toThrow(RegraFinanceira::class, 'cancele');
});

test('saldo do mês: receitas menos despesas, estorno desconta, conta pelo dia do pagamento', function () {
    // Março: receita de R$ 90,00 e despesa de R$ 49,90, pagas em março.
    $this->caixa->pagar(lancamentoDeTeste('receita', 9000), '2027-03-10', pix());
    $this->caixa->pagar(lancamentoDeTeste('despesa', 4990), '2027-03-12', pix());
    // Outra receita de R$ 90,00 paga em março e estornada em março.
    $estornada = $this->caixa->pagar(lancamentoDeTeste('receita', 9000), '2027-03-15', pix());
    $this->caixa->estornar($estornada, 'Pagamento devolvido', '2027-03-20');
    // Pendente e paga em abril: não entram em março.
    lancamentoDeTeste('receita', 100000);
    $this->caixa->pagar(lancamentoDeTeste('receita', 5000), '2027-04-01', pix());

    // 9000 − 4990 + 9000 − 9000 = 4010 (R$ 40,10).
    expect($this->caixa->saldoDoMes(2027, 3))->toBe(4010);
});

test('vencidos: só pendentes com vencimento antes do dia', function () {
    $vencido = lancamentoDeTeste(vencimento: '2027-03-31');
    // Vence no próprio dia: ainda não venceu.
    lancamentoDeTeste(vencimento: '2027-04-01');
    // Pago, mesmo com vencimento antigo: não está vencido.
    $this->caixa->pagar(lancamentoDeTeste(vencimento: '2027-03-01'), '2027-03-01', pix());
    // Cancelado: não está vencido.
    $this->caixa->cancelar(lancamentoDeTeste(vencimento: '2027-03-01'), 'Lançado errado');

    $ids = FinLancamento::query()->vencidos(Carbon::parse('2027-04-01'))->pluck('id')->all();

    expect($ids)->toBe([$vencido->id]);
});

test('auditoria não guarda a descrição (LGPD: pode ter nome de pessoa)', function () {
    lancamentoDeTeste();

    $detalhes = Auditoria::where('acao', 'LANCAMENTO_CRIADO')->first()->detalhes;
    expect(json_encode($detalhes))->not->toContain('Lançamento de teste');
});
