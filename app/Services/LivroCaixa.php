<?php

/*
 * LIVRO-CAIXA do IBTO (#59): o único lugar que cria e muda lançamentos.
 * Confere as regras e grava a auditoria na MESMA transação do banco
 * (ou grava o lançamento e a auditoria, ou não grava nada).
 *
 * Regras (Gleyson, 09/10/2026, no molde do Dojo Manager):
 * - valor em centavos, maior que zero; a categoria precisa ser do mesmo tipo;
 * - pendente pode ser pago ou cancelado (com motivo);
 * - pago nunca muda: corrige-se com UM estorno (com motivo);
 * - nada acontece sozinho: cada ação vem de um pedido explícito de alguém.
 */

namespace App\Services;

use App\Exceptions\RegraFinanceira;
use App\Models\Auditoria;
use App\Models\FinCategoria;
use App\Models\FinLancamento;
use App\Support\Dinheiro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LivroCaixa
{
    // Máximo de parcelas de um título: 120 (10 anos de parcelas mensais). Fica
    // num lugar SÓ: a conferência do formulário e a tela leem daqui (sem repetir
    // o número). O banco aceita até 255 nessa coluna.
    public const PARCELAS_MAXIMO = 120;

    /**
     * Cria um lançamento PENDENTE (uma conta a receber ou a pagar).
     *
     * @param  array{tipo: string, categoria_id: int, descricao: string, valor_centavos: int, vencimento: string, equipe_id?: int|null, origem?: string|null, origem_id?: int|null}  $dados
     *                                                                                                                                                                                       Ex.: ['tipo' => 'despesa', 'categoria_id' => 5, 'descricao' => 'Hospedagem out/2027', 'valor_centavos' => 4990, 'vencimento' => '2027-10-10']
     * @return FinLancamento O lançamento criado.
     */
    public function lancar(array $dados): FinLancamento
    {
        // Valor maior que zero e categoria do mesmo tipo (ver abaixo).
        $this->conferirValorECategoria($dados);

        return DB::transaction(function () use ($dados): FinLancamento {
            $lancamento = new FinLancamento;
            // forceFill: o modelo não aceita preenchimento direto de formulário (ver $guarded).
            $lancamento->forceFill([
                'tipo' => $dados['tipo'],
                'categoria_id' => $dados['categoria_id'],
                'descricao' => $dados['descricao'],
                'valor_centavos' => $dados['valor_centavos'],
                'vencimento' => $dados['vencimento'],
                'situacao' => 'pendente',
                'equipe_id' => $dados['equipe_id'] ?? null,
                'origem' => $dados['origem'] ?? null,
                'origem_id' => $dados['origem_id'] ?? null,
                'criado_por' => Auth::id(),
            ])->save();

            Auditoria::registrar('LANCAMENTO_CRIADO', $this->resumo($lancamento));

            return $lancamento;
        });
    }

    /**
     * PARCELA um título: cria N lançamentos pendentes, um por mês, ligados pelo
     * mesmo grupo. Ex.: R$ 900,00 em 3x com 1º vencimento em 10/11 → três de
     * R$ 300,00 em 10/11, 10/12 e 10/01.
     *
     * @param  array{tipo: string, categoria_id: int, descricao: string, valor_centavos: int, vencimento: string, equipe_id?: int|null}  $dados
     *                                                                                                                                           O título inteiro (valor TOTAL e o 1º vencimento).
     * @param  int  $parcelas  Em quantas vezes, escolhido pela pessoa. Ex.: 3 (de 2 a PARCELAS_MAXIMO).
     * @return list<FinLancamento> As parcelas criadas, na ordem (1/N primeiro).
     */
    public function lancarParcelado(array $dados, int $parcelas): array
    {
        // Entre 2 e o máximo (1 é "à vista": usa lancar()).
        if ($parcelas < 2 || $parcelas > self::PARCELAS_MAXIMO) {
            throw new RegraFinanceira('PARCELAS_INVALIDAS', 'Escolha de 2 a '.self::PARCELAS_MAXIMO.' parcelas.');
        }
        // Mesmas regras do lançamento comum.
        $this->conferirValorECategoria($dados);
        // Cada parcela precisa valer pelo menos 1 centavo.
        if ($dados['valor_centavos'] < $parcelas) {
            throw new RegraFinanceira('VALOR_INVALIDO', 'O valor é pequeno demais para tantas parcelas.');
        }

        // Valor de cada parcela (lacuna do Gleyson, ver Dinheiro::dividirEmParcelas).
        $valores = Dinheiro::dividirEmParcelas($dados['valor_centavos'], $parcelas);
        // Código do grupo: o mesmo em todas as parcelas deste título.
        $grupo = (string) Str::uuid();
        // 1º vencimento; os outros são "o mesmo dia nos meses seguintes".
        $primeiro = Carbon::parse($dados['vencimento']);

        return DB::transaction(function () use ($dados, $parcelas, $valores, $grupo, $primeiro): array {
            $criadas = [];
            foreach ($valores as $posicao => $valor) {
                $numero = $posicao + 1;
                $lancamento = new FinLancamento;
                $lancamento->forceFill([
                    'tipo' => $dados['tipo'],
                    'categoria_id' => $dados['categoria_id'],
                    // Ex.: 'Material (2/3)'.
                    'descricao' => "{$dados['descricao']} ({$numero}/{$parcelas})",
                    'valor_centavos' => $valor,
                    // addMonthsNoOverflow: 31/01 + 1 mês = 28/02 (não "pula" para março).
                    'vencimento' => $primeiro->copy()->addMonthsNoOverflow($posicao)->toDateString(),
                    'parcela_numero' => $numero,
                    'parcela_total' => $parcelas,
                    'grupo_parcelas' => $grupo,
                    'situacao' => 'pendente',
                    'equipe_id' => $dados['equipe_id'] ?? null,
                    'criado_por' => Auth::id(),
                ])->save();
                $criadas[] = $lancamento;
            }

            // Uma linha de auditoria para o título inteiro (sem a descrição: LGPD).
            Auditoria::registrar('LANCAMENTO_PARCELADO', [
                'grupo_parcelas' => $grupo,
                'parcelas' => $parcelas,
                'tipo' => $dados['tipo'],
                'valor_total_centavos' => $dados['valor_centavos'],
                'lancamento_ids' => array_map(fn (FinLancamento $l) => $l->id, $criadas),
            ]);

            return $criadas;
        });
    }

    /**
     * EDITA um lançamento PENDENTE (ainda sem dinheiro envolvido). Pago não se
     * edita (só estorno) e cancelado também não (já saiu das contas).
     *
     * @param  FinLancamento  $lancamento  O lançamento pendente.
     * @param  array{tipo: string, categoria_id: int, descricao: string, valor_centavos: int, vencimento: string, equipe_id?: int|null}  $dados  Os dados novos (mesmo formato de lancar()).
     * @return FinLancamento O mesmo lançamento, atualizado.
     */
    public function editar(FinLancamento $lancamento, array $dados): FinLancamento
    {
        // Só pendente pode ser editado.
        if ($lancamento->situacao !== 'pendente') {
            throw new RegraFinanceira('LANCAMENTO_NAO_PENDENTE', 'Só um lançamento pendente pode ser editado. Se já foi pago, faça um estorno.');
        }
        // Mesmas regras do lançamento novo: valor maior que zero e categoria do mesmo tipo.
        $this->conferirValorECategoria($dados);

        return DB::transaction(function () use ($lancamento, $dados): FinLancamento {
            // Como estava antes, para a auditoria mostrar o "antes e depois".
            $antes = $this->resumo($lancamento) + ['vencimento' => $lancamento->vencimento->toDateString(), 'categoria_id' => $lancamento->categoria_id];

            $lancamento->forceFill([
                'tipo' => $dados['tipo'],
                'categoria_id' => $dados['categoria_id'],
                'descricao' => $dados['descricao'],
                'valor_centavos' => $dados['valor_centavos'],
                'vencimento' => $dados['vencimento'],
                'equipe_id' => $dados['equipe_id'] ?? null,
            ])->save();

            $depois = $this->resumo($lancamento) + ['vencimento' => $lancamento->vencimento->toDateString(), 'categoria_id' => $lancamento->categoria_id];
            Auditoria::registrar('LANCAMENTO_EDITADO', ['lancamento_id' => $lancamento->id, 'antes' => $antes, 'depois' => $depois]);

            return $lancamento;
        });
    }

    /**
     * Dá baixa: marca um lançamento PENDENTE como pago.
     *
     * @param  FinLancamento  $lancamento  O lançamento pendente.
     * @param  string  $pagoEm  Dia do pagamento. Ex.: '2027-03-15'
     * @param  int  $formaPagamentoId  Como foi pago. Ex.: o id do 'PIX'
     * @return FinLancamento O mesmo lançamento, agora pago.
     */
    public function pagar(FinLancamento $lancamento, string $pagoEm, int $formaPagamentoId): FinLancamento
    {
        // Só pendente pode ser pago (pago de novo ou cancelado, não).
        if ($lancamento->situacao !== 'pendente') {
            throw new RegraFinanceira('LANCAMENTO_NAO_PENDENTE', 'Só um lançamento pendente pode receber baixa.');
        }

        return DB::transaction(function () use ($lancamento, $pagoEm, $formaPagamentoId): FinLancamento {
            $lancamento->forceFill([
                'situacao' => 'pago',
                'pago_em' => $pagoEm,
                'forma_pagamento_id' => $formaPagamentoId,
            ])->save();

            Auditoria::registrar('LANCAMENTO_PAGO', $this->resumo($lancamento));

            return $lancamento;
        });
    }

    /**
     * Cancela um lançamento PENDENTE (a conta não existe mais). Fica no
     * histórico, mas não conta no saldo nem nos vencidos.
     *
     * @param  FinLancamento  $lancamento  O lançamento pendente.
     * @param  string  $motivo  Por que foi cancelado. Ex.: 'Lançado em duplicidade'
     * @return FinLancamento O mesmo lançamento, agora cancelado.
     */
    public function cancelar(FinLancamento $lancamento, string $motivo): FinLancamento
    {
        // Pago não se cancela: precisa de estorno (regra do Gleyson).
        if ($lancamento->situacao !== 'pendente') {
            throw new RegraFinanceira('LANCAMENTO_NAO_PENDENTE', 'Só um lançamento pendente pode ser cancelado. Se já foi pago, faça um estorno.');
        }
        $this->exigirMotivo($motivo);

        return DB::transaction(function () use ($lancamento, $motivo): FinLancamento {
            $lancamento->forceFill(['situacao' => 'cancelado', 'motivo' => trim($motivo)])->save();

            Auditoria::registrar('LANCAMENTO_CANCELADO', $this->resumo($lancamento));

            return $lancamento;
        });
    }

    /**
     * Estorna um lançamento PAGO: cria um lançamento novo, ligado ao original,
     * que anula o valor dele no saldo. O original não muda.
     *
     * @param  FinLancamento  $original  O lançamento pago que vai ser estornado.
     * @param  string  $motivo  Por quê. Ex.: 'Valor lançado errado (era R$ 80,00)'
     * @param  string  $data  Dia do estorno. Ex.: '2027-03-20'
     * @return FinLancamento O lançamento de estorno.
     */
    public function estornar(FinLancamento $original, string $motivo, string $data): FinLancamento
    {
        $this->exigirMotivo($motivo);

        return DB::transaction(function () use ($original, $motivo, $data): FinLancamento {
            // Relê o original TRAVANDO a linha (lockForUpdate): se duas pessoas
            // estornarem ao mesmo tempo, a segunda espera e vê que já foi estornado.
            $original = FinLancamento::query()->lockForUpdate()->findOrFail($original->id);

            // Só pago pode ser estornado (pendente se cancela).
            if ($original->situacao !== 'pago') {
                throw new RegraFinanceira('LANCAMENTO_NAO_PAGO', 'Só um lançamento pago pode ser estornado. Se está pendente, cancele.');
            }
            // Estorno de estorno não existe (viraria uma confusão sem fim).
            if ($original->estorno_de_id !== null) {
                throw new RegraFinanceira('ESTORNO_DE_ESTORNO', 'Um estorno não pode ser estornado.');
            }
            // Cada lançamento só pode ser estornado uma vez.
            if ($original->estorno()->exists()) {
                throw new RegraFinanceira('JA_ESTORNADO', 'Este lançamento já foi estornado.');
            }

            $estorno = new FinLancamento;
            $estorno->forceFill([
                // Mesmo tipo, categoria e valor do original; o sinal no saldo é que inverte.
                'tipo' => $original->tipo,
                'categoria_id' => $original->categoria_id,
                'forma_pagamento_id' => $original->forma_pagamento_id,
                'descricao' => 'Estorno: '.$original->descricao,
                'valor_centavos' => $original->valor_centavos,
                'vencimento' => $data,
                'pago_em' => $data,
                // Nasce pago: o dinheiro já "voltou" na data do estorno.
                'situacao' => 'pago',
                'motivo' => trim($motivo),
                'estorno_de_id' => $original->id,
                'equipe_id' => $original->equipe_id,
                'criado_por' => Auth::id(),
            ])->save();

            Auditoria::registrar('LANCAMENTO_ESTORNADO', $this->resumo($estorno) + ['estorno_de_id' => $original->id]);

            return $estorno;
        });
    }

    /**
     * Saldo do mês, em centavos: receitas pagas menos despesas pagas, com os
     * estornos descontados. Conta pelo DIA DO PAGAMENTO (pago_em), não pelo vencimento.
     *
     * @param  int  $ano  Ex.: 2027
     * @param  int  $mes  De 1 a 12. Ex.: 3
     * @return int Ex.: 4010 (= R$ 40,10); negativo se saiu mais do que entrou.
     */
    public function saldoDoMes(int $ano, int $mes): int
    {
        // Primeiro e último dia do mês. Ex.: 2027-03-01 e 2027-03-31.
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth();

        // Soma os pagos do mês, cada um com o sinal certo (ver FinLancamento::valorNoSaldo).
        return FinLancamento::query()
            ->where('situacao', 'pago')
            ->whereBetween('pago_em', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->sum(fn (FinLancamento $lancamento) => $lancamento->valorNoSaldo());
    }

    /**
     * Regras comuns de lançar e editar: valor maior que zero e categoria que
     * exista e seja do mesmo tipo (ex.: "Anuidade" não pode ser usada numa despesa).
     *
     * @param  array{tipo: string, categoria_id: int, valor_centavos: int}  $dados
     */
    private function conferirValorECategoria(array $dados): void
    {
        // Valor zero ou negativo não é conta: recusa.
        if ($dados['valor_centavos'] <= 0) {
            throw new RegraFinanceira('VALOR_INVALIDO', 'O valor precisa ser maior que zero.');
        }

        // Categoria inexistente ou de outro tipo: recusa.
        $categoria = FinCategoria::find($dados['categoria_id']);
        if ($categoria === null || $categoria->tipo !== $dados['tipo']) {
            throw new RegraFinanceira('CATEGORIA_INVALIDA', 'Escolha uma categoria do mesmo tipo do lançamento.');
        }
    }

    /**
     * Cancelar e estornar exigem um motivo de verdade (não vale vazio).
     *
     * @param  string  $motivo  Ex.: 'Lançado em duplicidade'
     */
    private function exigirMotivo(string $motivo): void
    {
        // Menos de 5 letras (sem contar espaços) não explica nada: recusa.
        if (mb_strlen(trim($motivo)) < 5) {
            throw new RegraFinanceira('MOTIVO_OBRIGATORIO', 'Explique o motivo (pelo menos 5 letras).');
        }
    }

    /**
     * Resumo do lançamento para a auditoria: só números e códigos (LGPD:
     * a descrição pode ter nome de pessoa, então fica de fora).
     *
     * @return array<string, mixed> Ex.: ['lancamento_id' => 7, 'tipo' => 'receita', 'valor_centavos' => 9000, 'situacao' => 'pago']
     */
    private function resumo(FinLancamento $lancamento): array
    {
        return [
            'lancamento_id' => $lancamento->id,
            'tipo' => $lancamento->tipo,
            'valor_centavos' => $lancamento->valor_centavos,
            'situacao' => $lancamento->situacao,
        ];
    }
}
