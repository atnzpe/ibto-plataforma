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
use App\Http\Requests\Financeiro\LancamentoRequest;
use App\Models\Equipe;
use App\Models\FinCategoria;
use App\Models\FinLancamento;
use App\Services\LivroCaixa;
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
        $consulta = FinLancamento::query()->with('categoria')->orderBy('vencimento')->orderBy('id');

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
            // Só categorias ativas, separadas na tela pelo tipo.
            'categorias' => FinCategoria::where('ativa', true)->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'equipes' => Equipe::orderBy('nome')->get(['id', 'nome']),
        ]);
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
        ];
    }
}
