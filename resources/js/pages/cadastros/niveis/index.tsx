/**
 * Tela de LISTA dos níveis (#37), só para administradores, na ordem da
 * hierarquia (Praticante → Instrutor Expert V). Desativar é feito pela edição.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/cadastros/niveis';
import type { Nivel } from '@/types/cadastros';

/**
 * @param niveis Todos os níveis, já em ordem. Ex.: [{ id: 1, nome: 'Praticante', ... }]
 * @returns A tela da lista.
 */
export default function NiveisIndex({ niveis }: { niveis: Nivel[] }) {
    // Mensagem da última ação (ex.: 'Nível "Monitor II" atualizado.'), ou null.
    const { sucesso } = usePage().props;

    // Quantos estão ativos, para o resumo no topo.
    const ativos = niveis.filter((nivel) => nivel.ativo).length;

    return (
        <>
            <Head title="Níveis" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Níveis
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {niveis.length} níveis cadastrados, {ativos} ativos.
                            Desativar tira o nível das listas de escolha, sem
                            apagar.
                        </p>
                    </div>

                    <Button asChild>
                        <Link href={create()}>Novo nível</Link>
                    </Button>
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

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Lista dos níveis</caption>
                        <thead className="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Nível
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
                            {niveis.map((nivel) => (
                                <tr
                                    key={nivel.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2">{nivel.nome}</td>
                                    <td className="px-4 py-2">
                                        {/* Situação escrita (não só cor). */}
                                        {nivel.ativo ? (
                                            <span className="text-green-700 dark:text-green-400">
                                                Ativo
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Desativado
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Link
                                            href={edit(nivel.id)}
                                            className="underline underline-offset-4"
                                            aria-label={`Editar ${nivel.nome}`}
                                        >
                                            Editar
                                        </Link>
                                    </td>
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
NiveisIndex.layout = {
    breadcrumbs: [{ title: 'Níveis', href: index() }],
};
