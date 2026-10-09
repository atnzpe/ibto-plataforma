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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LancamentoController extends Controller
{
    // As "visões" da lista: todos, só a receber, só a pagar, só os vencidos.
    private const VISOES = ['todos', 'receber', 'pagar', 'vencidos'];

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

        // Mês pedido no formato "2027-03"; se vier vazio ou errado, usa o mês de hoje.
        $mesTexto = (string) $request->query('mes', '');
        $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesTexto) === 1
            ? Carbon::createFromFormat('Y-m-d', $mesTexto.'-01')->startOfDay()
            : now()->startOfMonth();
        $inicio = $mes->copy()->startOfMonth()->toDateString();
        $fim = $mes->copy()->endOfMonth()->toDateString();

        // Consulta da lista, já com a categoria (evita uma consulta por linha).
        // withExists('estorno'): junto, o banco diz se cada um já foi estornado (estorno_exists).
        $consulta = FinLancamento::query()->with('categoria')->withExists('estorno')->orderBy('vencimento')->orderBy('id');

        if ($visao === 'vencidos') {
            // Vencidos de qualquer mês: quem está atrasado não pode sumir da lista.
            $consulta->vencidos();
        } else {
            // As outras visões mostram o que VENCE no mês escolhido.
            $consulta->whereBetween('vencimento', [$inicio, $fim]);
            // "receber" = só receitas; "pagar" = só despesas; "todos" = sem filtro.
            if ($visao !== 'todos') {
                $consulta->where('tipo', $visao === 'receber' ? 'receita' : 'despesa');
            }
        }

        // Totais do mês: o que falta receber e pagar (pendentes que vencem no mês).
        $pendentesDoMes = FinLancamento::query()
            ->where('situacao', 'pendente')
            ->whereBetween('vencimento', [$inicio, $fim]);

        return Inertia::render('financeiro/lancamentos/index', [
            'filtros' => ['mes' => $mes->format('Y-m'), 'visao' => $visao],
            'lancamentos' => $consulta->limit(500)->get()->map(fn (FinLancamento $lancamento) => $this->paraTela($lancamento)),
            'totais' => [
                'saldo_centavos' => $caixa->saldoDoMes($mes->year, $mes->month),
                'a_receber_centavos' => (int) (clone $pendentesDoMes)->where('tipo', 'receita')->sum('valor_centavos'),
                'a_pagar_centavos' => (int) (clone $pendentesDoMes)->where('tipo', 'despesa')->sum('valor_centavos'),
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
        try {
            $lancamento = $caixa->lancar($request->paraLivroCaixa());
        } catch (RegraFinanceira $erro) {
            // Regra recusou (ex.: categoria de outro tipo): volta com a mensagem e o código.
            return back()->withInput()->withErrors(['categoria_id' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        return to_route('financeiro.lancamentos.index', ['mes' => $lancamento->vencimento->format('Y-m')])
            ->with('sucesso', 'Lançamento cadastrado.');
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

        return to_route('financeiro.lancamentos.index', ['mes' => $lancamento->vencimento->format('Y-m')])
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
        return $this->executar(
            fn () => $caixa->pagar($lancamento, $request->string('pago_em')->toString(), $request->integer('forma_pagamento_id')),
            'Baixa registrada.',
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
        return $this->executar(
            fn () => $caixa->cancelar($lancamento, $request->string('motivo')->toString()),
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
     * Roda uma ação do livro-caixa e volta para a lista. Se a REGRA recusar
     * (ex.: estornar duas vezes), volta com a mensagem e o código, sem erro 500.
     *
     * @param  callable(): mixed  $acao  A ação. Ex.: fn () => $caixa->pagar(...)
     * @param  string  $sucesso  A mensagem quando dá certo. Ex.: 'Baixa registrada.'
     * @return RedirectResponse De volta à tela anterior.
     */
    private function executar(callable $acao, string $sucesso): RedirectResponse
    {
        try {
            $acao();
        } catch (RegraFinanceira $erro) {
            // "acao" é o nome do erro que a janela mostra. Ex.: "Este lançamento já foi estornado. (JA_ESTORNADO)"
            return back()->withErrors(['acao' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        return back()->with('sucesso', $sucesso);
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
        ];
    }
}
