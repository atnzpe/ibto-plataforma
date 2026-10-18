<?php

/*
 * REGRAS DA MATRÍCULA (#41), num lugar só (as telas chamam daqui):
 * - matricular(): gratuito = confirmada na hora; pago = aguardando pagamento.
 *   Curso "só filiados" exige filiado ativo. VAGAS COM TRAVA: o curso fica
 *   bloqueado (lockForUpdate) enquanto as vagas são contadas, então duas
 *   pessoas disputando a última vaga não entram as duas (a segunda espera a
 *   primeira terminar e já vê a vaga ocupada) — como dois caixas do PDV que
 *   não vendem a última unidade do estoque duas vezes;
 * - confirmarPagamento(): a baixa MANUAL do PIX (molde da anuidade): lança a
 *   receita "Inscrição em curso" no livro-caixa, já paga, e guarda a
 *   referência na matrícula. Não confirma duas vezes. E a DIVISÃO (decisão
 *   de 14/10/2026): a parte do instrutor (100% − o percentual do IBTO
 *   escolhido no curso) vira uma CONTA A PAGAR "Instrutores", pendente, que
 *   o IBTO baixa no livro-caixa quando fizer o repasse. Curso com 100% do
 *   IBTO (Formação de instrutor) não gera conta a pagar;
 * - cancelar(): SEM apagar; a vaga volta e o histórico fica.
 * Tudo com auditoria, só com números (LGPD).
 */

namespace App\Actions\Cursos;

use App\Enums\AcessoCurso;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\FinCategoria;
use App\Models\User;
use App\Notifications\MatriculaConfirmada;
use App\Services\LivroCaixa;
use App\Support\Cupons;
use App\Support\Requisitos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Matriculas
{
    /**
     * @param  LivroCaixa  $caixa  O livro-caixa (o Laravel entrega pronto).
     */
    public function __construct(private readonly LivroCaixa $caixa) {}

    /**
     * Matricula a pessoa no curso.
     *
     * @param  Curso  $curso  O curso. Ex.: "Combate Corpo a Corpo" (R$ 250,00, 20 vagas).
     * @param  User  $usuario  Quem está se matriculando.
     * @param  bool  $peloIbto  true = é o IBTO matriculando a pessoa (único jeito de
     *                          entrar num curso FECHADO). Ex.: false na inscrição pelo site.
     * @param  string|null  $cupom  Cupom de desconto digitado (#89), ou null. Ex.: 'FILIADO10'.
     * @return CursoMatricula A matrícula criada (confirmada ou aguardando pagamento).
     *
     * @throws RegraCurso Quando a regra não deixa (o código diz o motivo).
     */
    public function matricular(Curso $curso, User $usuario, bool $peloIbto = false, ?string $cupom = null): CursoMatricula
    {
        // Só curso publicado (chancelado) recebe matrícula.
        if ($curso->situacao !== SituacaoCurso::Publicado) {
            throw new RegraCurso('CURSO_INDISPONIVEL', 'Este curso não está aberto para matrículas.');
        }

        // Curso FECHADO (ex.: Formação de instrutor): só o IBTO matricula.
        // Se não for fechado, ou se for o IBTO, segue para as outras regras.
        if ($curso->fechado && ! $peloIbto) {
            throw new RegraCurso('CURSO_FECHADO', 'Este curso é fechado: a matrícula é feita pelo IBTO.');
        }

        // Curso com data: matrículas até o dia de início (inclusive).
        if ($curso->data_inicio !== null && $curso->data_inicio->lt(today())) {
            throw new RegraCurso('MATRICULAS_ENCERRADAS', 'As matrículas deste curso já foram encerradas.');
        }

        // "Exclusivo para filiados": só quem é filiado com situação ativa.
        if ($curso->acesso === AcessoCurso::Restrito && $usuario->filiado?->status !== 'ativo') {
            throw new RegraCurso('MATRICULA_SO_FILIADOS', 'Este curso é exclusivo para filiados ativos do IBTO.');
        }

        // Pré-requisitos (#88): curso anterior, nível e tempo de filiado. Quem se inscreve sozinho
        // precisa cumprir todos; o IBTO pode matricular por exceção (com motivo, no
        // MatricularPeloIbtoController). Cumpre ou é o IBTO: segue para as outras regras.
        if (! $peloIbto && ! Requisitos::cumpre($curso, $usuario)) {
            throw new RegraCurso('MATRICULA_REQUISITO_PENDENTE', 'Você ainda não cumpre os pré-requisitos deste curso. Falta: '.implode('; ', Requisitos::pendentes($curso, $usuario)).'.');
        }

        return DB::transaction(function () use ($curso, $usuario, $cupom): CursoMatricula {
            // A TRAVA: lê o curso de novo, trancando a linha até o fim da transação.
            // Quem chegar ao mesmo tempo espera aqui e só conta as vagas depois.
            $curso = Curso::query()->lockForUpdate()->findOrFail($curso->id);

            // Já tem uma matrícula valendo (aguardando ou confirmada)? Não faz outra.
            if ($curso->matriculas()->ocupandoVaga()->where('user_id', $usuario->id)->exists()) {
                throw new RegraCurso('MATRICULA_JA_EXISTE', 'Você já está matriculado neste curso.');
            }

            // Vagas: null = sem limite. Senão, conta as que ocupam vaga.
            if ($curso->vagas !== null && $curso->matriculas()->ocupandoVaga()->count() >= $curso->vagas) {
                throw new RegraCurso('VAGAS_ESGOTADAS', 'As vagas deste curso acabaram.');
            }

            // Cupom (#89): conferido COM TRAVA (o último uso não passa para duas pessoas ao
            // mesmo tempo). Só em curso pago; o desconto nunca passa do preço.
            $cupomUsado = null;
            $desconto = 0;
            if ($cupom !== null && trim($cupom) !== '' && $curso->valor_centavos > 0) {
                $cupomUsado = Cupons::validar($cupom, $curso, $usuario, travar: true);
                $desconto = $cupomUsado->descontoSobre($curso->valor_centavos);
            }
            // O que de fato se paga. Ex.: R$ 250,00 com 10% → R$ 225,00.
            $aPagar = $curso->valor_centavos - $desconto;

            // Nada a pagar (gratuito ou cupom de 100%): confirmada na hora. Senão: aguarda o PIX.
            $gratuito = $aPagar === 0;

            $matricula = new CursoMatricula;
            $matricula->forceFill([
                'curso_id' => $curso->id,
                'user_id' => $usuario->id,
                'situacao' => $gratuito ? SituacaoMatricula::Confirmada : SituacaoMatricula::AguardandoPagamento,
                // O preço do dia JÁ com o desconto (se o curso mudar de preço depois, este fica).
                'valor_centavos' => $aPagar,
                // Cupom (#89): qual, o preço cheio e o desconto (só quando usou cupom).
                'cupom_id' => $cupomUsado?->id,
                'valor_cheio_centavos' => $cupomUsado !== null ? $curso->valor_centavos : null,
                'desconto_centavos' => $desconto,
                'confirmada_em' => $gratuito ? now() : null,
            ])->save();

            Auditoria::registrar('MATRICULA_CRIADA', [
                'matricula_id' => $matricula->id,
                'curso_id' => $curso->id,
                'usuario_id' => $usuario->id,
                'situacao' => $matricula->situacao->value,
                // Cupom usado (#89), só o número.
                'cupom_id' => $cupomUsado?->id,
            ]);

            return $matricula;
        });
    }

    /**
     * Confirma o pagamento (baixa manual do PIX), com a receita no livro-caixa.
     *
     * @param  CursoMatricula  $matricula  A matrícula aguardando pagamento.
     * @param  string  $pagoEm  Dia em que o dinheiro entrou. Ex.: '2026-10-12'
     * @param  int  $formaPagamentoId  Como pagou. Ex.: o id do 'PIX'.
     * @return CursoMatricula A matrícula confirmada.
     *
     * @throws RegraCurso Se já foi confirmada ou cancelada, ou falta uma categoria.
     */
    public function confirmarPagamento(CursoMatricula $matricula, string $pagoEm, int $formaPagamentoId): CursoMatricula
    {
        // A categoria "Inscrição em curso" (receita) do livro-caixa.
        $categoriaId = FinCategoria::where('nome', 'Inscrição em curso')->where('tipo', 'receita')->value('id');
        if ($categoriaId === null) {
            throw new RegraCurso('CATEGORIA_INSCRICAO_AUSENTE', 'A categoria "Inscrição em curso" não existe no financeiro.');
        }
        // A categoria "Instrutores" (despesa), onde entra o repasse ao instrutor.
        $categoriaRepasseId = FinCategoria::where('nome', 'Instrutores')->where('tipo', 'despesa')->value('id');
        if ($categoriaRepasseId === null) {
            throw new RegraCurso('CATEGORIA_INSTRUTORES_AUSENTE', 'A categoria "Instrutores" não existe no financeiro.');
        }

        $confirmada = DB::transaction(function () use ($matricula, $pagoEm, $formaPagamentoId, $categoriaId, $categoriaRepasseId): CursoMatricula {
            // Tranca a matrícula: dois cliques em "Confirmar" ao mesmo tempo não lançam duas receitas.
            $matricula = CursoMatricula::query()->lockForUpdate()->findOrFail($matricula->id);

            // Só a que está aguardando: confirmada ou cancelada não confirma de novo.
            if ($matricula->situacao !== SituacaoMatricula::AguardandoPagamento) {
                throw new RegraCurso('MATRICULA_NAO_AGUARDANDO', 'Esta matrícula não está aguardando pagamento (já foi confirmada ou cancelada).');
            }

            // 1) A receita no livro-caixa. Descrição só com o curso e o número da
            // matrícula (sem o nome da pessoa: LGPD).
            $receita = $this->caixa->lancar([
                'tipo' => 'receita',
                'categoria_id' => (int) $categoriaId,
                // Com cupom (#89), o código vai na descrição (o livro-caixa mostra o desconto dado).
                'descricao' => "Inscrição — {$matricula->curso->titulo} — matrícula nº {$matricula->id}"
                    .($matricula->cupom !== null ? " — cupom {$matricula->cupom->codigo}" : ''),
                'valor_centavos' => $matricula->valor_centavos,
                'vencimento' => $pagoEm,
                'equipe_id' => $matricula->curso->equipe_id,
                // Quem pagou (o aluno) e o curso.
                'pessoa_id' => $matricula->user_id,
                'curso_id' => $matricula->curso_id,
                'origem' => 'matricula',
                'origem_id' => $matricula->id,
            ]);
            // 2) A baixa: o dinheiro entrou.
            $this->caixa->pagar($receita, $pagoEm, $formaPagamentoId);

            // 2b) A DIVISÃO pelo percentual do curso. Ex.: R$ 250,00 com 20% →
            // IBTO R$ 50,00 e instrutor R$ 200,00.
            $curso = $matricula->curso;
            $divisao = $curso->divisao($matricula->valor_centavos);
            // A parte do instrutor vira uma conta A PAGAR, pendente (o IBTO dá a
            // baixa quando repassar). Parte zero (100% do IBTO): não lança nada.
            $repasse = null;
            if ($divisao['instrutor'] > 0) {
                $repasse = $this->caixa->lancar([
                    'tipo' => 'despesa',
                    'categoria_id' => (int) $categoriaRepasseId,
                    // Sem o nome do aluno (LGPD); o instrutor é o da vitrine do curso
                    // (sem instrutor escolhido ainda: "a definir").
                    'descricao' => 'Repasse ao instrutor ('.($curso->instrutorExibido() ?? 'a definir').") — {$curso->titulo} — matrícula nº {$matricula->id}",
                    'valor_centavos' => $divisao['instrutor'],
                    // Vence no dia do pagamento: aparece logo como "a pagar" no livro-caixa.
                    'vencimento' => $pagoEm,
                    'equipe_id' => $curso->equipe_id,
                    // Quem recebe: o instrutor do curso (ou o responsável, se não houver).
                    'pessoa_id' => $curso->instrutor_id ?? $curso->responsavel_id,
                    'curso_id' => $curso->id,
                    'origem' => 'repasse',
                    'origem_id' => $matricula->id,
                ]);
            }

            // 3) A matrícula guarda a referência do pagamento e quem confirmou.
            $matricula->forceFill([
                'situacao' => SituacaoMatricula::Confirmada,
                'lancamento_id' => $receita->id,
                'confirmada_por' => Auth::id(),
                'confirmada_em' => now(),
            ])->save();

            Auditoria::registrar('MATRICULA_CONFIRMADA', [
                'matricula_id' => $matricula->id,
                'curso_id' => $matricula->curso_id,
                'lancamento_id' => $receita->id,
                'valor_centavos' => $matricula->valor_centavos,
                // A divisão do dia (o percentual do curso pode mudar depois; o lançamento fica).
                'percentual_ibto' => $curso->percentual_ibto,
                'repasse_lancamento_id' => $repasse?->id,
                'repasse_centavos' => $divisao['instrutor'],
            ]);

            return $matricula;
        });

        // O e-mail ao aluno (sai depois da gravação; ver MatriculaConfirmada).
        $confirmada->usuario->notify(new MatriculaConfirmada($confirmada));

        return $confirmada;
    }

    /**
     * Cancela SEM apagar: a vaga volta e o histórico fica. Se já havia sido paga,
     * a devolução do dinheiro é feita no financeiro (estorno), por decisão humana.
     *
     * @param  CursoMatricula  $matricula  A matrícula.
     * @param  string  $motivo  Por quê. Ex.: 'Desistência do aluno'.
     * @return CursoMatricula A matrícula cancelada.
     *
     * @throws RegraCurso Sem motivo, ou já cancelada.
     */
    public function cancelar(CursoMatricula $matricula, string $motivo): CursoMatricula
    {
        // O motivo é obrigatório (fica no histórico).
        if (trim($motivo) === '') {
            throw new RegraCurso('MOTIVO_OBRIGATORIO', 'Explique o motivo do cancelamento.');
        }

        return DB::transaction(function () use ($matricula, $motivo): CursoMatricula {
            $matricula = CursoMatricula::query()->lockForUpdate()->findOrFail($matricula->id);

            // Já cancelada: nada a fazer.
            if ($matricula->situacao === SituacaoMatricula::Cancelada) {
                throw new RegraCurso('MATRICULA_JA_CANCELADA', 'Esta matrícula já está cancelada.');
            }

            $matricula->forceFill([
                'situacao' => SituacaoMatricula::Cancelada,
                'cancelada_em' => now(),
                'motivo_cancelamento' => trim($motivo),
            ])->save();

            Auditoria::registrar('MATRICULA_CANCELADA', [
                'matricula_id' => $matricula->id,
                'curso_id' => $matricula->curso_id,
                // true = tinha pagamento: lembrar do estorno no financeiro, se for devolver.
                'tinha_pagamento' => $matricula->lancamento_id !== null,
            ]);

            return $matricula;
        });
    }
}
