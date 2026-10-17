/**
 * RELATÓRIO NA TELA (#73): filtro De/Até, botões CSV e PDF (versão para
 * impressão, que abre em outra aba) e a tabela, que vira cartões no celular.
 */
import { Head, router } from '@inertiajs/react';
import FiltroPeriodo from '@/components/filtro-periodo';
import TabelaRelatorio from '@/components/relatorios/tabela-relatorio';
import type { ColunaRelatorio } from '@/components/relatorios/tabela-relatorio';
import { Button } from '@/components/ui/button';

/** O que a tela recebe do servidor. */
export type DadosRelatorio = {
    relatorio: { chave: string; titulo: string; descricao: string };
    colunas: ColunaRelatorio[];
    linhas: Record<string, unknown>[];
    totais: Record<string, unknown>;
    periodo: { de: string; ate: string };
};

/**
 * @param props O relatório, as colunas, as linhas, os totais e o período.
 * @returns A tela do relatório.
 */
export default function RelatorioVer({
    relatorio,
    colunas,
    linhas,
    totais,
    periodo,
}: DadosRelatorio) {
    // Os endereços do CSV e da impressão, com o mesmo período da tela.
    const busca = `?de=${periodo.de}&ate=${periodo.ate}`;

    return (
        <>
            <Head title={relatorio.titulo} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {relatorio.titulo}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {relatorio.descricao}
                        </p>
                    </div>
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <Button asChild variant="outline" className="min-h-11">
                            <a
                                href={`/relatorios/${relatorio.chave}/csv${busca}`}
                            >
                                Exportar CSV
                            </a>
                        </Button>
                        <Button asChild variant="outline" className="min-h-11">
                            <a
                                href={`/relatorios/${relatorio.chave}/imprimir${busca}`}
                                target="_blank"
                                rel="noopener"
                            >
                                PDF / imprimir
                            </a>
                        </Button>
                    </div>
                </div>

                <FiltroPeriodo
                    de={periodo.de}
                    ate={periodo.ate}
                    prefixo="relatorio"
                    aoMudar={(novo) =>
                        router.get(`/relatorios/${relatorio.chave}`, novo, {
                            preserveState: true,
                            preserveScroll: true,
                        })
                    }
                />

                <TabelaRelatorio
                    colunas={colunas}
                    linhas={linhas}
                    totais={totais}
                    titulo={relatorio.titulo}
                />
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
RelatorioVer.layout = {
    breadcrumbs: [{ title: 'Relatórios', href: '/relatorios' }],
};
