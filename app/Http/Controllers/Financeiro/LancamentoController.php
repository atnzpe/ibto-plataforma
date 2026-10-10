<?php

/*
 * Telas do LIVRO-CAIXA (#59): lista de lançamentos com filtros e totais do mês,
 * e o formulário de novo lançamento. Quem grava é o App\Services\LivroCaixa
 * (regras + auditoria). Permissões nas rotas: "financeiro.ver" para olhar,
 * "financeiro.lancar" para lançar.
 */

namespace App\Http\Controllers\Financeiro;

use App\Exceptions\RegraFinanceira;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\BaixaRequest;
use App\Http\Requests\Financeiro\LancamentoRequest;
use App\Http\Requests\Financeiro\MotivoRequest;
use App\Models\Equipe;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Services\LivroCaixa;
use App\Support\Dinheiro;
use App\Support\Periodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LancamentoController extends Controller
{
    // As "visões" da lista: todos, só a receber, só a pagar, só os vencidos.
    private const VISOES = ['todos', 'receber', 'pagar', 'vencidos'];

    /**
     * Título parcelado (grupo) → quantas parcelas pendentes, preenchido por
     * comParcelasEmAberto() antes de montar a lista.
     *
     * @var array<string, int>
     */
    private array $emAbertoPorTitulo = [];

    /**
     * Lista do mês escolhido, com os totais no topo.
     *
     * @param  Request  $request  Filtros do endereço. Ex.: /financeiro?mes=2027-03&visao=pagar
     * @param  LivroCaixa  $caixa  O livro-caixa (o Laravel entrega pronto), para o saldo do mês.
     * @return Response A tela "financeiro/lancamentos/index".
     */
    public function index(Request $request, LivroCaixa $caixa): Response
    {
        // Visão pedida; se vier algo fora da lista, usa "todos".
        $visao = in_array($request->query('visao'), self::VISOES, true) ? (string) $request->query('visao') : 'todos';

        // Período pedido ("de" e "até"; padrão = mês atual), pedido do Gleyson em
        // 10/10/2026. A regra fica em App\Support\Periodo, igual para todos os filtros.
        $periodo = Periodo::doPedido($request);
        $inicio = $periodo->inicio->toDateString();
        $fim = $periodo->fim->toDateString();

        // Consulta da lista, já com a categoria (evita uma consulta por linha).
        // withExists('estorno'): junto, o banco diz se cada um já foi estornado (estorno_exists).
        $consulta = FinLancamento::query()->with('categoria')->withExists('estorno')->orderBy('vencimento')->orderBy('id');

        if ($visao === 'vencidos') {
            // Vencidos de qualquer mês: quem está atrasado não pode sumir da lista.
            $consulta->vencidos();
        } else {
            // As outras visões mostram o que VENCE no período escolhido.
            $consulta->whereBetween('vencimento', [$inicio, $fim]);
            // "receber" = só receitas; "pagar" = só despesas; "todos" = sem filtro.
            if ($visao !== 'todos') {
                $consulta->where('tipo', $visao === 'receber' ? 'receita' : 'despesa');
            }
        }

        // Totais do período: o que falta receber e pagar (pendentes que vencem nele).
        $pendentesDoPeriodo = FinLancamento::query()
            ->where('situacao', 'pendente')
            ->whereBetween('vencimento', [$inicio, $fim]);

        return Inertia::render('financeiro/lancamentos/index', [
            'filtros' => [...$periodo->paraTela(), 'visao' => $visao],
            'lancamentos' => $this->comParcelasEmAberto($consulta->limit(500)->get())->map(fn (FinLancamento $lancamento) => $this->paraTela($lancamento)),
            'totais' => [
                'saldo_centavos' => $caixa->saldoEntre($inicio, $fim),
                'a_receber_centavos' => (int) (clone $pendentesDoPeriodo)->where('tipo', 'receita')->sum('valor_centavos'),
                'a_pagar_centavos' => (int) (clone $pendentesDoPeriodo)->where('tipo', 'despesa')->sum('valor_centavos'),
                'vencidos' => FinLancamento::query()->vencidos()->count(),
            ],
            'pode_lancar' => $request->user()?->can('financeiro.lancar') ?? false,
            // Formas ativas, para a janela de baixa.
            'formas' => FinFormaPagamento::where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /**
     * Formulário de novo lançamento.
     *
     * @return Response A tela "financeiro/lancamentos/formulario".
     */
    public function create(): Response
    {
        return Inertia::render('financeiro/lancamentos/formulario', [
            ...$this->opcoesDoFormulario(),
            // Sem lançamento: formulário vazio (novo).
            'lancamento' => null,
        ]);
    }

    /**
     * As listas de escolha do formulário (novo e edição).
     *
     * @return array{categorias: mixed, equipes: mixed}
     */
    private function opcoesDoFormulario(): array
    {
        return [
            // Só categorias ativas, separadas na tela pelo tipo.
            'categorias' => FinCategoria::where('ativa', true)->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'equipes' => Equipe::orderBy('nome')->get(['id', 'nome']),
            // Teto de parcelas, vindo do LivroCaixa (a tela não repete o número).
            'parcelas_maximo' => LivroCaixa::PARCELAS_MAXIMO,
        ];
    }

    /**
     * Grava o lançamento novo pelo LivroCaixa e volta para a lista do mês dele.
     *
     * @param  LancamentoRequest  $request  O formulário, já conferido.
     * @param  LivroCaixa  $caixa  O livro-caixa (regras + auditoria).
     * @return RedirectResponse Lista do mês do vencimento, com a mensagem; ou de
     *                          volta ao formulário com o erro da regra.
     */
    public function store(LancamentoRequest $request, LivroCaixa $caixa): RedirectResponse
    {
        // Quantas parcelas (vazio = 1, à vista).
        $parcelas = $request->filled('parcelas') ? $request->integer('parcelas') : 1;

        try {
            // À vista: um lançamento. Parcelado: N lançamentos, um por mês; a
            // lista abre no mês da 1ª parcela.
            $lancamento = $parcelas > 1
                ? $caixa->lancarParcelado($request->paraLivroCaixa(), $parcelas)[0]
                : $caixa->lancar($request->paraLivroCaixa());
        } catch (RegraFinanceira $erro) {
            // Regra recusou (ex.: categoria de outro tipo): volta com a mensagem e o código.
            return back()->withInput()->withErrors(['categoria_id' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        return to_route('financeiro.lancamentos.index', $this->mesDe($lancamento))
            ->with('sucesso', $parcelas > 1 ? "Lançamento cadastrado em {$parcelas} parcelas." : 'Lançamento cadastrado.');
    }

    /**
     * Formulário preenchido para EDITAR um lançamento pendente.
     *
     * @param  FinLancamento  $lancamento  O lançamento do endereço (ex.: /financeiro/lancamentos/7/editar).
     * @return Response|RedirectResponse O formulário; ou de volta à lista, se não estiver pendente.
     */
    public function edit(FinLancamento $lancamento): Response|RedirectResponse
    {
        // Pago ou cancelado não abre para edição: volta com o aviso e o código.
        if ($lancamento->situacao !== 'pendente') {
            return to_route('financeiro.lancamentos.index')
                ->withErrors(['acao' => 'Só um lançamento pendente pode ser editado. (LANCAMENTO_NAO_PENDENTE)']);
        }

        return Inertia::render('financeiro/lancamentos/formulario', [
            ...$this->opcoesDoFormulario(),
            'lancamento' => [
                'id' => $lancamento->id,
                'tipo' => $lancamento->tipo,
                'categoria_id' => $lancamento->categoria_id,
                'descricao' => $lancamento->descricao,
                // Valor no formato do campo. Ex.: 1250 → '12,50'.
                'valor' => Dinheiro::paraCampo($lancamento->valor_centavos),
                'vencimento' => $lancamento->vencimento->toDateString(),
                'equipe_id' => $lancamento->equipe_id,
            ],
        ]);
    }

    /**
     * Grava a edição pelo LivroCaixa e volta para a lista do mês do vencimento.
     *
     * @param  LancamentoRequest  $request  O formulário, já conferido.
     * @param  FinLancamento  $lancamento  O lançamento que está sendo editado.
     * @param  LivroCaixa  $caixa  O livro-caixa (regras + auditoria com antes e depois).
     * @return RedirectResponse A lista, com a mensagem; ou de volta ao formulário com o erro da regra.
     */
    public function update(LancamentoRequest $request, FinLancamento $lancamento, LivroCaixa $caixa): RedirectResponse
    {
        try {
            $caixa->editar($lancamento, $request->paraLivroCaixa());
        } catch (RegraFinanceira $erro) {
            return back()->withInput()->withErrors(['categoria_id' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        return to_route('financeiro.lancamentos.index', $this->mesDe($lancamento))
            ->with('sucesso', 'Lançamento atualizado.');
    }

    /**
     * Dá BAIXA: marca o lançamento pendente como pago (dia e forma).
     *
     * @param  BaixaRequest  $request  O formulário, já conferido. Ex.: pago_em '2027-03-15', forma = PIX.
     * @param  FinLancamento  $lancamento  O lançamento do endereço (ex.: /financeiro/lancamentos/7/pagar).
     * @param  LivroCaixa  $caixa  O livro-caixa (regras + auditoria).
     * @return RedirectResponse De volta à lista, com a mensagem de sucesso ou o erro da regra.
     */
    public function pagar(BaixaRequest $request, FinLancamento $lancamento, LivroCaixa $caixa): RedirectResponse
    {
        // Valor pago: vazio = o valor todo; texto que não vira número = 0 (o
        // LivroCaixa recusa com VALOR_PAGO_INVALIDO, sem erro 500).
        $valorPago = $request->filled('valor_pago')
            ? (Dinheiro::paraCentavos((string) $request->input('valor_pago')) ?? 0)
            : null;

        // Mensagem diferente quando sobra saldo (baixa parcial).
        $parcial = $valorPago !== null && $valorPago < $lancamento->valor_centavos;

        return $this->executar(
            fn () => $caixa->pagar($lancamento, $request->string('pago_em')->toString(), $request->integer('forma_pagamento_id'), $valorPago),
            $parcial ? 'Baixa parcial registrada; o saldo ficou em aberto.' : 'Baixa registrada.',
        );
    }

    /**
     * CANCELA um lançamento pendente, com motivo.
     *
     * @param  MotivoRequest  $request  O formulário, já conferido.
     * @param  FinLancamento  $lancamento  O lançamento do endereço.
     * @param  LivroCaixa  $caixa  O livro-caixa.
     * @return RedirectResponse De volta à lista.
     */
    public function cancelar(MotivoRequest $request, FinLancamento $lancamento, LivroCaixa $caixa): RedirectResponse
    {
        $motivo = $request->string('motivo')->toString();

        // "todas": cancela as parcelas em aberto do título inteiro (pedido do
        // Gleyson, 10/10/2026). Senão, só este lançamento.
        if ($request->input('escopo') === 'todas') {
            $quantas = 0;

            return $this->executar(
                function () use ($caixa, $lancamento, $motivo, &$quantas): void {
                    $quantas = $caixa->cancelarParcelas($lancamento, $motivo);
                },
                // "function () use (&$quantas)", e não "fn () =>": a função curta
                // copiaria $quantas ainda valendo 0; o "&" lê o valor depois da ação.
                function () use (&$quantas): string {
                    return "{$quantas} parcela(s) em aberto do título cancelada(s).";
                },
            );
        }

        return $this->executar(
            fn () => $caixa->cancelar($lancamento, $motivo),
            'Lançamento cancelado.',
        );
    }

    /**
     * ESTORNA um lançamento pago, com motivo e dia (hoje, se não vier).
     *
     * @param  MotivoRequest  $request  O formulário, já conferido.
     * @param  FinLancamento  $lancamento  O lançamento pago do endereço.
     * @param  LivroCaixa  $caixa  O livro-caixa.
     * @return RedirectResponse De volta à lista.
     */
    public function estornar(MotivoRequest $request, FinLancamento $lancamento, LivroCaixa $caixa): RedirectResponse
    {
        // Dia do estorno: o informado, ou hoje.
        $data = $request->filled('data') ? $request->string('data')->toString() : today()->toDateString();

        return $this->executar(
            fn () => $caixa->estornar($lancamento, $request->string('motivo')->toString(), $data),
            'Estorno registrado.',
        );
    }

    /**
     * Filtro "de / até" do mês do vencimento de um lançamento, para voltar à
     * lista mostrando onde ele está. Ex.: vence em 15/10/2026 → de 01/10 até 31/10.
     *
     * @return array{de: string, ate: string}
     */
    private function mesDe(FinLancamento $lancamento): array
    {
        return [
            'de' => $lancamento->vencimento->copy()->startOfMonth()->toDateString(),
            'ate' => $lancamento->vencimento->copy()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Antes de montar a lista: conta, numa consulta só, quantas parcelas em
     * aberto cada título parcelado da lista tem (guarda em $emAbertoPorTitulo).
     *
     * @param  Collection<int, FinLancamento>  $lancamentos  As linhas da lista.
     * @return Collection<int, FinLancamento> As mesmas linhas (para encadear).
     */
    private function comParcelasEmAberto(Collection $lancamentos): Collection
    {
        // Os títulos parcelados que aparecem na lista. Ex.: ['9f1c...', 'a2b7...'].
        $grupos = $lancamentos->pluck('grupo_parcelas')->filter()->unique()->values();

        // Grupo → quantas pendentes. Ex.: ['9f1c...' => 8].
        $this->emAbertoPorTitulo = $grupos->isEmpty() ? [] : FinLancamento::query()
            ->whereIn('grupo_parcelas', $grupos)
            ->where('situacao', 'pendente')
            ->selectRaw('grupo_parcelas, count(*) as quantidade')
            ->groupBy('grupo_parcelas')
            ->pluck('quantidade', 'grupo_parcelas')
            ->map(fn ($quantidade) => (int) $quantidade)
            ->all();

        return $lancamentos;
    }

    /**
     * Roda uma ação do livro-caixa e volta para a lista. Se a REGRA recusar
     * (ex.: estornar duas vezes), volta com a mensagem e o código, sem erro 500.
     *
     * @param  callable(): mixed  $acao  A ação. Ex.: fn () => $caixa->pagar(...)
     * @param  string|callable(): string  $sucesso  A mensagem quando dá certo. Ex.: 'Baixa registrada.'
     *                                              Pode ser uma função, montada DEPOIS da ação (ex.: com a quantidade cancelada).
     * @return RedirectResponse De volta à tela anterior.
     */
    private function executar(callable $acao, string|callable $sucesso): RedirectResponse
    {
        try {
            $acao();
        } catch (RegraFinanceira $erro) {
            // "acao" é o nome do erro que a janela mostra. Ex.: "Este lançamento já foi estornado. (JA_ESTORNADO)"
            return back()->withErrors(['acao' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        // Mensagem pronta, ou montada agora (depois da ação).
        return back()->with('sucesso', is_callable($sucesso) ? $sucesso() : $sucesso);
    }

    /**
     * Só o que a tela usa.
     *
     * @return array<string, mixed>
     */
    private function paraTela(FinLancamento $lancamento): array
    {
        // "vencido" não é gravado no banco: é um pendente cujo vencimento já passou.
        $vencido = $lancamento->situacao === 'pendente' && $lancamento->vencimento->lt(today());

        return [
            'id' => $lancamento->id,
            'tipo' => $lancamento->tipo,
            'categoria' => $lancamento->categoria?->nome,
            'descricao' => $lancamento->descricao,
            'valor_centavos' => $lancamento->valor_centavos,
            'vencimento' => $lancamento->vencimento->toDateString(),
            'pago_em' => $lancamento->pago_em?->toDateString(),
            'situacao' => $vencido ? 'vencido' : $lancamento->situacao,
            'estorno' => $lancamento->estorno_de_id !== null,
            // true se este lançamento JÁ tem um estorno (vem do withExists da consulta).
            'estornado' => (bool) ($lancamento->getAttribute('estorno_exists') ?? false),
            // Parcela "3 de 10" (vazio = à vista) e quantas parcelas do título ainda
            // estão em aberto (para a janela de cancelar oferecer "todas").
            'parcela_numero' => $lancamento->parcela_numero,
            'parcela_total' => $lancamento->parcela_total,
            'parcelas_em_aberto' => $lancamento->grupo_parcelas !== null
                ? ($this->emAbertoPorTitulo[$lancamento->grupo_parcelas] ?? 0)
                : 0,
        ];
    }
}
