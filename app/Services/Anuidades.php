<?php

/*
 * ANUIDADES (#37 + #59, bloco 3): registrar o pagamento da anuidade de um
 * filiado. Num clique explícito, e numa operação só (transação), grava:
 * 1) a RECEITA no livro-caixa (categoria "Anuidade"), já paga;
 * 2) o PAGAMENTO da anuidade, ligado a essa receita.
 * Ou grava as duas coisas, ou nenhuma. Estornar a receita (no financeiro)
 * desfaz o "em dia" do filiado, e ele pode pagar de novo.
 */

namespace App\Services;

use App\Exceptions\RegraFinanceira;
use App\Models\Anuidade;
use App\Models\AnuidadePagamento;
use App\Models\Auditoria;
use App\Models\Filiado;
use App\Models\FinCategoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Anuidades
{
    /**
     * @param  LivroCaixa  $caixa  O livro-caixa (o Laravel entrega pronto).
     */
    public function __construct(private readonly LivroCaixa $caixa) {}

    /**
     * Registra o pagamento da anuidade de UM ano para um filiado.
     *
     * @param  Filiado  $filiado  Quem pagou.
     * @param  int  $ano  De qual ano. Ex.: 2027
     * @param  string  $pagoEm  Dia em que o dinheiro entrou. Ex.: '2027-03-15'
     * @param  int  $formaPagamentoId  Como pagou. Ex.: o id do 'PIX'
     * @return AnuidadePagamento O pagamento criado (com a receita ligada).
     */
    public function registrarPagamento(Filiado $filiado, int $ano, string $pagoEm, int $formaPagamentoId): AnuidadePagamento
    {
        // O valor do ano precisa estar cadastrado e não ser isento.
        $anuidade = Anuidade::where('ano', $ano)->first();
        if ($anuidade === null) {
            throw new RegraFinanceira('ANUIDADE_SEM_VALOR', "Não há valor de anuidade cadastrado para {$ano}.");
        }
        if ($anuidade->isenta) {
            throw new RegraFinanceira('ANUIDADE_ISENTA', "A anuidade de {$ano} é isenta: não há o que pagar.");
        }

        // A categoria "Anuidade" (receita) do livro-caixa.
        $categoriaId = FinCategoria::where('nome', 'Anuidade')->where('tipo', 'receita')->value('id');
        if ($categoriaId === null) {
            throw new RegraFinanceira('CATEGORIA_ANUIDADE_AUSENTE', 'A categoria "Anuidade" não existe no financeiro.');
        }

        return DB::transaction(function () use ($filiado, $ano, $pagoEm, $formaPagamentoId, $anuidade, $categoriaId): AnuidadePagamento {
            // Trava a linha do filiado (lockForUpdate): se duas pessoas registrarem
            // ao mesmo tempo, a segunda espera e vê que já está pago.
            Filiado::query()->lockForUpdate()->findOrFail($filiado->id);

            // Já existe um pagamento VÁLIDO (não estornado) deste ano? Recusa.
            if ($filiado->pagamentosAnuidade()->validos()->where('ano', $ano)->exists()) {
                throw new RegraFinanceira('ANUIDADE_JA_PAGA', "A anuidade de {$ano} deste filiado já está paga.");
            }

            // 1) A receita no livro-caixa, pendente por um instante (vencimento =
            // dia do pagamento). A descrição leva só o REGISTRO (sem nome: LGPD).
            $receita = $this->caixa->lancar([
                'tipo' => 'receita',
                'categoria_id' => (int) $categoriaId,
                'descricao' => "Anuidade {$ano} — {$filiado->registro}",
                'valor_centavos' => $anuidade->valor_centavos,
                'vencimento' => $pagoEm,
                'equipe_id' => $filiado->equipe_id,
                'origem' => 'anuidade',
            ]);

            // 2) O pagamento da anuidade, ligado à receita.
            $pagamento = AnuidadePagamento::create([
                'filiado_id' => $filiado->id,
                'ano' => $ano,
                'valor_centavos' => $anuidade->valor_centavos,
                'pago_em' => $pagoEm,
                'lancamento_id' => $receita->id,
                'registrado_por' => Auth::id(),
            ]);

            // A receita aponta de volta para o pagamento (origem_id), ainda
            // pendente (depois de paga ela não muda mais)...
            $receita->forceFill(['origem_id' => $pagamento->id])->save();
            // ...e recebe a baixa: o dinheiro entrou.
            $this->caixa->pagar($receita, $pagoEm, $formaPagamentoId);

            Auditoria::registrar('ANUIDADE_PAGA', [
                'filiado_id' => $filiado->id,
                'ano' => $ano,
                'pagamento_id' => $pagamento->id,
                'lancamento_id' => $receita->id,
                'valor_centavos' => $anuidade->valor_centavos,
            ]);

            return $pagamento;
        });
    }
}
