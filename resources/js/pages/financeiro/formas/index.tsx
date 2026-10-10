/**
 * Tela de LISTA das formas de pagamento do financeiro (#59). Desativar é feito
 * pela edição (não existe apagar). Responsiva: tabela na tela larga, cartões no celular.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/financeiro/formas';
import { index as livroCaixa } from '@/routes/financeiro/lancamentos';

/** Uma forma de pagamento como a lista mostra. */
type FormaLista = { id: number; nome: string; ativa: boolean };

/**
 * @param formas Todas, em ordem de nome. Ex.: [{ id: 1, nome: 'PIX', ativa: true }]
 * @returns A tela da lista.
 */
export default function FormasIndex({ formas }: { formas: FormaLista[] }) {
    // Mensagem da última ação, ou null.
    const { sucesso } = usePage().props;

    return (
        <>
            <Head title="Formas de pagamento" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Formas de pagamento
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Como o dinheiro entra ou sai. Desativar tira a forma
                            das opções da baixa, sem apagar os lançamentos
                            antigos.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={create()}>Nova forma</Link>
                    </Button>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* Lista simples (2 colunas): cabe no celular sem virar cartão. */}
                <ul
                    aria-label="Lista das formas de pagamento"
                    className="flex flex-col divide-y divide-border rounded-md border border-border"
                >
                    {formas.map((forma) => (
                        <li
                            key={forma.id}
                            className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                        >
                            <span className="flex flex-col">
                                <span className="font-medium">
                                    {forma.nome}
                                </span>
                                {/* Situação escrita (não só cor). */}
                                <span className="text-muted-foreground">
                                    {forma.ativa ? 'Ativa' : 'Desativada'}
                                </span>
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11"
                            >
                                <Link
                                    href={edit(forma.id)}
                                    aria-label={`Editar ${forma.nome}`}
                                >
                                    Editar
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
FormasIndex.layout = {
    breadcrumbs: [
        { title: 'Financeiro', href: livroCaixa() },
        { title: 'Formas de pagamento', href: index() },
    ],
};
