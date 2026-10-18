<?php

/*
 * PAINEL FINANCEIRO DAS AULAS REGULARES (17/10/2026, pedido do Gleyson).
 * No molde do "Cockpit" do Dojo Manager: de onde viemos, onde estamos e para
 * onde vamos. Responde, no período (pelo VENCIMENTO da mensalidade):
 * - quem treina (alunos, programa, turma, mensalidade e se está em dia);
 * - quanto estava PREVISTO, quanto já foi RECEBIDO, quanto FALTA receber e
 *   quanto está EM ATRASO;
 * - a DIVISÃO: quanto fica para o IBTO e quanto vai para o instrutor, e quanto
 *   desse repasse já foi pago (baixa no livro-caixa) e quanto falta repassar;
 * - os números por programa, por instrutor e por mês.
 *
 * Filtros: De/Até, programa, instrutor (quem RECEBE o repasse), aluno (quem
 * PAGA: busca pelo nome) e situação da mensalidade.
 * Escopo: quem tem "relatorio.financeiro" vê tudo; o instrutor vê só os
 * programas dele. LGPD: só o nome do aluno (sem CPF, telefone ou endereço).
 * Valores sempre em CENTAVOS (12000 = R$ 120,00), para não errar arredondamento.
 */

namespace App\Relatorios;

use App\Models\Assinatura;
use App\Models\AssinaturaPagamento;
use App\Models\FinLancamento;
use App\Models\Programa;
use App\Models\User;
use App\Support\Periodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * O formato de uma mensalidade no painel (definido uma vez, usado em todo lugar).
 *
 * @phpstan-type Mensalidade array{id: int, referencia: string, vence_em: string, pago_em: string|null, aluno: string, programa_id: int, programa: string, turma: string, instrutor_id: int|null, instrutor: string, valor: int, situacao: string, situacao_rotulo: string, ibto: int, instrutor_parte: int, repassado: bool}
 */
class PainelAulasRegulares
{
    /** As situações que o filtro aceita, com o rótulo da tela. */
    public const SITUACOES = ['pago' => 'Pago', 'aberto' => 'A receber (no prazo)', 'atrasado' => 'Em atraso'];

    /**
     * @param  Periodo  $periodo  De/Até (pelo vencimento da mensalidade).
     * @param  User  $usuario  Quem pediu: sem "relatorio.financeiro", vê só os programas dele.
     * @param  bool  $soOsMeus  true = só os programas da pessoa (Meu painel).
     * @param  array{programa?: int|null, instrutor?: int|null, pagador?: string|null, situacao?: string|null}  $filtros
     *                                                                                                                    Ex.: ['programa' => 3, 'pagador' => 'Silva', 'situacao' => 'atrasado'].
     */
    public function __construct(
        private readonly Periodo $periodo,
        private readonly User $usuario,
        private readonly array $filtros = [],
        // true = só os programas em que a pessoa é o instrutor, mesmo que ela veja tudo
        // (usado no "Meu painel": cada um vê o próprio dinheiro).
        private readonly bool $soOsMeus = false,
    ) {}

    /**
     * Quem vê tudo (Comando Geral e Tecnologia)? Os demais (instrutor) veem só os programas deles.
     */
    public function veTudo(): bool
    {
        return ! $this->soOsMeus && $this->usuario->temPermissao('relatorio.financeiro');
    }

    /**
     * Os programas que esta pessoa pode ver (para os filtros e as consultas).
     *
     * @return Builder<Programa>
     */
    public function programasVisiveis(): Builder
    {
        $consulta = Programa::query()->orderBy('titulo');
        // Sem a visão completa: só os programas em que ela é o instrutor.
        if (! $this->veTudo()) {
            $consulta->where('instrutor_id', $this->usuario->id);
        }

        return $consulta;
    }

    /**
     * Uma linha por MENSALIDADE do período, já com a situação, a divisão e o repasse.
     *
     * @return list<Mensalidade>
     */
    public function mensalidades(): array
    {
        $hoje = today()->toDateString();
        $consulta = AssinaturaPagamento::query()
            ->with(['assinatura.programa', 'assinatura.usuario', 'assinatura.turma'])
            ->whereBetween('vence_em', [$this->periodo->inicio->toDateString(), $this->periodo->fim->toDateString()])
            // Mensalidade cancelada (assinatura cancelada) não é dinheiro previsto: fica de fora.
            ->where('situacao', '!=', 'cancelado')
            ->whereHas('assinatura', fn (Builder $assinatura) => $this->filtrarAssinatura($assinatura));

        // Filtro de situação: pago; a receber no prazo; ou em atraso (aguardando e já venceu).
        match ($this->filtros['situacao'] ?? null) {
            'pago' => $consulta->where('situacao', 'pago'),
            'aberto' => $consulta->where('situacao', 'aguardando')->whereDate('vence_em', '>=', $hoje),
            'atrasado' => $consulta->where('situacao', 'aguardando')->whereDate('vence_em', '<', $hoje),
            default => null,
        };

        $pagamentos = $consulta->orderBy('vence_em')->get();

        // Os repasses JÁ PAGOS ao instrutor (baixa no livro-caixa), numa consulta só.
        $repassados = FinLancamento::query()
            ->where('origem', 'repasse_assinatura')
            ->whereIn('origem_id', $pagamentos->pluck('id'))
            ->where('situacao', 'pago')
            ->pluck('origem_id')
            ->flip();

        // array_values: lista simples (posições 0, 1, 2…).
        return array_values($pagamentos->map(function (AssinaturaPagamento $pagamento) use ($hoje, $repassados): array {
            $assinatura = $pagamento->assinatura;
            $programa = $assinatura->programa;
            // A divisão usa o percentual ATUAL do programa (o mesmo da confirmação do Pix).
            $divisao = $programa->divisao($pagamento->valor_centavos);
            // Pago; aguardando dentro do prazo (a receber); ou aguardando vencida (em atraso).
            $situacao = $pagamento->situacao === 'pago'
                ? 'pago'
                : ($pagamento->vence_em->toDateString() < $hoje ? 'atrasado' : 'aberto');

            return [
                'id' => $pagamento->id,
                'referencia' => $pagamento->referencia,
                'vence_em' => $pagamento->vence_em->toDateString(),
                'pago_em' => $pagamento->pago_em?->toDateString(),
                'aluno' => $assinatura->usuario->name,
                'programa_id' => $programa->id,
                'programa' => $programa->titulo,
                'turma' => $assinatura->turma->nome ?? '—',
                'instrutor_id' => $programa->instrutor_id,
                'instrutor' => $programa->instrutor_nome ?: 'A definir',
                'valor' => $pagamento->valor_centavos,
                'situacao' => $situacao,
                'situacao_rotulo' => self::SITUACOES[$situacao],
                'ibto' => $divisao['ibto'],
                'instrutor_parte' => $divisao['instrutor'],
                // O repasse só existe depois do pagamento; "true" = o IBTO já pagou o instrutor.
                'repassado' => $repassados->has($pagamento->id),
            ];
        })->all());
    }

    /**
     * QUEM TREINA: as assinaturas não canceladas (com os filtros de programa,
     * instrutor e aluno), com a mensalidade e a situação de acesso hoje.
     *
     * @return list<array{id: int, aluno: string, programa: string, turma: string, instrutor: string, mensalidade: int, acesso: string, acesso_rotulo: string, pago_ate: string|null}>
     */
    public function alunos(): array
    {
        $consulta = Assinatura::query()->with(['programa', 'usuario', 'turma'])->where('situacao', '!=', 'cancelada');
        $this->filtrarAssinatura($consulta);

        // array_values: devolve uma lista simples (posições 0, 1, 2…), como a tela espera.
        return array_values($consulta->get()
            ->map(fn (Assinatura $assinatura): array => [
                'id' => $assinatura->id,
                'aluno' => $assinatura->usuario->name,
                'programa' => $assinatura->programa->titulo,
                'turma' => $assinatura->turma->nome ?? '—',
                'instrutor' => $assinatura->programa->instrutor_nome ?: 'A definir',
                'mensalidade' => $assinatura->programa->valor_mensal_centavos,
                'acesso' => $assinatura->acesso()['acesso'],
                'acesso_rotulo' => $assinatura->acesso()['rotulo'],
                'pago_ate' => $assinatura->pago_ate?->toDateString(),
            ])
            // Em ordem de programa e depois de nome (fácil de achar na chamada).
            ->sortBy(fn (array $linha) => $linha['programa'].'|'.$linha['aluno'])
            ->all());
    }

    /**
     * Tudo o que a tela mostra: os cartões do topo, as tabelas e a evolução por mês.
     *
     * @return array<string, mixed>
     */
    public function montar(): array
    {
        // Em coleção, para somar e agrupar com facilidade.
        $linhas = collect($this->mensalidades());
        $alunos = $this->alunos();

        return [
            'totais' => self::somar($linhas) + [
                // Quantos treinam hoje (em dia ou na carência) e quantos estão travados.
                'alunos' => count($alunos),
                'alunos_em_dia' => count(array_filter($alunos, fn (array $aluno) => in_array($aluno['acesso'], ['liberado', 'carencia'], true))),
                'alunos_travados' => count(array_filter($alunos, fn (array $aluno) => $aluno['acesso'] === 'travado')),
            ],
            'por_programa' => $this->agrupar($linhas, 'programa_id', 'programa'),
            'por_instrutor' => $this->agrupar($linhas, 'instrutor', 'instrutor'),
            'por_mes' => $this->agrupar($linhas, 'referencia', 'referencia', true),
            'alunos' => $alunos,
            'mensalidades' => $linhas->all(),
        ];
    }

    /**
     * Soma um grupo de mensalidades: previsto, recebido, a receber, em atraso e a divisão.
     *
     * @param  Collection<int, Mensalidade>  $linhas
     * @return array{previsto: int, recebido: int, a_receber: int, atrasado: int, percentual_recebido: int|null, ibto_previsto: int, instrutor_previsto: int, ibto_recebido: int, instrutor_recebido: int, repassado: int, a_repassar: int, mensalidades: int}
     */
    public static function somar(Collection $linhas): array
    {
        $pagas = $linhas->where('situacao', 'pago');
        $previsto = (int) $linhas->sum('valor');
        $recebido = (int) $pagas->sum('valor');
        // Parte do instrutor nas mensalidades pagas: o que já foi repassado e o que falta.
        $instrutorRecebido = (int) $pagas->sum('instrutor_parte');
        $repassado = (int) $pagas->where('repassado', true)->sum('instrutor_parte');

        return [
            'previsto' => $previsto,
            'recebido' => $recebido,
            'a_receber' => (int) $linhas->where('situacao', 'aberto')->sum('valor'),
            'atrasado' => (int) $linhas->where('situacao', 'atrasado')->sum('valor'),
            // Percentual recebido do previsto; sem previsão, não há percentual (null = "—").
            'percentual_recebido' => $previsto > 0 ? intdiv($recebido * 100, $previsto) : null,
            'ibto_previsto' => (int) $linhas->sum('ibto'),
            'instrutor_previsto' => (int) $linhas->sum('instrutor_parte'),
            'ibto_recebido' => (int) $pagas->sum('ibto'),
            'instrutor_recebido' => $instrutorRecebido,
            'repassado' => $repassado,
            'a_repassar' => $instrutorRecebido - $repassado,
            'mensalidades' => $linhas->count(),
        ];
    }

    /**
     * Agrupa as mensalidades (por programa, instrutor ou mês) e soma cada grupo.
     *
     * @param  Collection<int, Mensalidade>  $linhas
     * @param  string  $chave  O campo do grupo. Ex.: 'programa_id'.
     * @param  string  $nome  O campo do nome mostrado. Ex.: 'programa'.
     * @param  bool  $porOrdemDaChave  true = em ordem do grupo (meses em ordem); false = maior previsto primeiro.
     * @return list<array<string, mixed>>
     */
    private function agrupar(Collection $linhas, string $chave, string $nome, bool $porOrdemDaChave = false): array
    {
        $grupos = $linhas->groupBy($chave)
            ->map(fn (Collection $grupo) => ['nome' => (string) $grupo->first()[$nome]] + self::somar($grupo)
                // Quantos alunos diferentes pagam neste grupo.
                + ['alunos' => $grupo->pluck('aluno')->unique()->count()]);

        $grupos = $porOrdemDaChave ? $grupos->sortKeys() : $grupos->sortByDesc('previsto');

        // array_values: lista simples (posições 0, 1, 2…).
        return array_values($grupos->all());
    }

    /**
     * Aplica nas assinaturas o escopo da pessoa e os filtros de programa, instrutor e aluno.
     *
     * @param  Builder<Assinatura>  $assinatura
     * @return Builder<Assinatura>
     */
    private function filtrarAssinatura(Builder $assinatura): Builder
    {
        $programa = $this->filtros['programa'] ?? null;
        $instrutor = $this->filtros['instrutor'] ?? null;
        $pagador = trim((string) ($this->filtros['pagador'] ?? ''));

        // Só os programas que a pessoa pode ver, e os filtros de programa e instrutor.
        $assinatura->whereIn('programa_id', $this->programasVisiveis()
            ->when($programa !== null, fn (Builder $consulta) => $consulta->whereKey($programa))
            ->when($instrutor !== null, fn (Builder $consulta) => $consulta->where('instrutor_id', $instrutor))
            ->reorder()->select('id'));

        // Aluno (quem paga): parte do nome. Sem texto, não filtra.
        if ($pagador !== '') {
            $assinatura->whereHas('usuario', fn (Builder $usuario) => $usuario->where('name', 'like', '%'.addcslashes($pagador, '%_\\').'%'));
        }

        return $assinatura;
    }
}
