/**
 * TRILHAS no Comando Geral (#39): lista com a quantidade de cursos de cada uma.
 * Não existe apagar: a trilha é desativada. Lista simples (cabe no celular).
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index as cursos } from '@/routes/comando-geral/cursos';
import { create, edit, index } from '@/routes/comando-geral/trilhas';

/**
 * @param trilhas Ex.: [{ id: 1, nome: 'Formação', ativa: true, cursos: 5 }]
 * @returns A tela da lista de trilhas.
 */
export default function TrilhasIndex({
    trilhas,
}: {
    trilhas: { id: number; nome: string; ativa: boolean; cursos: number }[];
}) {
    // Mensagem da última ação, ou null.
    const { sucesso } = usePage().props;

    return (
        <>
            <Head title="Trilhas" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Trilhas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Um conjunto de cursos em ordem. Ex.: do Praticante
                            ao Instrutor Expert.
                        </p>
                    </div>
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <Button asChild variant="outline">
                            <Link href={cursos()}>Cursos</Link>
                        </Button>
                        <Button asChild>
                            <Link href={create()}>Nova trilha</Link>
                        </Button>
                    </div>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                <ul
                    aria-label="Lista das trilhas"
                    className="flex flex-col divide-y divide-border rounded-md border border-border"
                >
                    {trilhas.length === 0 && (
                        <li className="px-4 py-6 text-center text-sm text-muted-foreground">
                            Nenhuma trilha ainda.
                        </li>
                    )}
                    {trilhas.map((trilha) => (
                        <li
                            key={trilha.id}
                            className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                        >
                            <span className="flex flex-col">
                                <span className="font-medium">
                                    {trilha.nome}
                                </span>
                                {/* Situação escrita (não só cor). */}
                                <span className="text-muted-foreground">
                                    {trilha.cursos} curso(s) ·{' '}
                                    {trilha.ativa ? 'Ativa' : 'Desativada'}
                                </span>
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11"
                            >
                                <Link
                                    href={edit(trilha.id)}
                                    aria-label={`Editar ${trilha.nome}`}
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
TrilhasIndex.layout = {
    breadcrumbs: [
        { title: 'Cursos', href: cursos() },
        { title: 'Trilhas', href: index() },
    ],
};
