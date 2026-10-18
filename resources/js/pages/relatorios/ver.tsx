/**
 * RELATÓRIO NA TELA (#73), com os FILTROS ROBUSTOS (18/10/2026): período
 * (De/Até), filtro por cada dimensão (equipe, modalidade, área, situação,
 * instrutor...) e por coluna de texto, busca livre, e os modos ANALÍTICO
 * (cada linha) ou SINTÉTICO (somado pelo campo escolhido). Os botões CSV e
 * PDF exportam exatamente o que está na tela. A tabela vira cartões no celular.
 */
import { Head, router } from '@inertiajs/react';
import FiltroPeriodo from '@/components/filtro-periodo';
import TabelaRelatorio from '@/components/relatorios/tabela-relatorio';
import type { ColunaRelatorio } from '@/components/relatorios/tabela-relatorio';
import { Button } from '@/components/ui/button';

/** Um campo de filtro: a chave, o nome e os valores possíveis. */
type CampoFiltro = { chave: string; rotulo: string; valores: string[] };

/** O que a tela recebe do servidor. */
export type DadosRelatorio = {
    relatorio: { chave: string; titulo: string; descricao: string };
    colunas: ColunaRelatorio[];
    linhas: Record<string, unknown>[];
    totais: Record<string, unknown>;
    periodo: { de: string; ate: string };
    // Os filtros aplicados: busca, f_<chave>, modo e agrupar. Ex.: { f_modalidade: 'Online', modo: 'sintetico' }
    filtros?: Record<string, string>;
    // Os campos de filtro disponíveis neste relatório.
    campos_filtro?: CampoFiltro[];
};

/**
 * @param props O relatório, as colunas, as linhas, os totais, o período e os filtros.
 * @returns A tela do relatório.
 */
export default function RelatorioVer({
    relatorio,
    colunas,
    linhas,
    totais,
    periodo,
    filtros = {},
    campos_filtro = [],
}: DadosRelatorio) {
    /**
     * O endereço com o período e os filtros (sem os vazios).
     *
     * @param novo O que mudou. Ex.: { f_equipe: 'IBTO' }
     * @returns Ex.: { de: '2026-01-01', ate: '2026-12-31', f_equipe: 'IBTO' }
     */
    const parametros = (novo: Record<string, string> = {}) =>
        Object.fromEntries(
            Object.entries({ ...periodo, ...filtros, ...novo }).filter(
                ([, valor]) => valor !== '' && valor !== undefined,
            ),
        );

    /**
     * Recarrega a tela com os filtros novos (o que não mudou continua).
     *
     * @param novo O que mudou. Ex.: { modo: 'sintetico', agrupar: 'equipe' }
     */
    const filtrar = (novo: Record<string, string>) =>
        router.get(`/relatorios/${relatorio.chave}`, parametros(novo), {
            preserveState: true,
            preserveScroll: true,
        });

    // Os endereços do CSV e da impressão, com os mesmos filtros da tela.
    const busca = `?${new URLSearchParams(parametros()).toString()}`;
    // Modo atual: analítico (padrão) ou sintético.
    const sintetico = filtros.modo === 'sintetico';
    // Visual dos campos (44 px de altura).
    const campo =
        'h-11 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm';

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
                    aoMudar={(novo) => filtrar(novo)}
                />

                {/* Filtros: um campo por dimensão/coluna, e a busca livre. */}
                {campos_filtro.length > 0 && (
                    <div
                        className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        data-tour="filtros-relatorio"
                    >
                        {campos_filtro.map((filtro) => (
                            <label
                                key={filtro.chave}
                                className="grid gap-1 text-xs"
                            >
                                {filtro.rotulo}
                                <select
                                    value={filtros[`f_${filtro.chave}`] ?? ''}
                                    onChange={(evento) =>
                                        filtrar({
                                            [`f_${filtro.chave}`]:
                                                evento.target.value,
                                        })
                                    }
                                    className={campo}
                                >
                                    <option value="">Todos</option>
                                    {filtro.valores.map((valor) => (
                                        <option key={valor} value={valor}>
                                            {valor}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ))}
                    </div>
                )}
                {/* Busca livre: parte do texto em qualquer coluna (ao enviar). */}
                <form
                    key={filtros.busca ?? ''}
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        const valor = new FormData(evento.currentTarget).get(
                            'busca',
                        );
                        filtrar({
                            busca:
                                typeof valor === 'string' ? valor.trim() : '',
                        });
                    }}
                >
                    <label className="grid min-w-0 flex-1 gap-1 text-xs">
                        Buscar em todas as colunas
                        <input
                            name="busca"
                            type="search"
                            maxLength={100}
                            defaultValue={filtros.busca ?? ''}
                            placeholder="Ex.: Recife, Combate, Marcelo"
                            className={campo}
                        />
                    </label>
                    <Button
                        type="submit"
                        variant="outline"
                        className="min-h-11"
                    >
                        Buscar
                    </Button>
                    {/* Limpar: volta só com o período. */}
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={() =>
                            router.get(
                                `/relatorios/${relatorio.chave}`,
                                periodo,
                            )
                        }
                    >
                        Limpar filtros
                    </Button>
                </form>

                {/* Modo: analítico (cada linha) ou sintético (somado por um campo). */}
                {campos_filtro.length > 0 && (
                    <div
                        className="flex flex-wrap items-end gap-3"
                        data-tour="modo-relatorio"
                    >
                        <div
                            role="group"
                            aria-label="Modo"
                            className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto"
                        >
                            <Button
                                type="button"
                                variant={sintetico ? 'outline' : 'default'}
                                aria-pressed={!sintetico}
                                onClick={() =>
                                    filtrar({ modo: '', agrupar: '' })
                                }
                            >
                                Analítico
                            </Button>
                            <Button
                                type="button"
                                variant={sintetico ? 'default' : 'outline'}
                                aria-pressed={sintetico}
                                onClick={() =>
                                    filtrar({
                                        modo: 'sintetico',
                                        agrupar:
                                            filtros.agrupar ||
                                            campos_filtro[0].chave,
                                    })
                                }
                            >
                                Sintético
                            </Button>
                        </div>
                        {sintetico && (
                            <label className="grid gap-1 text-xs">
                                Somar por
                                <select
                                    value={filtros.agrupar ?? ''}
                                    onChange={(evento) =>
                                        filtrar({
                                            modo: 'sintetico',
                                            agrupar: evento.target.value,
                                        })
                                    }
                                    className={campo}
                                >
                                    {campos_filtro.map((filtro) => (
                                        <option
                                            key={filtro.chave}
                                            value={filtro.chave}
                                        >
                                            {filtro.rotulo}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                    </div>
                )}

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
