<?php

/*
 * REGRAS DAS ASSINATURAS DE AULAS REGULARES (#80), num lugar só.
 *
 * - assinar: confere programa ativo, "só filiados", vagas (à prova de corrida) e
 *   se a pessoa já não assina; cria a assinatura "aguardando" e a 1ª mensalidade
 *   (referência = mês atual, vence hoje).
 * - confirmarPagamento: o Comando Geral confere o Pix; vira receita "Aulas
 *   regulares" no livro-caixa e o repasse ao instrutor (conta a pagar
 *   "Instrutores"); a assinatura fica ativa e paga até o dia de vencimento do
 *   mês seguinte à referência. Ex.: paga a de 2026-10 com vencimento dia 10 →
 *   paga até 10/11/2026.
 * - gerarMensalidades: todo dia, para quem vence em até 3 dias, cria a
 *   mensalidade do mês e manda o aviso por e-mail (uma vez só por mês).
 * - cancelar: encerra a assinatura sem apagar o histórico.
 * Valores sempre em centavos. Erros com código estável (RegraCurso).
 */

namespace App\Actions\AulasRegulares;

use App\Exceptions\RegraCurso;
use App\Models\Assinatura;
use App\Models\AssinaturaPagamento;
use App\Models\Auditoria;
use App\Models\FinCategoria;
use App\Models\Programa;
use App\Models\User;
use App\Notifications\AvisoMensalidade;
use App\Services\LivroCaixa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Assinaturas
{
    /** Quantos dias antes do vencimento a mensalidade é gerada e o aviso sai. */
    public const DIAS_AVISO = 3;

    public function __construct(private readonly LivroCaixa $caixa) {}

    /**
     * A pessoa assina o programa.
     *
     * @param  Programa  $programa  O programa. Ex.: "Krav Maga online".
     * @param  User  $usuario  Quem assina.
     * @param  int|null  $turmaId  A turma escolhida (opcional). Ex.: 3.
     * @return Assinatura A assinatura nova, aguardando a 1ª mensalidade.
     *
     * @throws RegraCurso Quando a regra não deixa (o código diz o motivo).
     */
    public function assinar(Programa $programa, User $usuario, ?int $turmaId = null): Assinatura
    {
        // Programa desativado: ninguém assina.
        if (! $programa->ativo) {
            throw new RegraCurso('PROGRAMA_INDISPONIVEL', 'Este programa não está aberto para assinaturas.');
        }
        // "Só filiados": precisa ser filiado com situação ativa.
        if ($programa->so_filiados && $usuario->filiado?->status !== 'ativo') {
            throw new RegraCurso('PROGRAMA_SO_FILIADOS', 'Este programa é exclusivo para filiados ativos do IBTO.');
        }
        // A turma, se escolhida, precisa ser deste programa e estar ativa.
        if ($turmaId !== null && ! $programa->turmas()->where('id', $turmaId)->where('ativa', true)->exists()) {
            throw new RegraCurso('TURMA_INVALIDA', 'Escolha uma turma deste programa.');
        }

        $assinatura = DB::transaction(function () use ($programa, $usuario, $turmaId): Assinatura {
            // Tranca o programa: duas pessoas pela última vaga ao mesmo tempo, só uma entra.
            $programa = Programa::query()->lockForUpdate()->findOrFail($programa->id);

            // Já assina (aguardando ou ativa): não cria outra.
            if ($programa->assinaturas()->where('user_id', $usuario->id)->where('situacao', '!=', 'cancelada')->exists()) {
                throw new RegraCurso('JA_ASSINA', 'Você já assina este programa. Veja em "Minhas aulas".');
            }
            // Vagas: conta as que não foram canceladas.
            if ($programa->vagas !== null && $programa->assinaturas()->where('situacao', '!=', 'cancelada')->count() >= $programa->vagas) {
                throw new RegraCurso('PROGRAMA_SEM_VAGAS', 'As vagas deste programa acabaram.');
            }

            $assinatura = new Assinatura;
            $assinatura->forceFill(['programa_id' => $programa->id, 'user_id' => $usuario->id, 'turma_id' => $turmaId, 'situacao' => 'aguardando_pagamento'])->save();

            // A 1ª mensalidade: o mês atual, vencendo hoje.
            $this->novaMensalidade($assinatura, $programa, CarbonImmutable::today()->format('Y-m'), CarbonImmutable::today());

            return $assinatura;
        });

        Auditoria::registrar('ASSINATURA_CRIADA', ['assinatura_id' => $assinatura->id, 'programa_id' => $programa->id]);

        return $assinatura;
    }

    /**
     * O Comando Geral confirma o Pix de uma mensalidade.
     *
     * @param  AssinaturaPagamento  $pagamento  A mensalidade.
     * @param  string  $pagoEm  A data do Pix. Ex.: '2026-10-17'.
     * @param  int  $formaPagamentoId  A forma (PIX) do livro-caixa.
     * @return AssinaturaPagamento A mensalidade paga.
     *
     * @throws RegraCurso Quando já foi paga/cancelada ou falta a categoria no financeiro.
     */
    public function confirmarPagamento(AssinaturaPagamento $pagamento, string $pagoEm, int $formaPagamentoId): AssinaturaPagamento
    {
        $receitaId = FinCategoria::where('nome', 'Aulas regulares')->where('tipo', 'receita')->value('id');
        $repasseId = FinCategoria::where('nome', 'Instrutores')->where('tipo', 'despesa')->value('id');
        // As categorias nascem nas migrations; se alguém apagou, avisa em vez de lançar errado.
        if ($receitaId === null || $repasseId === null) {
            throw new RegraCurso('CATEGORIA_AULAS_AUSENTE', 'As categorias "Aulas regulares" e "Instrutores" precisam existir no financeiro.');
        }

        return DB::transaction(function () use ($pagamento, $pagoEm, $formaPagamentoId, $receitaId, $repasseId): AssinaturaPagamento {
            // Tranca: dois cliques em "Confirmar" não lançam duas receitas.
            $pagamento = AssinaturaPagamento::query()->lockForUpdate()->findOrFail($pagamento->id);
            if ($pagamento->situacao !== 'aguardando') {
                throw new RegraCurso('MENSALIDADE_NAO_AGUARDANDO', 'Esta mensalidade não está aguardando pagamento.');
            }
            $assinatura = $pagamento->assinatura;
            $programa = $assinatura->programa;

            // 1) A receita (sem o nome da pessoa: LGPD) e a baixa.
            $receita = $this->caixa->lancar([
                'tipo' => 'receita',
                'categoria_id' => (int) $receitaId,
                'descricao' => "Mensalidade {$pagamento->referencia} — {$programa->titulo} — assinatura nº {$assinatura->id}",
                'valor_centavos' => $pagamento->valor_centavos,
                'vencimento' => $pagoEm,
                'origem' => 'assinatura',
                'origem_id' => $pagamento->id,
            ]);
            $this->caixa->pagar($receita, $pagoEm, $formaPagamentoId);

            // 2) A parte do instrutor vira conta a pagar (o IBTO dá a baixa ao repassar).
            $divisao = $programa->divisao($pagamento->valor_centavos);
            if ($divisao['instrutor'] > 0) {
                $this->caixa->lancar([
                    'tipo' => 'despesa',
                    'categoria_id' => (int) $repasseId,
                    'descricao' => 'Repasse ao instrutor ('.($programa->instrutor_nome ?: 'a definir').") — {$programa->titulo} — mensalidade {$pagamento->referencia}",
                    'valor_centavos' => $divisao['instrutor'],
                    'vencimento' => $pagoEm,
                    // Origem própria: o livro-caixa não aceita origem + número repetidos, e
                    // "repasse" já é usada pelas matrículas de curso (com o número da matrícula).
                    'origem' => 'repasse_assinatura',
                    'origem_id' => $pagamento->id,
                ]);
            }

            // 3) A mensalidade paga e a assinatura em dia até o vencimento do mês seguinte.
            $pagamento->forceFill(['situacao' => 'pago', 'pago_em' => now(), 'lancamento_id' => $receita->id, 'confirmado_por' => Auth::id()])->save();
            $pagoAte = self::vencimentoDoMes(CarbonImmutable::createFromFormat('Y-m', $pagamento->referencia)->startOfMonth()->addMonth(), $programa->dia_vencimento);
            // Só avança (pagar um mês antigo depois não encurta o que já estava pago).
            if ($assinatura->pago_ate === null || $assinatura->pago_ate->lt($pagoAte)) {
                $assinatura->forceFill(['pago_ate' => $pagoAte->toDateString()]);
            }
            $assinatura->forceFill(['situacao' => 'ativa'])->save();

            Auditoria::registrar('MENSALIDADE_CONFIRMADA', ['pagamento_id' => $pagamento->id, 'assinatura_id' => $assinatura->id, 'valor_centavos' => $pagamento->valor_centavos, 'ibto_centavos' => $divisao['ibto'], 'instrutor_centavos' => $divisao['instrutor']]);

            return $pagamento;
        });
    }

    /**
     * Todo dia (agendador): gera a mensalidade de quem vence em até 3 dias e avisa por e-mail.
     *
     * @return int Quantas mensalidades novas foram geradas.
     */
    public function gerarMensalidades(): int
    {
        $limite = CarbonImmutable::today()->addDays(self::DIAS_AVISO);
        $geradas = 0;

        $assinaturas = Assinatura::query()->with('programa', 'usuario')->where('situacao', 'ativa')->whereNotNull('pago_ate')->whereDate('pago_ate', '<=', $limite->toDateString())->get();
        foreach ($assinaturas as $assinatura) {
            /** @var CarbonImmutable $vence */
            $vence = CarbonImmutable::parse($assinatura->pago_ate);
            $referencia = $vence->format('Y-m');
            // Já existe a mensalidade deste mês (gerada antes ou paga adiantada): não repete.
            if ($assinatura->pagamentos()->where('referencia', $referencia)->exists()) {
                continue;
            }
            $nova = $this->novaMensalidade($assinatura, $assinatura->programa, $referencia, $vence);
            // O aviso leva direto à página de pagamento desta mensalidade (/pagar/<código>).
            $assinatura->usuario->notify(new AvisoMensalidade($assinatura->programa->titulo, $vence->format('d/m/Y'), $nova->linkDePagamento()));
            $geradas++;
        }

        return $geradas;
    }

    /**
     * Cancela a assinatura (o histórico e os pagamentos ficam; as mensalidades em aberto são canceladas).
     *
     * @param  Assinatura  $assinatura  A assinatura.
     */
    public function cancelar(Assinatura $assinatura): void
    {
        DB::transaction(function () use ($assinatura): void {
            $assinatura->forceFill(['situacao' => 'cancelada', 'cancelada_em' => now()])->save();
            $assinatura->pagamentos()->where('situacao', 'aguardando')->update(['situacao' => 'cancelado']);
        });
        Auditoria::registrar('ASSINATURA_CANCELADA', ['assinatura_id' => $assinatura->id]);
    }

    /**
     * O dia de vencimento dentro de um mês. Ex.: (novembro/2026, 10) → 10/11/2026.
     *
     * @param  CarbonImmutable  $mes  Qualquer dia do mês.
     * @param  int  $dia  1 a 28.
     */
    public static function vencimentoDoMes(CarbonImmutable $mes, int $dia): CarbonImmutable
    {
        return $mes->startOfMonth()->addDays(max(1, min(28, $dia)) - 1);
    }

    /**
     * Cria a mensalidade de um mês, com o valor de hoje do programa.
     */
    private function novaMensalidade(Assinatura $assinatura, Programa $programa, string $referencia, CarbonImmutable $venceEm): AssinaturaPagamento
    {
        $pagamento = new AssinaturaPagamento;
        $pagamento->forceFill([
            'assinatura_id' => $assinatura->id,
            'referencia' => $referencia,
            'valor_centavos' => $programa->valor_mensal_centavos,
            'situacao' => 'aguardando',
            'vence_em' => $venceEm->toDateString(),
        ])->save();

        return $pagamento;
    }
}
