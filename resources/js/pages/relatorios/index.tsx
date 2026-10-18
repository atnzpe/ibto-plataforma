/**
 * RELATÓRIOS — ÍNDICE (#73): os relatórios que esta pessoa pode ver (cada um tem
 * a sua permissão). Clique para abrir; lá dentro: filtro De/Até, CSV e PDF.
 */
import { Head, Link } from '@inertiajs/react';

/** Um relatório disponível. href: o endereço da tela (o painel das aulas tem tela própria). */
type Item = { chave: string; titulo: string; descricao: string; href: string };

/**
 * @param relatorios Os relatórios liberados para esta pessoa.
 * @returns O índice.
 */
export default function RelatoriosIndex({
    relatorios,
}: {
    relatorios: Item[];
}) {
    return (
        <>
            <Head title="Relatórios" />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Relatórios
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Cada relatório tem filtro De/Até, exportação em CSV e
                        versão para impressão (PDF). Toda exportação fica
                        registrada.
                    </p>
                </div>
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {relatorios.map((relatorio) => (
                        <li key={relatorio.chave}>
                            <Link
                                href={relatorio.href}
                                className="flex h-full min-h-24 flex-col gap-1 rounded-md border border-border p-4 text-sm transition-colors hover:border-[#6b5d3a] focus-visible:outline-2"
                            >
                                <strong className="text-base">
                                    {relatorio.titulo}
                                </strong>
                                <span className="text-muted-foreground">
                                    {relatorio.descricao}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
RelatoriosIndex.layout = {
    breadcrumbs: [{ title: 'Relatórios', href: '/relatorios' }],
};
