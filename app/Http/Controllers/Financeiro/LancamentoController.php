<?php

/*
 * Telas do LIVRO-CAIXA (#59): lista de lançamentos com os filtros robustos e os
 * cartões que respondem ao filtro (18/10/2026; regra em App\Relatorios\FiltroFinanceiro),
 * CSV e impressão (PDF), e o formulário de novo lançamento. Quem grava é o App\Services\LivroCaixa
 * (regras + auditoria). Permissões nas rotas: "financeiro.ver" para olhar,
 * "financeiro.lancar" para lançar.
 */

namespace App\Http\Controllers\Financeiro;

use App\Exceptions\RegraFinanceira;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\BaixaRequest;
use App\Http\Requests\Financeiro\LancamentoRequest;
use App\Http\Requests\Financeiro\MotivoRequest;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Equipe;
use App\Models\FinCategoria;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\Papel;
use App\Models\Programa;
use App\Relatorios\AuditoriaRelatorio;
use App\Relatorios\FiltroFinanceiro;
use App\Services\LivroCaixa;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LancamentoController extends Controller
{
    /**
     * Título parcelado (grupo) → quantas parcelas pendentes, preenchido por
     * comParcelasEmAberto() antes de montar a lista.
     *
     * @var array<string, int>
     */
    private array $emAbertoPorTitulo = [];

    /**
     * A lista do livro-caixa com os FILTROS ROBUSTOS (18/10/2026) e os cartões
     * que respondem ao filtro: recebido, a receber, pago, a pagar e saldo.
     * Modo analítico (cada lançamento) ou sintético (somado por grupo).
     *
     * @param  Request  $request  Ex.: /financeiro?de=2026-10-01&ate=2026-10-31&papel=fotografo&modo=sintetico
     * @return Response A tela "financeiro/lancamentos/index".
     */
    public function index(Request $request): Response
    {
        $filtro = FiltroFinanceiro::doPedido($request);
        $lancamentos = $filtro->lancamentos();

        return Inertia::render('financeiro/lancamentos/index', [
            'filtros' => $filtro->paraTela(),
            // Analítico: cada lançamento, com as ações (baixa, cancelar, estornar) e os vínculos.
            'lancamentos' => $this->comParcelasEmAberto($lancamentos)->map(fn (FinLancamento $lancamento) => $this->paraTela($lancamento) + FiltroFinanceiro::linha($lancamento))->values(),
            // Sintético: as mesmas somas por grupo (pessoa, categoria, curso, equipe...).
            'sintetico' => $filtro->filtros['modo'] === 'sintetico' ? $filtro->sintetico($lancamentos) : [],
            'cartoes' => FiltroFinanceiro::cartoes($lancamentos),
            // Passou do limite: a tela avisa para refinar o filtro.
            'limite_atingido' => $lancamentos->count() >= FiltroFinanceiro::LIMITE,
            // Vencidos de QUALQUER mês (alerta fixo, independente do filtro).
            'vencidos_total' => FinLancamento::query()->vencidos()->count(),
            'pode_lancar' => $request->user()?->can('financeiro.lancar') ?? false,
            // Formas ativas, para a janela de baixa.
            'formas' => FinFormaPagamento::where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
            'opcoes' => $this->opcoesDosFiltros(),
        ]);
    }

    /**
     * O CSV do filtro atual: analítico (um lançamento por linha) ou sintético (um grupo por linha).
     *
     * @param  Request  $request  Os mesmos filtros da tela.
     */
    public function csv(Request $request): StreamedResponse
    {
        $filtro = FiltroFinanceiro::doPedido($request);
        [$colunas, $linhas] = $this->tabelaExportacao($filtro);
        Auditoria::registrar('RELATORIO_EXPORTADO', ['relatorio' => 'financeiro', 'formato' => 'csv', 'modo' => $filtro->filtros['modo'], ...$filtro->periodo->paraTela()]);

        return response()->streamDownload(function () use ($colunas, $linhas): void {
            $saida = fopen('php://output', 'w');
            // Não abriu a saída (raro): não há onde escrever.
            if ($saida === false) {
                return;
            }
            // BOM: o Excel lê os acentos certos.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, array_column($colunas, 'rotulo'), ';');
            foreach ($linhas as $linha) {
                fputcsv($saida, array_map(fn (array $coluna) => $coluna['tipo'] === 'dinheiro' ? number_format(((int) ($linha[$coluna['chave']] ?? 0)) / 100, 2, ',', '') : (string) ($linha[$coluna['chave']] ?? ''), $colunas), ';');
            }
            fclose($saida);
        }, 'financeiro-ibto.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A versão para impressão (PDF pelo navegador) do filtro atual, com a marca do IBTO.
     *
     * @param  Request  $request  Os mesmos filtros da tela.
     */
    public function imprimir(Request $request): Response
    {
        $filtro = FiltroFinanceiro::doPedido($request);
        [$colunas, $linhas] = $this->tabelaExportacao($filtro);
        Auditoria::registrar('RELATORIO_EXPORTADO', ['relatorio' => 'financeiro', 'formato' => 'impressao', 'modo' => $filtro->filtros['modo'], ...$filtro->periodo->paraTela()]);
        // Totais: soma as colunas de dinheiro e número; texto fica vazio.
        $totais = [];
        foreach ($colunas as $coluna) {
            $totais[$coluna['chave']] = in_array($coluna['tipo'], ['dinheiro', 'numero'], true) ? (int) collect($linhas)->sum($coluna['chave']) : null;
        }
        $modo = $filtro->filtros['modo'] === 'sintetico' ? 'sintético por '.mb_strtolower(FiltroFinanceiro::AGRUPAMENTOS[$filtro->filtros['agrupar']]) : 'analítico';

        return Inertia::render('relatorios/imprimir', [
            'relatorio' => ['chave' => 'financeiro', 'titulo' => "Financeiro ({$modo})", 'descricao' => ''],
            'colunas' => $colunas,
            'linhas' => $linhas,
            'totais' => $totais,
            'periodo' => $filtro->periodo->paraTela(),
            'gerado_em' => now()->format('d/m/Y H:i'),
            'gerado_por' => AuditoriaRelatorio::abreviado($request->user()->name).' (nº '.$request->user()->id.')',
        ]);
    }

    /**
     * As colunas e as linhas da exportação (CSV e PDF), no modo do filtro.
     *
     * @return array{0: list<array{chave: string, rotulo: string, tipo: string}>, 1: list<array<string, mixed>>}
     */
    private function tabelaExportacao(FiltroFinanceiro $filtro): array
    {
        $lancamentos = $filtro->lancamentos();
        // Sintético: um grupo por linha, com os cinco números.
        if ($filtro->filtros['modo'] === 'sintetico') {
            return [[
                ['chave' => 'grupo', 'rotulo' => FiltroFinanceiro::AGRUPAMENTOS[$filtro->filtros['agrupar']], 'tipo' => 'texto'],
                ['chave' => 'recebido', 'rotulo' => 'Recebido', 'tipo' => 'dinheiro'],
                ['chave' => 'a_receber', 'rotulo' => 'A receber', 'tipo' => 'dinheiro'],
                ['chave' => 'pago', 'rotulo' => 'Pago', 'tipo' => 'dinheiro'],
                ['chave' => 'a_pagar', 'rotulo' => 'A pagar', 'tipo' => 'dinheiro'],
                ['chave' => 'saldo', 'rotulo' => 'Saldo', 'tipo' => 'dinheiro'],
                ['chave' => 'quantidade', 'rotulo' => 'Lançamentos', 'tipo' => 'numero'],
            ], $filtro->sintetico($lancamentos)];
        }

        // Analítico: um lançamento por linha. Situação e tipo escritos por extenso.
        return [[
            ['chave' => 'id', 'rotulo' => 'Nº', 'tipo' => 'texto'],
            ['chave' => 'vencimento', 'rotulo' => 'Vencimento', 'tipo' => 'texto'],
            ['chave' => 'pago_em', 'rotulo' => 'Pago em', 'tipo' => 'texto'],
            ['chave' => 'tipo_texto', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
            ['chave' => 'descricao', 'rotulo' => 'Descrição', 'tipo' => 'texto'],
            ['chave' => 'categoria', 'rotulo' => 'Categoria', 'tipo' => 'texto'],
            ['chave' => 'movimento', 'rotulo' => 'Movimento', 'tipo' => 'texto'],
            ['chave' => 'pessoa', 'rotulo' => 'Pessoa', 'tipo' => 'texto'],
            ['chave' => 'curso', 'rotulo' => 'Curso/programa', 'tipo' => 'texto'],
            ['chave' => 'equipe', 'rotulo' => 'Equipe', 'tipo' => 'texto'],
            ['chave' => 'forma', 'rotulo' => 'Forma', 'tipo' => 'texto'],
            ['chave' => 'situacao_texto', 'rotulo' => 'Situação', 'tipo' => 'texto'],
            ['chave' => 'valor_centavos', 'rotulo' => 'Valor', 'tipo' => 'dinheiro'],
        ], array_values($lancamentos->map(function (FinLancamento $lancamento): array {
            $linha = FiltroFinanceiro::linha($lancamento);

            return $linha + [
                'tipo_texto' => $linha['tipo'] === 'receita' ? 'Receita' : 'Despesa',
                'situacao_texto' => (FiltroFinanceiro::SITUACOES[$linha['situacao']] ?? $linha['situacao']).($linha['estorno'] ? ' (estorno)' : ''),
            ];
        })->all())];
    }

    /**
     * As opções dos filtros (listas para os campos de escolha).
     *
     * @return array<string, mixed>
     */
    private function opcoesDosFiltros(): array
    {
        // Lista simples [{id, nome}] a partir de um mapa chave => rótulo.
        $lista = fn (array $mapa) => collect($mapa)->map(fn (string $nome, string $id) => ['id' => $id, 'nome' => $nome])->values();

        return [
            'categorias' => FinCategoria::orderBy('tipo')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'formas' => FinFormaPagamento::orderBy('nome')->get(['id', 'nome']),
            'equipes' => Equipe::orderBy('nome')->get(['id', 'nome']),
            'cursos' => Curso::orderBy('titulo')->get(['id', 'titulo as nome']),
            'programas' => Programa::orderBy('titulo')->get(['id', 'titulo as nome']),
            'papeis' => Papel::orderBy('nome')->get(['slug as id', 'nome']),
            'situacoes' => $lista(FiltroFinanceiro::SITUACOES),
            'movimentos' => $lista(FiltroFinanceiro::MOVIMENTOS),
            'modalidades' => $lista(FiltroFinanceiro::MODALIDADES),
            'agrupamentos' => $lista(FiltroFinanceiro::AGRUPAMENTOS),
        ];
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
