/**
 * Tela do LIVRO-CAIXA (#59): os lançamentos do mês, com filtros (todos, a
 * receber, a pagar, vencidos) e os totais no topo. Só entra quem tem
 * "financeiro.ver"; o botão "Novo lançamento" só aparece para "financeiro.lancar".
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
// Botões e janelas de baixa, cancelar e estornar (#59).
import AcoesLancamento from '@/components/financeiro/acoes-lancamento';
// Filtro "De / Até" (o mesmo em todas as telas com data).
import FiltroPeriodo from '@/components/filtro-periodo';
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
    FiltrosFinanceiro,
    Lancamento,
    TotaisFinanceiro,
} from '@/types/financeiro';

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
 * @param lancamentos As linhas do filtro atual.
 * @param totais Os totais do mês (saldo, a receber, a pagar, vencidos).
 * @param filtros O período e a visão escolhidos. Ex.: { de: '2027-03-01', ate: '2027-03-31', visao: 'todos' }
 * @param pode_lancar true se a pessoa pode lançar, dar baixa, cancelar e estornar.
 * @param formas As formas de pagamento ativas, para a janela de baixa.
 * @returns A tela do livro-caixa.
 */
export default function LancamentosIndex({
    lancamentos,
    totais,
    filtros,
    pode_lancar,
    formas,
}: {
    lancamentos: Lancamento[];
    totais: TotaisFinanceiro;
    filtros: FiltrosFinanceiro;
    pode_lancar: boolean;
    formas: { id: number; nome: string }[];
}) {
    // Mensagem da última ação (ex.: 'Lançamento cadastrado.'), ou null.
    // errors.acao: aviso de regra que volta para a lista (ex.: tentar editar um pago).
    const { sucesso, errors } = usePage().props;
    const avisoDeRegra = (errors as Record<string, string> | undefined)?.acao;

    /**
     * Recarrega a lista com um filtro trocado, mantendo o outro.
     *
     * @param novo O filtro que mudou. Ex.: { visao: 'pagar' }
     */
    const filtrar = (novo: Partial<FiltrosFinanceiro>) =>
        router.get(
            index().url,
            { ...filtros, ...novo },
            { preserveScroll: true },
        );

    // Os 4 quadros do topo: título → valor já escrito.
    const quadros = [
        {
            titulo: 'Saldo do período (pagos)',
            valor: formatarReais(totais.saldo_centavos),
        },
        {
            titulo: 'A receber no período',
            valor: formatarReais(totais.a_receber_centavos),
        },
        {
            titulo: 'A pagar no período',
            valor: formatarReais(totais.a_pagar_centavos),
        },
        { titulo: 'Vencidos (todos os meses)', valor: String(totais.vencidos) },
    ];

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

                {/* Totais do mês. */}
                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {quadros.map((quadro) => (
                        <div
                            key={quadro.titulo}
                            className="rounded-md border border-border p-4"
                        >
                            <dt className="text-sm text-muted-foreground">
                                {quadro.titulo}
                            </dt>
                            <dd className="text-lg font-semibold">
                                {quadro.valor}
                            </dd>
                        </div>
                    ))}
                </dl>

                {/* Filtros: período (de / até) e visão. */}
                <div className="flex flex-wrap items-end gap-4">
                    <FiltroPeriodo
                        prefixo="livro-caixa"
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(periodo) => filtrar(periodo)}
                    />

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

                {/* TABELA: só em tela larga (md = a partir de 768 px). No celular
                    ela some e entram os cartões abaixo, sem rolagem lateral. */}
                <div className="hidden overflow-x-auto rounded-md border border-border md:block">
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
                                        <span className="sr-only">Ações</span>
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {lancamentos.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={pode_lancar ? 6 : 5}
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
                                        {SITUACOES[lancamento.situacao].texto}
                                        {lancamento.estorno && ' (estorno)'}
                                        {lancamento.pago_em &&
                                            ` em ${dataBr(lancamento.pago_em)}`}
                                        {lancamento.estornado && ' · estornado'}
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
                    className="flex flex-col gap-3 md:hidden"
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
                                    Vence {dataBr(lancamento.vencimento)}
                                </span>
                                <span className="text-base font-semibold whitespace-nowrap">
                                    {lancamento.tipo === 'receita'
                                        ? '+ '
                                        : '− '}
                                    {formatarReais(lancamento.valor_centavos)}
                                </span>
                            </div>

                            {/* Linha 2: o que é e a categoria. */}
                            <div className="flex flex-col gap-0.5">
                                <span className="font-medium">
                                    {lancamento.descricao}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {lancamento.categoria}
                                </span>
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
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
LancamentosIndex.layout = {
    breadcrumbs: [{ title: 'Financeiro', href: index() }],
};
