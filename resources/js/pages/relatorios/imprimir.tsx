/**
 * RELATÓRIO — VERSÃO PARA IMPRESSÃO / PDF (#73). Página limpa (sem menu), com a
 * marca do IBTO, o título, o período, a data/hora de geração e quem gerou. O
 * botão "Imprimir ou salvar em PDF" abre a janela de impressão do navegador
 * (lá, escolha "Salvar como PDF"). O botão some na impressão.
 */
import { Head } from '@inertiajs/react';
import TabelaRelatorio from '@/components/relatorios/tabela-relatorio';
import type { DadosRelatorio } from '@/pages/relatorios/ver';

/**
 * @param props Os dados do relatório, mais quando e quem gerou.
 * @returns A página de impressão.
 */
export default function RelatorioImprimir({
    relatorio,
    colunas,
    linhas,
    totais,
    periodo,
    gerado_em,
    gerado_por,
}: DadosRelatorio & { gerado_em: string; gerado_por: string }) {
    // Datas do período no formato brasileiro. Ex.: '2026-10-01' → '01/10/2026'.
    const br = (data: string) => data.split('-').reverse().join('/');

    return (
        <>
            <Head title={`${relatorio.titulo} — impressão`} />
            <main className="mx-auto flex max-w-5xl flex-col gap-4 bg-white p-6 text-black print:p-0">
                <header className="flex items-center gap-4 border-b border-black/20 pb-4">
                    <img
                        src="/images/ibto-emblema.png"
                        alt="Emblema do IBTO"
                        className="size-16"
                    />
                    <div>
                        <p className="text-xs tracking-widest uppercase">
                            Instituto Brasileiro de Treinamento Operacional
                        </p>
                        <h1 className="text-xl font-bold">
                            {relatorio.titulo}
                        </h1>
                        <p className="text-xs">
                            Período: {br(periodo.de)} a {br(periodo.ate)} ·
                            Gerado em {gerado_em} por {gerado_por}
                        </p>
                    </div>
                </header>

                <button
                    type="button"
                    onClick={() => window.print()}
                    className="min-h-11 self-start rounded-md border border-black/30 px-4 text-sm font-medium print:hidden"
                >
                    Imprimir ou salvar em PDF
                </button>

                <TabelaRelatorio
                    colunas={colunas}
                    linhas={linhas}
                    totais={totais}
                    titulo={relatorio.titulo}
                />

                <footer className="border-t border-black/20 pt-2 text-[10px]">
                    Documento interno do IBTO. Sem dados pessoais sensíveis
                    (LGPD). Esta exportação foi registrada na auditoria.
                </footer>
            </main>
        </>
    );
}
