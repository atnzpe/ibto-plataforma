/**
 * Tela do LIVRO-CAIXA (#59): os lançamentos do mês, com filtros (todos, a
 * receber, a pagar, vencidos) e os totais no topo. Só entra quem tem
 * "financeiro.ver"; o botão "Novo lançamento" só aparece para "financeiro.lancar".
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
// Botões e janelas de baixa, cancelar e estornar (#59).
import AcoesLancamento from '@/components/financeiro/acoes-lancamento';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';
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
 * @param filtros O mês e a visão escolhidos. Ex.: { mes: '2027-03', visao: 'todos' }
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
    const { sucesso } = usePage().props;

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
            titulo: 'Saldo do mês (pagos)',
            valor: formatarReais(totais.saldo_centavos),
        },
        {
            titulo: 'A receber no mês',
            valor: formatarReais(totais.a_receber_centavos),
        },
        {
            titulo: 'A pagar no mês',
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
                    </div>

                    {/* Só quem pode lançar vê o botão (o servidor também confere). */}
                    {pode_lancar && (
                        <Button asChild>
                            <Link href={create()}>Novo lançamento</Link>
                        </Button>
                    )}
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

                {/* Filtros: mês e visão. */}
                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-1">
                        <label htmlFor="mes" className="text-sm font-medium">
                            Mês
                        </label>
                        <input
                            id="mes"
                            type="month"
                            value={filtros.mes}
                            // Só recarrega quando o mês está completo (ex.: '2027-03').
                            onChange={(evento) =>
                                evento.target.value &&
                                filtrar({ mes: evento.target.value })
                            }
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        />
                    </div>

                    <div
                        role="group"
                        aria-label="Visão"
                        className="flex flex-wrap gap-2"
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

                <div className="overflow-x-auto rounded-md border border-border">
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
                                        <td className="px-4 py-2">
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
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
LancamentosIndex.layout = {
    breadcrumbs: [{ title: 'Financeiro', href: index() }],
};
