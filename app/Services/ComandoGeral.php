<?php

/*
 * COMANDO GERAL (#59 bloco 4 / #45): calcula os números do painel do IBTO,
 * no desenho aprovado pelo Gleyson em 09/10/2026:
 * - totais do mês (saldo, a receber, a pagar, vencidos);
 * - entradas × saídas dos últimos 6 meses;
 * - anuidade do ano (% em dia) e a régua de cobrança;
 * - próximos vencimentos;
 * - filiados (ativos, equipes, convites pendentes, por categoria);
 * - cursos (turmas abertas, vagas livres, aguardando pagamento, confirmadas no mês).
 * Só números e códigos: o REGISTRO aparece na régua, nunca o nome (LGPD).
 */

namespace App\Services;

use App\Enums\CategoriaFiliado;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusEquipe;
use App\Models\Anuidade;
use App\Models\AnuidadePagamento;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\FinLancamento;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ComandoGeral
{
    /**
     * @param  LivroCaixa  $caixa  O livro-caixa (para o saldo do mês).
     */
    public function __construct(private readonly LivroCaixa $caixa) {}

    /**
     * Todos os números do painel, para uma data de referência.
     *
     * @param  CarbonInterface|null  $hoje  O dia de referência (vazio = hoje). Ex.: Carbon::parse('2027-10-15')
     * @return array<string, mixed>
     */
    public function painel(?CarbonInterface $hoje = null): array
    {
        $hoje = ($hoje ?? now())->copy()->startOfDay();

        return [
            'mes' => $hoje->format('Y-m'),
            'totais' => $this->totais($hoje),
            'fluxo' => $this->fluxo($hoje),
            'anuidade' => $this->anuidade($hoje),
            'vencimentos' => $this->proximosVencimentos($hoje),
            'filiados' => $this->filiados(),
            'cursos' => $this->cursos($hoje),
        ];
    }

    /**
     * O quadro CURSOS (desenho aprovado em 09/10/2026, que esperava as #39 e #41):
     * turmas abertas, vagas livres, matrículas aguardando pagamento e as
     * confirmadas no mês. Os certificados entram com a #44.
     *
     * @return array{turmas_abertas: int, vagas_livres: int|null, aguardando_pagamento: int, confirmadas_no_mes: int}
     *                                                                                                                vagas_livres null = algum curso aberto não tem limite de vagas.
     */
    private function cursos(CarbonInterface $hoje): array
    {
        // Turmas abertas: publicadas e que ainda não começaram (ou sem data: online no seu ritmo).
        $abertas = Curso::query()
            ->where('situacao', SituacaoCurso::Publicado)
            ->where(fn (Builder $quando) => $quando->whereNull('data_inicio')->orWhere('data_inicio', '>=', $hoje->toDateString()))
            ->withCount(['matriculas as ocupadas' => fn (Builder $matricula) => $matricula->where('situacao', '!=', SituacaoMatricula::Cancelada->value)])
            ->get(['id', 'vagas']);

        // Vagas livres: a soma do que sobra em cada turma. Algum curso sem limite = "sem limite" (null).
        $vagasLivres = $abertas->contains(fn (Curso $curso) => $curso->vagas === null)
            ? null
            : (int) $abertas->sum(fn (Curso $curso) => max(0, (int) $curso->vagas - (int) $curso->getAttribute('ocupadas')));

        return [
            'turmas_abertas' => $abertas->count(),
            'vagas_livres' => $vagasLivres,
            'aguardando_pagamento' => CursoMatricula::where('situacao', SituacaoMatricula::AguardandoPagamento->value)->count(),
            'confirmadas_no_mes' => CursoMatricula::query()
                ->where('situacao', SituacaoMatricula::Confirmada->value)
                ->whereBetween('confirmada_em', [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()])
                ->count(),
        ];
    }

    /**
     * Os 4 cartões do topo.
     *
     * @return array{saldo_centavos: int, a_receber_centavos: int, a_pagar_centavos: int, vencidos: int}
     */
    private function totais(CarbonInterface $hoje): array
    {
        $inicio = $hoje->copy()->startOfMonth()->toDateString();
        $fim = $hoje->copy()->endOfMonth()->toDateString();

        // Pendentes que vencem no mês, separados por tipo.
        $pendentes = fn (string $tipo) => (int) FinLancamento::query()
            ->where('situacao', 'pendente')->where('tipo', $tipo)
            ->whereBetween('vencimento', [$inicio, $fim])
            ->sum('valor_centavos');

        return [
            'saldo_centavos' => $this->caixa->saldoDoMes($hoje->year, $hoje->month),
            'a_receber_centavos' => $pendentes('receita'),
            'a_pagar_centavos' => $pendentes('despesa'),
            'vencidos' => FinLancamento::query()->vencidos($hoje)->count(),
        ];
    }

    /**
     * Entradas e saídas PAGAS de cada um dos últimos 6 meses (o atual por último),
     * com os estornos descontados. Ex.: [['mes' => '2027-05', 'entradas_centavos' => 9000, 'saidas_centavos' => 4990], ...]
     *
     * @return list<array{mes: string, entradas_centavos: int, saidas_centavos: int}>
     */
    private function fluxo(CarbonInterface $hoje): array
    {
        // Os 6 meses, do mais antigo ao atual. Ex.: 2027-05 ... 2027-10.
        $meses = [];
        for ($voltar = 5; $voltar >= 0; $voltar--) {
            $meses[$hoje->copy()->startOfMonth()->subMonthsNoOverflow($voltar)->format('Y-m')] = ['entradas' => 0, 'saidas' => 0];
        }

        // Todos os pagos desde o 1º dia do mês mais antigo (poucas linhas; soma no PHP).
        $desde = $hoje->copy()->startOfMonth()->subMonthsNoOverflow(5)->toDateString();
        $pagos = FinLancamento::query()->where('situacao', 'pago')->where('pago_em', '>=', $desde)->get();

        foreach ($pagos as $lancamento) {
            $mes = $lancamento->pago_em?->format('Y-m');
            // Fora da janela (ex.: pago no futuro por engano): ignora.
            if ($mes === null || ! isset($meses[$mes])) {
                continue;
            }
            // Receita soma nas entradas; despesa nas saídas. O estorno entra
            // negativo no próprio lado (desfaz o que o original somou).
            $valor = $lancamento->estorno_de_id === null ? $lancamento->valor_centavos : -$lancamento->valor_centavos;
            $lado = $lancamento->tipo === 'receita' ? 'entradas' : 'saidas';
            $meses[$mes][$lado] += $valor;
        }

        $resultado = [];
        foreach ($meses as $mes => $somas) {
            $resultado[] = ['mes' => $mes, 'entradas_centavos' => $somas['entradas'], 'saidas_centavos' => $somas['saidas']];
        }

        return $resultado;
    }

    /**
     * Anuidade do ano: quantos filiados ativos estão em dia, a % e a régua
     * de cobrança (os primeiros 5 em aberto, só com o registro).
     *
     * @return array<string, mixed>
     */
    private function anuidade(CarbonInterface $hoje): array
    {
        $ano = $hoje->year;
        $doAno = Anuidade::where('ano', $ano)->first();
        $ativos = Filiado::where('status', 'ativo');
        $total = (clone $ativos)->count();

        // Ano isento ou sem valor: ninguém deve; a régua fica vazia.
        if ($doAno === null || $doAno->isenta) {
            return ['ano' => $ano, 'isenta' => true, 'total' => $total, 'em_dia' => $total, 'percentual' => $total > 0 ? 100 : 0, 'recebido_centavos' => 0, 'em_aberto' => 0, 'regua' => []];
        }

        // Os filiados que têm pagamento VÁLIDO (não estornado) deste ano: uma
        // "subconsulta" que o banco resolve junto (só a coluna filiado_id).
        $pagaram = AnuidadePagamento::query()->validos()->where('ano', $ano)->select('filiado_id');

        $emDia = (clone $ativos)->whereIn('id', $pagaram)->count();

        // Porcentagem em dia, inteira (Gleyson): em dia × 100 ÷ total.
        // Se não houver filiados ativos, o percentual é 0% (e não divide por zero).
        $percentual = $total > 0 ? intdiv($emDia * 100, $total) : 0;

        // Régua: os ativos SEM pagamento válido do ano, em ordem de registro.
        $emAberto = (clone $ativos)->whereNotIn('id', $pagaram);
        // Dias em aberto, contados desde 01/01 do ano (a data de vencimento da anuidade ainda não foi definida).
        $dias = (int) Carbon::create($ano, 1, 1)->diffInDays($hoje);

        return [
            'ano' => $ano,
            'isenta' => false,
            'total' => $total,
            'em_dia' => $emDia,
            'percentual' => $percentual,
            // Quanto já entrou de anuidade no ano (receitas da categoria, pagas, sem estornos).
            'recebido_centavos' => (int) FinLancamento::query()
                ->where('origem', 'anuidade')->where('situacao', 'pago')
                ->whereYear('pago_em', $ano)->whereDoesntHave('estorno')
                ->sum('valor_centavos'),
            'em_aberto' => (clone $emAberto)->count(),
            'regua' => $emAberto->with('equipe:id,nome')->orderBy('registro')->limit(5)->get()
                ->map(fn (Filiado $filiado) => [
                    'id' => $filiado->id,
                    'registro' => $filiado->registro,
                    'equipe' => $filiado->equipe?->nome,
                    'dias' => $dias,
                ])->all(),
        ];
    }

    /**
     * As próximas 5 contas pendentes (de hoje em diante).
     *
     * @return list<array{id: int, vencimento: string, descricao: string, tipo: string, valor_centavos: int}>
     */
    private function proximosVencimentos(CarbonInterface $hoje): array
    {
        // array_values: devolve uma lista "limpa" (posições 0, 1, 2...).
        return array_values(FinLancamento::query()
            ->where('situacao', 'pendente')
            ->where('vencimento', '>=', $hoje->toDateString())
            ->orderBy('vencimento')->orderBy('id')
            ->limit(5)->get()
            ->map(fn (FinLancamento $lancamento) => [
                'id' => $lancamento->id,
                'vencimento' => $lancamento->vencimento->toDateString(),
                'descricao' => $lancamento->descricao,
                'tipo' => $lancamento->tipo,
                'valor_centavos' => $lancamento->valor_centavos,
            ])->all());
    }

    /**
     * Filiados: ativos, equipes ativas, convites pendentes e quantos por categoria.
     *
     * @return array<string, mixed>
     */
    private function filiados(): array
    {
        $ativos = Filiado::where('status', 'ativo');

        // Quantos por categoria (uma consulta só). Ex.: ['praticante' => 28, 'monitor' => 10].
        $porCategoria = (clone $ativos)->whereNotNull('categoria')
            ->selectRaw('categoria, count(*) as quantidade')->groupBy('categoria')
            ->pluck('quantidade', 'categoria');

        return [
            'ativos' => (clone $ativos)->count(),
            'equipes' => Equipe::where('status', StatusEquipe::Ativa)->count(),
            // Convite pendente = a pessoa ainda não criou a senha (e-mail não confirmado).
            'convites_pendentes' => (clone $ativos)->whereHas('usuario', fn (Builder $usuario) => $usuario->whereNull('email_verified_at'))->count(),
            // Todas as categorias, na ordem da hierarquia, mesmo com zero.
            'por_categoria' => array_map(fn (CategoriaFiliado $categoria) => [
                'categoria' => $categoria->rotulo(),
                'quantidade' => (int) ($porCategoria[$categoria->value] ?? 0),
            ], CategoriaFiliado::cases()),
        ];
    }
}
