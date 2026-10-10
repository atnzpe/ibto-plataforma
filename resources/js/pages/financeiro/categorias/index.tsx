/**
 * Tela de LISTA das categorias do financeiro (#59). Desativar é feito pela
 * edição (não existe apagar). Responsiva: tabela na tela larga, cartões no celular.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/financeiro/categorias';
import { index as livroCaixa } from '@/routes/financeiro/lancamentos';

/** Uma categoria como a lista mostra. */
type CategoriaLista = {
    id: number;
    nome: string;
    tipo: 'receita' | 'despesa';
    ativa: boolean;
    // Quantos lançamentos usam esta categoria.
    em_uso: number;
};

// Tipo → texto da tela.
const TIPOS = { receita: 'Receita', despesa: 'Despesa' } as const;

/**
 * @param categorias Todas, receitas primeiro. Ex.: [{ id: 1, nome: 'Anuidade', tipo: 'receita', ... }]
 * @returns A tela da lista.
 */
export default function CategoriasIndex({
    categorias,
}: {
    categorias: CategoriaLista[];
}) {
    // Mensagem da última ação (ex.: 'Categoria "Doação" atualizada.'), ou null.
    const { sucesso } = usePage().props;

    return (
        <>
            <Head title="Categorias do financeiro" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Categorias do financeiro
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Para que é o dinheiro. Desativar tira a categoria
                            das opções novas, sem apagar os lançamentos antigos.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={create()}>Nova categoria</Link>
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

                {/* TABELA: só em tela larga. */}
                <div className="hidden overflow-x-auto rounded-md border border-border md:block">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">
                            Lista das categorias
                        </caption>
                        <thead className="bg-muted/50">
                            <tr>
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
                                    Tipo
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Lançamentos
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Situação
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    <span className="sr-only">Ações</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {categorias.map((categoria) => (
                                <tr
                                    key={categoria.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2">
                                        {categoria.nome}
                                    </td>
                                    <td className="px-4 py-2">
                                        {TIPOS[categoria.tipo]}
                                    </td>
                                    <td className="px-4 py-2">
                                        {categoria.em_uso}
                                    </td>
                                    <td className="px-4 py-2">
                                        {/* Situação escrita (não só cor). */}
                                        {categoria.ativa ? (
                                            'Ativa'
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Desativada
                                            </span>
                                        )}
                                    </td>
                                    <td className="w-px px-4 py-2 text-right whitespace-nowrap">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={edit(categoria.id)}
                                                aria-label={`Editar ${categoria.nome}`}
                                            >
                                                Editar
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* CARTÕES: só no celular. */}
                <ul
                    aria-label="Lista das categorias"
                    className="flex flex-col gap-3 md:hidden"
                >
                    {categorias.map((categoria) => (
                        <li
                            key={categoria.id}
                            className="flex flex-col gap-3 rounded-md border border-border p-4 text-sm"
                        >
                            <div className="flex items-baseline justify-between gap-3">
                                <span className="font-medium">
                                    {categoria.nome}
                                </span>
                                <span className="text-muted-foreground">
                                    {TIPOS[categoria.tipo]}
                                </span>
                            </div>
                            <span className="text-muted-foreground">
                                {categoria.ativa ? 'Ativa' : 'Desativada'} ·{' '}
                                {categoria.em_uso} lançamento(s)
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11 w-full"
                            >
                                <Link
                                    href={edit(categoria.id)}
                                    aria-label={`Editar ${categoria.nome}`}
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
CategoriasIndex.layout = {
    breadcrumbs: [
        { title: 'Financeiro', href: livroCaixa() },
        { title: 'Categorias', href: index() },
    ],
};
