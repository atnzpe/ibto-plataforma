<?php

/*
 * FILTROS ROBUSTOS DO FINANCEIRO (18/10/2026, pedido do Gleyson): "filtro por
 * papel e usuário, assim sei quanto preciso pagar a cada fotógrafo, instrutor;
 * por equipe, data, modalidade, código de inscrição, e-mail, valor, forma de
 * pagamento... analítico ou sintético, exportado em CSV ou PDF".
 *
 * Esta classe faz três coisas, usadas pela tela, pelo CSV e pela impressão:
 * 1) LÊ os filtros do endereço (o que vier errado é ignorado, sem quebrar);
 * 2) APLICA os filtros na consulta do livro-caixa;
 * 3) SOMA os cartões (recebido, a receber, pago, a pagar, saldo em caixa) e
 *    monta a visão SINTÉTICA (somada por pessoa, categoria, curso, equipe...).
 *
 * Valores sempre em CENTAVOS. A pessoa vem pelo vínculo do lançamento
 * (pessoa_id): na receita, quem pagou; no repasse, quem recebe.
 */

namespace App\Relatorios;

use App\Models\FinLancamento;
use App\Support\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FiltroFinanceiro
{
    /** As visões rápidas (botões): todos, a receber, a pagar e vencidos. */
    public const VISOES = ['todos', 'receber', 'pagar', 'vencidos'];

    /** As situações do filtro, com o rótulo da tela. 'vencido' = pendente com vencimento passado. */
    public const SITUACOES = ['pendente' => 'Pendente (no prazo)', 'vencido' => 'Vencido', 'pago' => 'Pago', 'cancelado' => 'Cancelado'];

    /** Os tipos de movimento (pela origem do lançamento), com o rótulo da tela. */
    public const MOVIMENTOS = [
        'matricula' => 'Inscrição em curso',
        'repasse' => 'Repasse ao instrutor (curso)',
        'assinatura' => 'Mensalidade (aulas regulares)',
        'repasse_assinatura' => 'Repasse ao instrutor (aulas regulares)',
        'pedido_foto' => 'Venda de fotos',
        'repasse_foto' => 'Repasse ao fotógrafo',
        'anuidade' => 'Anuidade',
        'manual' => 'Lançamento manual',
    ];

    /** As modalidades (de curso ou de programa), com o rótulo da tela. */
    public const MODALIDADES = ['presencial' => 'Presencial', 'online' => 'Online', 'hibrido' => 'Presencial e online'];

    /** Como a visão sintética pode agrupar, com o rótulo da tela. */
    public const AGRUPAMENTOS = ['pessoa' => 'Pessoa', 'categoria' => 'Categoria', 'movimento' => 'Tipo de movimento', 'curso' => 'Curso ou programa', 'equipe' => 'Equipe', 'mes' => 'Mês', 'forma' => 'Forma de pagamento'];

    /** O máximo de linhas na visão analítica (a tela fica leve; o CSV usa o mesmo limite alto). */
    public const LIMITE = 2000;

    /**
     * @param  Periodo  $periodo  De/Até.
     * @param  array<string, mixed>  $filtros  Os filtros já conferidos (ver doPedido).
     */
    public function __construct(
        public readonly Periodo $periodo,
        public readonly array $filtros,
    ) {}

    /**
     * Lê o período e os filtros do endereço. Valor fora da lista ou em formato
     * errado é simplesmente ignorado (a tela nunca quebra por um filtro).
     *
     * @param  Request  $request  Ex.: ?de=2026-10-01&ate=2026-10-31&papel=fotografo&modo=sintetico&agrupar=pessoa
     */
    public static function doPedido(Request $request): self
    {
        // Número inteiro positivo, ou null. Ex.: '12' → 12; 'abc' → null.
        $numero = fn (string $chave): ?int => ctype_digit((string) $request->query($chave, '')) ? (int) $request->query($chave) : null;
        // Valor em reais digitado ("150" ou "150,50") → centavos; vazio ou inválido → null.
        $reais = function (string $chave) use ($request): ?int {
            $texto = str_replace(['.', ','], ['', '.'], trim((string) $request->query($chave, '')));

            return is_numeric($texto) ? (int) round(((float) $texto) * 100) : null;
        };
        // Texto da lista, ou o padrão. Ex.: escolha('visao', VISOES, 'todos').
        $escolha = fn (string $chave, array $lista, ?string $padrao = null): ?string => in_array($request->query($chave), $lista, true) ? (string) $request->query($chave) : $padrao;

        return new self(Periodo::doPedido($request), [
            'visao' => $escolha('visao', self::VISOES, 'todos'),
            // Qual data o período usa: o vencimento (padrão) ou o dia do pagamento.
            'data_base' => $escolha('data_base', ['vencimento', 'pagamento'], 'vencimento'),
            'situacao' => $escolha('situacao', array_keys(self::SITUACOES)),
            'movimento' => $escolha('movimento', array_keys(self::MOVIMENTOS)),
            'modalidade' => $escolha('modalidade', array_keys(self::MODALIDADES)),
            'categoria' => $numero('categoria'),
            'forma' => $numero('forma'),
            'equipe' => $numero('equipe'),
            'curso' => $numero('curso'),
            'programa' => $numero('programa'),
            'pessoa_id' => $numero('pessoa_id'),
            // Busca da pessoa por parte do nome ou do e-mail (até 100 letras).
            'pessoa' => mb_substr(trim((string) $request->query('pessoa', '')), 0, 100),
            'papel' => preg_match('/^[a-z_]{2,30}$/', (string) $request->query('papel', '')) === 1 ? (string) $request->query('papel') : null,
            // Código: nº do lançamento, nº da matrícula/pedido/mensalidade, ou o código da página /pagar.
            'codigo' => mb_substr(trim((string) $request->query('codigo', '')), 0, 40),
            'valor_min' => $reais('valor_min'),
            'valor_max' => $reais('valor_max'),
            'modo' => $escolha('modo', ['analitico', 'sintetico'], 'analitico'),
            'agrupar' => $escolha('agrupar', array_keys(self::AGRUPAMENTOS), 'pessoa'),
        ]);
    }

    /**
     * A consulta do livro-caixa com TODOS os filtros aplicados.
     *
     * @return Builder<FinLancamento>
     */
    public function consulta(): Builder
    {
        $f = $this->filtros;
        $hoje = today()->toDateString();
        $consulta = FinLancamento::query()->with(['categoria', 'formaPagamento', 'pessoa', 'curso', 'programa', 'equipe'])->withExists('estorno');

        // Visão "vencidos": pendentes atrasados de QUALQUER mês (atrasado não pode sumir da lista).
        if ($f['visao'] === 'vencidos') {
            $consulta->vencidos();
        } else {
            // As outras visões usam o período, pela data escolhida (vencimento ou pagamento).
            $coluna = $f['data_base'] === 'pagamento' ? 'pago_em' : 'vencimento';
            $consulta->whereBetween($coluna, [$this->periodo->inicio->toDateString(), $this->periodo->fim->toDateString()]);
            // "receber" = só receitas; "pagar" = só despesas.
            if ($f['visao'] !== 'todos') {
                $consulta->where('tipo', $f['visao'] === 'receber' ? 'receita' : 'despesa');
            }
        }

        // Situação: "vencido" e "pendente" são calculados pela data de hoje.
        match ($f['situacao']) {
            'vencido' => $consulta->where('situacao', 'pendente')->whereDate('vencimento', '<', $hoje),
            'pendente' => $consulta->where('situacao', 'pendente')->whereDate('vencimento', '>=', $hoje),
            'pago', 'cancelado' => $consulta->where('situacao', $f['situacao']),
            default => null,
        };

        // Tipo de movimento pela origem; "manual" = sem origem (lançado à mão).
        if ($f['movimento'] === 'manual') {
            $consulta->whereNull('origem');
        } elseif ($f['movimento'] !== null) {
            $consulta->where('origem', $f['movimento']);
        }

        // Filtros simples por número (só aplica o que veio).
        foreach (['categoria' => 'categoria_id', 'forma' => 'forma_pagamento_id', 'equipe' => 'equipe_id', 'curso' => 'curso_id', 'programa' => 'programa_id', 'pessoa_id' => 'pessoa_id'] as $chave => $coluna) {
            if ($f[$chave] !== null) {
                $consulta->where($coluna, $f[$chave]);
            }
        }

        // Modalidade: a do curso ou a do programa ligado ao lançamento.
        if ($f['modalidade'] !== null) {
            $consulta->where(fn (Builder $ou) => $ou
                ->whereHas('curso', fn (Builder $curso) => $curso->where('modalidade', $f['modalidade']))
                ->orWhereHas('programa', fn (Builder $programa) => $programa->where('modalidade', $f['modalidade'])));
        }

        // Pessoa: parte do nome ou do e-mail.
        if ($f['pessoa'] !== '') {
            $texto = '%'.addcslashes($f['pessoa'], '%_\\').'%';
            $consulta->whereHas('pessoa', fn (Builder $pessoa) => $pessoa->where(fn (Builder $ou) => $ou->where('name', 'like', $texto)->orWhere('email', 'like', $texto)));
        }

        // Papel da pessoa. Ex.: 'fotografo' → só lançamentos de quem tem o papel Fotógrafo.
        if ($f['papel'] !== null) {
            $consulta->whereHas('pessoa.papeis', fn (Builder $papel) => $papel->where('slug', $f['papel']));
        }

        // Código: número (do lançamento ou da origem) ou o código da página de pagamento.
        if ($f['codigo'] !== '') {
            $this->filtrarCodigo($consulta, $f['codigo']);
        }

        // Faixa de valor (centavos).
        if ($f['valor_min'] !== null) {
            $consulta->where('valor_centavos', '>=', $f['valor_min']);
        }
        if ($f['valor_max'] !== null) {
            $consulta->where('valor_centavos', '<=', $f['valor_max']);
        }

        return $consulta->orderBy('vencimento')->orderBy('id');
    }

    /**
     * As linhas do filtro (até o limite), na ordem do vencimento.
     *
     * @return Collection<int, FinLancamento>
     */
    public function lancamentos(): Collection
    {
        return $this->consulta()->limit(self::LIMITE)->get();
    }

    /**
     * Os cartões do topo, somados SOBRE O FILTRO (mudou o filtro, mudam os números).
     * Estorno conta ao contrário do original (devolveu o dinheiro).
     *
     * @param  Collection<int, FinLancamento>  $lancamentos  As linhas do filtro.
     * @return array{recebido: int, a_receber: int, pago: int, a_pagar: int, saldo: int, vencidos: int, quantidade: int}
     */
    public static function cartoes(Collection $lancamentos): array
    {
        $hoje = today()->toDateString();
        $soma = ['recebido' => 0, 'a_receber' => 0, 'pago' => 0, 'a_pagar' => 0, 'vencidos' => 0];
        foreach ($lancamentos as $lancamento) {
            // Estorno: o dinheiro voltou (sinal invertido no que foi pago).
            $sinal = $lancamento->estorno_de_id !== null ? -1 : 1;
            if ($lancamento->situacao === 'pago') {
                $soma[$lancamento->tipo === 'receita' ? 'recebido' : 'pago'] += $sinal * $lancamento->valor_centavos;
            } elseif ($lancamento->situacao === 'pendente') {
                $soma[$lancamento->tipo === 'receita' ? 'a_receber' : 'a_pagar'] += $lancamento->valor_centavos;
                // Pendente com vencimento passado conta também como vencido.
                if ($lancamento->vencimento->toDateString() < $hoje) {
                    $soma['vencidos']++;
                }
            }
        }

        // Saldo em caixa no filtro: o que entrou menos o que saiu (só o que foi pago).
        return $soma + ['saldo' => $soma['recebido'] - $soma['pago'], 'quantidade' => $lancamentos->count()];
    }

    /**
     * A visão SINTÉTICA: os mesmos números somados por grupo (pessoa, categoria...).
     * A pessoa mais "pesada" primeiro (maior movimento).
     *
     * @param  Collection<int, FinLancamento>  $lancamentos  As linhas do filtro.
     * @return list<array{grupo: string, recebido: int, a_receber: int, pago: int, a_pagar: int, saldo: int, vencidos: int, quantidade: int}>
     */
    public function sintetico(Collection $lancamentos): array
    {
        $grupos = $lancamentos->groupBy(fn (FinLancamento $lancamento): string => $this->nomeDoGrupo($lancamento));

        return array_values($grupos
            ->map(fn (Collection $grupo, string $nome): array => ['grupo' => $nome] + self::cartoes($grupo))
            ->sortByDesc(fn (array $linha): int => abs($linha['recebido']) + abs($linha['pago']) + $linha['a_receber'] + $linha['a_pagar'])
            ->all());
    }

    /**
     * Uma linha da visão ANALÍTICA, pronta para a tela e o CSV.
     *
     * @return array<string, mixed>
     */
    public static function linha(FinLancamento $lancamento): array
    {
        // "vencido" não é gravado: é um pendente cujo vencimento já passou.
        $vencido = $lancamento->situacao === 'pendente' && $lancamento->vencimento->lt(today());

        return [
            'id' => $lancamento->id,
            'tipo' => $lancamento->tipo,
            'vencimento' => $lancamento->vencimento->toDateString(),
            'pago_em' => $lancamento->pago_em?->toDateString(),
            'descricao' => $lancamento->descricao,
            'categoria' => $lancamento->categoria?->nome,
            'movimento' => self::MOVIMENTOS[$lancamento->origem ?? 'manual'] ?? 'Outro',
            'pessoa' => $lancamento->pessoa?->name,
            'curso' => $lancamento->curso->titulo ?? $lancamento->programa?->titulo,
            'equipe' => $lancamento->equipe?->nome,
            'forma' => $lancamento->formaPagamento?->nome,
            'valor_centavos' => $lancamento->valor_centavos,
            'situacao' => $vencido ? 'vencido' : $lancamento->situacao,
            'estorno' => $lancamento->estorno_de_id !== null,
        ];
    }

    /**
     * Os filtros para devolver à tela (para os campos mostrarem o que está valendo).
     *
     * @return array<string, mixed>
     */
    public function paraTela(): array
    {
        $f = $this->filtros;

        return [...$this->periodo->paraTela(), ...$f,
            // Valores de volta em reais, como a pessoa digitou. Ex.: 15050 → '150,50'.
            'valor_min' => $f['valor_min'] !== null ? number_format($f['valor_min'] / 100, 2, ',', '') : '',
            'valor_max' => $f['valor_max'] !== null ? number_format($f['valor_max'] / 100, 2, ',', '') : '',
            'pessoa' => $f['pessoa'], 'codigo' => $f['codigo'],
        ];
    }

    /**
     * O nome do grupo de um lançamento, conforme o agrupamento escolhido.
     */
    private function nomeDoGrupo(FinLancamento $lancamento): string
    {
        return match ($this->filtros['agrupar']) {
            'categoria' => $lancamento->categoria->nome ?? 'Sem categoria',
            'movimento' => self::MOVIMENTOS[$lancamento->origem ?? 'manual'] ?? 'Outro',
            'curso' => $lancamento->curso->titulo ?? $lancamento->programa->titulo ?? 'Sem curso ou programa',
            'equipe' => $lancamento->equipe->nome ?? 'Sem equipe (IBTO)',
            'mes' => CarbonImmutable::parse($lancamento->vencimento)->format('Y-m'),
            'forma' => $lancamento->formaPagamento->nome ?? 'Sem forma (não pago)',
            // Pessoa (padrão): o nome e o nº da conta (dois "João" não se misturam).
            default => $lancamento->pessoa !== null ? "{$lancamento->pessoa->name} (nº {$lancamento->pessoa_id})" : 'Sem pessoa ligada',
        };
    }

    /**
     * Filtra pelo código digitado.
     * - Só números: o nº do lançamento OU o nº da origem (matrícula, pedido, mensalidade).
     * - Texto: o código da página de pagamento (/pagar/<código>) da matrícula, do pedido ou da mensalidade.
     *
     * @param  Builder<FinLancamento>  $consulta
     */
    private function filtrarCodigo(Builder $consulta, string $codigo): void
    {
        if (ctype_digit($codigo)) {
            $consulta->where(fn (Builder $ou) => $ou->where('id', (int) $codigo)->orWhere('origem_id', (int) $codigo));

            return;
        }

        $codigo = strtolower($codigo);
        // Acha a origem pelo código da página de pagamento, nas três tabelas que têm o código.
        $matricula = DB::table('curso_matriculas')->where('codigo_pagamento', $codigo)->value('id');
        $pedido = DB::table('pedidos_fotos')->where('codigo_pagamento', $codigo)->value('id');
        $mensalidade = DB::table('assinatura_pagamentos')->where('codigo_pagamento', $codigo)->value('id');

        $consulta->where(function (Builder $ou) use ($matricula, $pedido, $mensalidade): void {
            // Nenhum encontrado: nenhuma linha (whereRaw('1 = 0') = "nada passa").
            $ou->whereRaw('1 = 0');
            if ($matricula !== null) {
                $ou->orWhere(fn (Builder $q) => $q->whereIn('origem', ['matricula', 'repasse'])->where('origem_id', $matricula));
            }
            if ($pedido !== null) {
                $ou->orWhere(fn (Builder $q) => $q->whereIn('origem', ['pedido_foto', 'repasse_foto'])->where('origem_id', $pedido));
            }
            if ($mensalidade !== null) {
                $ou->orWhere(fn (Builder $q) => $q->whereIn('origem', ['assinatura', 'repasse_assinatura'])->where('origem_id', $mensalidade));
            }
        });
    }
}
