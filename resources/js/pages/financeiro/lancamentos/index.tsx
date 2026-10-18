/**
 * Tela do LIVRO-CAIXA (#59) com os FILTROS ROBUSTOS (18/10/2026): período (pelo
 * vencimento ou pelo pagamento), visão, situação, tipo de movimento, categoria,
 * forma, pessoa (nome ou e-mail), papel (instrutor, fotógrafo...), equipe,
 * curso, programa, modalidade, código e faixa de valor. Os cartões do topo
 * (recebido, a receber, pago, a pagar e saldo) somam SÓ o que está no filtro.
 * Modo analítico (cada lançamento) ou sintético (somado por grupo), com CSV e
 * PDF. Só entra quem tem "financeiro.ver"; lançar pede "financeiro.lancar".
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
// Botões e janelas de baixa, cancelar e estornar (#59).
import AcoesLancamento from '@/components/financeiro/acoes-lancamento';
// Filtro "De / Até" (o mesmo em todas as telas com data).
import FiltroPeriodo from '@/components/filtro-periodo';
// Tabela da visão sintética (a mesma dos relatórios: vira cartões no celular).
import TabelaRelatorio from '@/components/relatorios/tabela-relatorio';
import type { ColunaRelatorio } from '@/components/relatorios/tabela-relatorio';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';
// Endereço do histórico de toda movimentação (#59).
import { historico } from '@/routes/financeiro';
// Cadastros de apoio do financeiro (#59).
import { index as categorias } from '@/routes/financeiro/categorias';
// "rotaFormas" (e não "formas"): "formas" já é a lista de formas da baixa, que viria por cima.
import { index as rotaFormas } from '@/routes/financeiro/formas';
import { create, index } from '@/routes/financeiro/lancamentos';
import type {
    CartoesFinanceiro,
    FiltrosFinanceiro,
    Lancamento,
    LinhaSintetica,
    OpcaoFiltro,
} from '@/types/financeiro';

/**
 * Um valor de filtro como texto para o endereço (número, texto ou sim/não);
 * qualquer outra coisa (vazio, null) vira ''.
 *
 * @param valor Ex.: 3, 'fotografo', null.
 * @returns Ex.: '3', 'fotografo', ''.
 */
function comoTexto(valor: unknown): string {
    return typeof valor === 'string' ||
        typeof valor === 'number' ||
        typeof valor === 'boolean'
        ? String(valor)
        : '';
}

/**
 * Lê um campo de texto do formulário, sem espaços nas pontas ('' se não houver).
 *
 * @param dados Os dados do formulário enviado.
 * @param nome O nome do campo. Ex.: 'pessoa'
 * @returns Ex.: 'Marcelo'
 */
function campoDoFormulario(dados: FormData, nome: string): string {
    const valor = dados.get(nome);

    return typeof valor === 'string' ? valor.trim() : '';
}

// As colunas da visão sintética (o nome do grupo e os cinco números).
const COLUNAS_SINTETICO: ColunaRelatorio[] = [
    { chave: 'grupo', rotulo: 'Grupo', tipo: 'texto' },
    { chave: 'recebido', rotulo: 'Recebido', tipo: 'dinheiro' },
    { chave: 'a_receber', rotulo: 'A receber', tipo: 'dinheiro' },
    { chave: 'pago', rotulo: 'Pago', tipo: 'dinheiro' },
    { chave: 'a_pagar', rotulo: 'A pagar', tipo: 'dinheiro' },
    { chave: 'saldo', rotulo: 'Saldo', tipo: 'dinheiro' },
    { chave: 'quantidade', rotulo: 'Lançamentos', tipo: 'numero' },
];

// Os filtros "avançados" (os que ficam na caixa que abre e fecha).
const AVANCADOS: (keyof FiltrosFinanceiro)[] = [
    'situacao',
    'movimento',
    'categoria',
    'forma',
    'papel',
    'pessoa',
    'equipe',
    'curso',
    'programa',
    'modalidade',
    'codigo',
    'valor_min',
    'valor_max',
];

// Botões de visão: valor do filtro → texto do botão.
const VISOES: { valor: FiltrosFinanceiro['visao']; texto: string }[] = [
    { valor: 'todos', texto: 'Todos' },
    { valor: 'receber', texto: 'A receber' },
    { valor: 'pagar', texto: 'A pagar' },
    { valor: 'vencidos', texto: 'Vencidos' },
];

// Situação → texto escrito (a cor só reforça; a informação está no texto).
const SITUACOES: Record<
    Lancamento['situacao'],
    { texto: string; classe: string }
> = {
    pendente: { texto: 'Pendente', classe: 'text-foreground' },
    vencido: {
        texto: 'Vencido',
        classe: 'font-medium text-red-700 dark:text-red-400',
    },
    pago: { texto: 'Pago', classe: 'text-green-700 dark:text-green-400' },
    cancelado: {
        texto: 'Cancelado',
        classe: 'text-muted-foreground line-through',
    },
};

/**
 * Data do banco ('2027-03-31') no formato brasileiro ('31/03/2027').
 *
 * @param data Ex.: '2027-03-31'
 * @returns Ex.: '31/03/2027'
 */
function dataBr(data: string): string {
    const [ano, mes, dia] = data.split('-');

    return `${dia}/${mes}/${ano}`;
}

/**
 * @param lancamentos As linhas do filtro atual (visão analítica).
 * @param sintetico As somas por grupo (só no modo sintético).
 * @param cartoes Os cartões do topo, somados sobre o filtro.
 * @param limite_atingido true = o filtro passou de 2000 linhas (refinar).
 * @param vencidos_total Vencidos de qualquer mês (alerta fixo).
 * @param filtros Os filtros aplicados.
 * @param pode_lancar true se a pessoa pode lançar, dar baixa, cancelar e estornar.
 * @param formas As formas de pagamento ativas, para a janela de baixa.
 * @param opcoes As listas dos campos de filtro.
 * @returns A tela do livro-caixa.
 */
export default function LancamentosIndex({
    lancamentos,
    sintetico,
    cartoes,
    limite_atingido,
    vencidos_total,
    filtros,
    pode_lancar,
    formas,
    opcoes,
}: {
    lancamentos: Lancamento[];
    sintetico: LinhaSintetica[];
    cartoes: CartoesFinanceiro;
    limite_atingido: boolean;
    vencidos_total: number;
    filtros: FiltrosFinanceiro;
    pode_lancar: boolean;
    formas: { id: number; nome: string }[];
    opcoes: {
        categorias: (OpcaoFiltro & { tipo: string })[];
        formas: OpcaoFiltro[];
        equipes: OpcaoFiltro[];
        cursos: OpcaoFiltro[];
        programas: OpcaoFiltro[];
        papeis: OpcaoFiltro[];
        situacoes: OpcaoFiltro[];
        movimentos: OpcaoFiltro[];
        modalidades: OpcaoFiltro[];
        agrupamentos: OpcaoFiltro[];
    };
}) {
    // Mensagem da última ação (ex.: 'Lançamento cadastrado.'), ou null.
    // errors.acao: aviso de regra que volta para a lista (ex.: tentar editar um pago).
    const { sucesso, errors } = usePage().props;
    const avisoDeRegra = (errors as Record<string, string> | undefined)?.acao;

    /**
     * Os filtros como texto para o endereço, sem os vazios (endereço limpo e fácil de compartilhar).
     *
     * @param novo O que mudou. Ex.: { papel: 'fotografo' }
     * @returns Ex.: { de: '2026-10-01', ate: '2026-10-31', papel: 'fotografo' }
     */
    const paraEndereco = (
        novo: Partial<Record<keyof FiltrosFinanceiro, unknown>> = {},
    ): Record<string, string> =>
        Object.fromEntries(
            Object.entries({ ...filtros, ...novo })
                .map(([chave, valor]) => [chave, comoTexto(valor)])
                .filter(([, valor]) => valor !== ''),
        );

    /**
     * Recarrega a lista com um ou mais filtros trocados, mantendo os outros.
     *
     * @param novo O que mudou. Ex.: { visao: 'pagar' }
     */
    const filtrar = (novo: Partial<Record<keyof FiltrosFinanceiro, unknown>>) =>
        router.get(index().url, paraEndereco(novo), { preserveScroll: true });

    // Os endereços do CSV e da impressão, com os MESMOS filtros da tela.
    const busca = new URLSearchParams(paraEndereco()).toString();

    // Algum filtro avançado ligado? Então a caixa já abre aberta.
    const temAvancado = AVANCADOS.some(
        (chave) => filtros[chave] !== null && filtros[chave] !== '',
    );

    // Os 5 cartões do topo (somam SÓ o que está no filtro).
    const quadros = [
        {
            titulo: 'Recebido',
            valor: formatarReais(cartoes.recebido),
            dica: 'receitas pagas',
        },
        {
            titulo: 'A receber',
            valor: formatarReais(cartoes.a_receber),
            dica: 'receitas pendentes',
        },
        {
            titulo: 'Pago',
            valor: formatarReais(cartoes.pago),
            dica: 'despesas pagas (repasses, contas)',
        },
        {
            titulo: 'A pagar',
            valor: formatarReais(cartoes.a_pagar),
            dica: 'despesas pendentes',
        },
        {
            titulo: 'Saldo em caixa',
            valor: formatarReais(cartoes.saldo),
            dica: 'recebido − pago, no filtro',
        },
    ];

    // Visual dos campos de filtro (44 px de altura).
    const campo =
        'h-11 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm';

    /**
     * Um campo de escolha de filtro (com "Todos" para tirar o filtro).
     *
     * @param chave Qual filtro. Ex.: 'papel'
     * @param rotulo O texto do campo. Ex.: 'Papel da pessoa'
     * @param lista As opções.
     */
    const escolha = (
        chave: keyof FiltrosFinanceiro,
        rotulo: string,
        lista: OpcaoFiltro[],
    ) => (
        <label className="grid gap-1 text-xs" key={chave}>
            {rotulo}
            <select
                value={(filtros[chave] as string | number | null) ?? ''}
                onChange={(evento) => filtrar({ [chave]: evento.target.value })}
                className={campo}
            >
                <option value="">Todos</option>
                {lista.map((opcao) => (
                    <option key={opcao.id} value={opcao.id}>
                        {opcao.nome}
                    </option>
                ))}
            </select>
        </label>
    );

    return (
        <>
            <Head title="Financeiro" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Financeiro
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Livro-caixa: contas a receber e a pagar. Lançamento
                            pago não se altera; corrige-se com estorno.
                        </p>
                        {/* Atalhos para os cadastros de apoio (só quem pode lançar). */}
                        {pode_lancar && (
                            <p className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <Link
                                    href={categorias()}
                                    className="underline underline-offset-4"
                                >
                                    Categorias
                                </Link>
                                <Link
                                    href={rotaFormas()}
                                    className="underline underline-offset-4"
                                >
                                    Formas de pagamento
                                </Link>
                            </p>
                        )}
                    </div>

                    {/* Botões do topo: no celular, 2 colunas iguais; na tela larga, lado a lado. */}
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        {/* Exportar o filtro atual (o mesmo da tela). */}
                        <Button asChild variant="outline">
                            <a href={`/financeiro/exportar/csv?${busca}`}>
                                Exportar CSV
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={`/financeiro/exportar/imprimir?${busca}`}
                                target="_blank"
                                rel="noopener"
                            >
                                PDF / imprimir
                            </a>
                        </Button>
                        {/* Histórico: todos que veem o financeiro podem consultar. */}
                        <Button
                            asChild
                            variant="outline"
                            // Sozinho (sem o botão de lançar), ocupa as 2 colunas.
                            className={pode_lancar ? '' : 'col-span-2'}
                        >
                            <Link href={historico()}>Histórico</Link>
                        </Button>
                        {/* Só quem pode lançar vê o botão (o servidor também confere). */}
                        {pode_lancar && (
                            <Button asChild>
                                <Link href={create()}>Novo lançamento</Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Aviso de sucesso, anunciado pelo leitor de tela sem tirar o foco. */}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* Aviso de regra (com código), anunciado na hora pelo leitor de tela. */}
                {avisoDeRegra && (
                    <p
                        role="alert"
                        className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                    >
                        {avisoDeRegra}
                    </p>
                )}

                {/* Vencidos de QUALQUER mês: alerta fixo (não some com o filtro). */}
                {vencidos_total > 0 && (
                    <p
                        role="status"
                        className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                    >
                        {vencidos_total} lançamento(s) vencido(s) no total.{' '}
                        <button
                            type="button"
                            className="min-h-11 underline underline-offset-4"
                            onClick={() => filtrar({ visao: 'vencidos' })}
                        >
                            Ver os vencidos
                        </button>
                    </p>
                )}

                {/* Os cartões: somam SÓ o que está no filtro. */}
                <dl
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                    data-tour="cartoes-financeiro"
                >
                    {quadros.map((quadro) => (
                        <div
                            key={quadro.titulo}
                            className="rounded-md border border-border p-4"
                        >
                            <dt className="text-sm text-muted-foreground">
                                {quadro.titulo}
                            </dt>
                            <dd className="text-lg font-semibold [overflow-wrap:anywhere]">
                                {quadro.valor}
                            </dd>
                            <dd className="text-xs text-muted-foreground">
                                {quadro.dica}
                            </dd>
                        </div>
                    ))}
                </dl>
                <p className="-mt-3 text-xs text-muted-foreground">
                    {cartoes.quantidade} lançamento(s) no filtro
                    {cartoes.vencidos > 0 &&
                        ` · ${cartoes.vencidos} vencido(s)`}
                    .
                </p>

                {/* Filtros principais: período (e qual data conta) e visão. */}
                <div className="flex flex-wrap items-end gap-4">
                    <FiltroPeriodo
                        prefixo="livro-caixa"
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(periodo) => filtrar(periodo)}
                    />
                    <label className="grid gap-1 text-xs">
                        O período conta pela data de
                        <select
                            value={filtros.data_base}
                            onChange={(evento) =>
                                filtrar({ data_base: evento.target.value })
                            }
                            className={campo}
                        >
                            <option value="vencimento">Vencimento</option>
                            <option value="pagamento">Pagamento</option>
                        </select>
                    </label>

                    <div
                        role="group"
                        // Celular: 2 colunas iguais (2 x 2). Tela maior: lado a lado.
                        aria-label="Visão"
                        className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap"
                    >
                        {VISOES.map((visao) => (
                            <Button
                                key={visao.valor}
                                type="button"
                                variant={
                                    filtros.visao === visao.valor
                                        ? 'default'
                                        : 'outline'
                                }
                                // aria-pressed: o leitor de tela diz qual visão está ligada.
                                aria-pressed={filtros.visao === visao.valor}
                                onClick={() => filtrar({ visao: visao.valor })}
                            >
                                {visao.texto}
                            </Button>
                        ))}
                    </div>
                </div>

                {/* Filtros avançados: abrem e fecham; já abertos quando algum está ligado. */}
                <details
                    open={temAvancado}
                    className="rounded-md border border-border p-3"
                    data-tour="filtros-financeiro"
                >
                    <summary className="min-h-11 cursor-pointer py-2 text-sm font-medium">
                        Filtros avançados (pessoa, papel, equipe, curso, código,
                        valor...)
                    </summary>
                    <div className="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {escolha('situacao', 'Situação', opcoes.situacoes)}
                        {escolha(
                            'movimento',
                            'Tipo de movimento',
                            opcoes.movimentos,
                        )}
                        {escolha('categoria', 'Categoria', opcoes.categorias)}
                        {escolha('forma', 'Forma de pagamento', opcoes.formas)}
                        {escolha('papel', 'Papel da pessoa', opcoes.papeis)}
                        {escolha('equipe', 'Equipe', opcoes.equipes)}
                        {escolha('curso', 'Curso', opcoes.cursos)}
                        {escolha(
                            'programa',
                            'Programa (aulas regulares)',
                            opcoes.programas,
                        )}
                        {escolha(
                            'modalidade',
                            'Modalidade',
                            opcoes.modalidades,
                        )}
                    </div>
                    {/* Campos de texto: só filtram ao clicar em "Aplicar" (ou Enter). */}
                    <form
                        key={`${filtros.pessoa}|${filtros.codigo}|${filtros.valor_min}|${filtros.valor_max}`}
                        className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            const dados = new FormData(evento.currentTarget);
                            filtrar({
                                pessoa: campoDoFormulario(dados, 'pessoa'),
                                codigo: campoDoFormulario(dados, 'codigo'),
                                valor_min: campoDoFormulario(
                                    dados,
                                    'valor_min',
                                ),
                                valor_max: campoDoFormulario(
                                    dados,
                                    'valor_max',
                                ),
                            });
                        }}
                    >
                        <label className="grid gap-1 text-xs">
                            Pessoa (nome ou e-mail)
                            <input
                                name="pessoa"
                                type="search"
                                maxLength={100}
                                defaultValue={filtros.pessoa}
                                placeholder="Ex.: Marcelo ou @gmail"
                                className={campo}
                            />
                        </label>
                        <label className="grid gap-1 text-xs">
                            Código (nº do lançamento, da matrícula ou do
                            pagamento)
                            <input
                                name="codigo"
                                type="search"
                                maxLength={40}
                                defaultValue={filtros.codigo}
                                placeholder="Ex.: 42"
                                className={campo}
                            />
                        </label>
                        <label className="grid gap-1 text-xs">
                            Valor mínimo (R$)
                            <input
                                name="valor_min"
                                inputMode="decimal"
                                defaultValue={filtros.valor_min}
                                placeholder="Ex.: 50,00"
                                className={campo}
                            />
                        </label>
                        <label className="grid gap-1 text-xs">
                            Valor máximo (R$)
                            <input
                                name="valor_max"
                                inputMode="decimal"
                                defaultValue={filtros.valor_max}
                                placeholder="Ex.: 500,00"
                                className={campo}
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-2 sm:col-span-2 sm:flex lg:col-span-4">
                            <Button type="submit" className="min-h-11">
                                Aplicar
                            </Button>
                            {/* Limpar: volta só com o período (tira todos os outros filtros). */}
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11"
                                onClick={() =>
                                    router.get(index().url, {
                                        de: filtros.de,
                                        ate: filtros.ate,
                                    })
                                }
                            >
                                Limpar filtros
                            </Button>
                        </div>
                    </form>
                </details>

                {/* Modo: analítico (cada lançamento) ou sintético (somado por grupo). */}
                <div
                    className="flex flex-wrap items-end gap-3"
                    data-tour="modo-financeiro"
                >
                    <div
                        role="group"
                        aria-label="Modo"
                        className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto"
                    >
                        <Button
                            type="button"
                            variant={
                                filtros.modo === 'analitico'
                                    ? 'default'
                                    : 'outline'
                            }
                            aria-pressed={filtros.modo === 'analitico'}
                            onClick={() => filtrar({ modo: 'analitico' })}
                        >
                            Analítico
                        </Button>
                        <Button
                            type="button"
                            variant={
                                filtros.modo === 'sintetico'
                                    ? 'default'
                                    : 'outline'
                            }
                            aria-pressed={filtros.modo === 'sintetico'}
                            onClick={() => filtrar({ modo: 'sintetico' })}
                        >
                            Sintético
                        </Button>
                    </div>
                    {filtros.modo === 'sintetico' && (
                        <label className="grid gap-1 text-xs">
                            Somar por
                            <select
                                value={filtros.agrupar}
                                onChange={(evento) =>
                                    filtrar({ agrupar: evento.target.value })
                                }
                                className={campo}
                            >
                                {opcoes.agrupamentos.map((opcao) => (
                                    <option key={opcao.id} value={opcao.id}>
                                        {opcao.nome}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                </div>

                {/* Muitas linhas: avisa para refinar (a tela mostra até 2000). */}
                {limite_atingido && (
                    <p
                        role="status"
                        className="text-sm text-amber-800 dark:text-amber-200"
                    >
                        O filtro passou de 2000 lançamentos; mostramos os 2000
                        primeiros. Refine o período ou os filtros.
                    </p>
                )}

                {/* SINTÉTICO: uma linha por grupo, com os cinco números. */}
                {filtros.modo === 'sintetico' && (
                    <TabelaRelatorio
                        colunas={COLUNAS_SINTETICO.map((coluna) =>
                            coluna.chave === 'grupo'
                                ? {
                                      ...coluna,
                                      rotulo:
                                          opcoes.agrupamentos.find(
                                              (opcao) =>
                                                  opcao.id === filtros.agrupar,
                                          )?.nome ?? 'Grupo',
                                  }
                                : coluna,
                        )}
                        linhas={sintetico}
                        totais={{ grupo: null, ...cartoes }}
                        titulo="Financeiro sintético"
                    />
                )}

                {/* TABELA: só em tela larga (lg = a partir de 1024 px). No celular
                    ela some e entram os cartões abaixo, sem rolagem lateral. */}
                {filtros.modo === 'analitico' && (
                    <>
                        <div className="hidden overflow-x-auto rounded-md border border-border lg:block">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Lançamentos do filtro escolhido
                                </caption>
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Vencimento
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Descrição
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Categoria
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Pessoa
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 text-right font-medium"
                                        >
                                            Valor
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            Situação
                                        </th>
                                        {pode_lancar && (
                                            <th
                                                scope="col"
                                                className="px-4 py-2 font-medium"
                                            >
                                                <span className="sr-only">
                                                    Ações
                                                </span>
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {lancamentos.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={pode_lancar ? 7 : 6}
                                                className="px-4 py-6 text-center text-muted-foreground"
                                            >
                                                Nenhum lançamento neste filtro.
                                            </td>
                                        </tr>
                                    )}
                                    {lancamentos.map((lancamento) => (
                                        <tr
                                            key={lancamento.id}
                                            className="border-t border-border"
                                        >
                                            <td className="px-4 py-2 whitespace-nowrap">
                                                {dataBr(lancamento.vencimento)}
                                            </td>
                                            <td className="px-4 py-2">
                                                {lancamento.descricao}
                                            </td>
                                            <td className="px-4 py-2">
                                                {lancamento.categoria}
                                                <span className="block text-xs text-muted-foreground">
                                                    {lancamento.movimento}
                                                </span>
                                            </td>
                                            {/* A quem se refere: quem pagou (receita) ou quem recebe (repasse). */}
                                            <td className="px-4 py-2">
                                                {lancamento.pessoa ?? '—'}
                                                {lancamento.curso && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {lancamento.curso}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right whitespace-nowrap">
                                                {/* Receita com "+" e despesa com "−", escrito (não só cor). */}
                                                {lancamento.tipo === 'receita'
                                                    ? '+ '
                                                    : '− '}
                                                {formatarReais(
                                                    lancamento.valor_centavos,
                                                )}
                                            </td>
                                            <td
                                                className={`px-4 py-2 ${SITUACOES[lancamento.situacao].classe}`}
                                            >
                                                {
                                                    SITUACOES[
                                                        lancamento.situacao
                                                    ].texto
                                                }
                                                {lancamento.estorno &&
                                                    ' (estorno)'}
                                                {lancamento.pago_em &&
                                                    ` em ${dataBr(lancamento.pago_em)}`}
                                                {lancamento.estornado &&
                                                    ' · estornado'}
                                            </td>
                                            {/* Ações só para quem pode lançar (o servidor também confere). */}
                                            {pode_lancar && (
                                                <td className="w-px px-4 py-2 whitespace-nowrap">
                                                    <AcoesLancamento
                                                        lancamento={lancamento}
                                                        formas={formas}
                                                    />
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* CARTÕES: só em tela estreita (celular). Um lançamento por
                    cartão, tudo na largura da tela, sem rolagem lateral. */}
                        <ul
                            aria-label="Lançamentos do filtro escolhido"
                            className="flex flex-col gap-3 lg:hidden"
                        >
                            {lancamentos.length === 0 && (
                                <li className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                                    Nenhum lançamento neste filtro.
                                </li>
                            )}
                            {lancamentos.map((lancamento) => (
                                <li
                                    key={lancamento.id}
                                    className="flex flex-col gap-3 rounded-md border border-border p-4"
                                >
                                    {/* Linha 1: vencimento à esquerda, valor à direita. */}
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="text-sm text-muted-foreground">
                                            Vence{' '}
                                            {dataBr(lancamento.vencimento)}
                                        </span>
                                        <span className="text-base font-semibold whitespace-nowrap">
                                            {lancamento.tipo === 'receita'
                                                ? '+ '
                                                : '− '}
                                            {formatarReais(
                                                lancamento.valor_centavos,
                                            )}
                                        </span>
                                    </div>

                                    {/* Linha 2: o que é e a categoria. */}
                                    <div className="flex flex-col gap-0.5">
                                        <span className="font-medium">
                                            {lancamento.descricao}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {lancamento.categoria} ·{' '}
                                            {lancamento.movimento}
                                        </span>
                                        {/* A quem se refere (e o curso), quando há. */}
                                        {lancamento.pessoa && (
                                            <span className="text-sm">
                                                {lancamento.pessoa}
                                                {lancamento.curso &&
                                                    ` · ${lancamento.curso}`}
                                            </span>
                                        )}
                                    </div>

                                    {/* Linha 3: situação escrita (não só cor). */}
                                    <span
                                        className={`text-sm ${SITUACOES[lancamento.situacao].classe}`}
                                    >
                                        {SITUACOES[lancamento.situacao].texto}
                                        {lancamento.estorno && ' (estorno)'}
                                        {lancamento.pago_em &&
                                            ` em ${dataBr(lancamento.pago_em)}`}
                                        {lancamento.estornado && ' · estornado'}
                                    </span>

                                    {/* Linha 4: ações em grade de colunas iguais. */}
                                    {pode_lancar && (
                                        <AcoesLancamento
                                            lancamento={lancamento}
                                            formas={formas}
                                            emGrade
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
LancamentosIndex.layout = {
    breadcrumbs: [{ title: 'Financeiro', href: index() }],
};
