/**
 * Tela do HISTÓRICO do financeiro (#59): o log de toda movimentação, do mais
 * novo para o mais antigo. Só leitura: não há botão de apagar nem de editar.
 * Filtros por mês e por número do lançamento ("a vida inteira" de uma conta).
 * Responsiva: tabela em tela larga; cartões no celular (sem rolagem lateral).
 */
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatarReais } from '@/lib/dinheiro';
import { historico } from '@/routes/financeiro';
import { index as lancamentos } from '@/routes/financeiro/lancamentos';

/** Uma linha do histórico, como o servidor manda. */
type LinhaHistorico = {
    id: number;
    // Ex.: '2026-10-09 15:42'.
    quando: string;
    quem: string;
    acao: string;
    // Lançamento(s) da ação. Ex.: [7], ou [8, 9, 10] num parcelamento.
    lancamentos: number[];
    estorno_de: number | null;
    parcelas: number | null;
    // Baixa parcial: quanto ficou em aberto (em centavos). Ex.: 60000.
    saldo_centavos: number | null;
    valor_centavos: number | null;
    antes: { valor_centavos: number | null; vencimento: string | null } | null;
    depois: { valor_centavos: number | null; vencimento: string | null } | null;
};

// Código da auditoria → texto em português para a tela.
const ROTULOS: Record<string, string> = {
    LANCAMENTO_CRIADO: 'Criou', // Ex.: "Fulano criou o lançamento nº 7"
    LANCAMENTO_EDITADO: 'Editou', // Ex.: "Fulano editou o lançamento nº 7"
    LANCAMENTO_PARCELADO: 'Parcelou', // Ex.: "Fulano parcelou o lançamento nº 7"
    LANCAMENTO_PAGO: 'Deu baixa', // Ex.: "Fulano deu baixa no lançamento nº 7"
    LANCAMENTO_CANCELADO: 'Cancelou', // Ex.: "Fulano cancelou o lançamento nº 7"
    LANCAMENTO_ESTORNADO: 'Estornou', // Ex.: "Fulano estornou o lançamento nº 7"
    LANCAMENTO_PAGO_PARCIAL: 'Deu baixa parcial',
    ANUIDADE_PAGA: 'Recebeu anuidade',
    PARCELAS_CANCELADAS: 'Cancelou as parcelas',
};

/**
 * Data e hora do servidor no formato brasileiro.
 *
 * @param quando Ex.: '2026-10-09 15:42'
 * @returns Ex.: '09/10/2026 15:42'
 */
function quandoBr(quando: string): string {
    const [data, hora] = quando.split(' ');
    const [ano, mes, dia] = data.split('-');

    return `${dia}/${mes}/${ano} ${hora}`;
}

/**
 * Data simples ('2026-10-30') no formato brasileiro ('30/10/2026'); vazio vira '—'.
 */
function dataBr(data: string | null): string {
    if (!data) {
        return '—';
    }
    const [ano, mes, dia] = data.split('-');

    return `${dia}/${mes}/${ano}`;
}

/**
 * O "detalhe" de cada linha, em texto. Ex.: 'R$ 1.250,00 → R$ 12,50 · venc. 09/10 → 10/10'
 *
 * @param linha A linha do histórico.
 * @returns O texto do detalhe.
 */
function detalhe(linha: LinhaHistorico): string {
    // Edição: mostra o antes → depois do valor e do vencimento.
    if (linha.antes && linha.depois) {
        const valor = `${formatarReais(linha.antes.valor_centavos ?? 0)} → ${formatarReais(linha.depois.valor_centavos ?? 0)}`;
        const vencimento =
            linha.antes.vencimento !== linha.depois.vencimento
                ? ` · venc. ${dataBr(linha.antes.vencimento)} → ${dataBr(linha.depois.vencimento)}`
                : '';

        return valor + vencimento;
    }
    // Baixa parcial: quanto entrou e quanto ficou em aberto.
    if (linha.saldo_centavos !== null) {
        return `${formatarReais(linha.valor_centavos ?? 0)} pagos · saldo de ${formatarReais(linha.saldo_centavos)} em aberto`;
    }
    // Parcelamento: o total e em quantas vezes.
    if (linha.parcelas) {
        return `${formatarReais(linha.valor_centavos ?? 0)} em ${linha.parcelas}x`;
    }
    // Estorno: aponta para o original.
    if (linha.estorno_de) {
        return `${formatarReais(linha.valor_centavos ?? 0)} (estorno do nº ${linha.estorno_de})`;
    }

    return linha.valor_centavos !== null
        ? formatarReais(linha.valor_centavos)
        : '';
}

/**
 * @param linhas As linhas da página atual.
 * @param filtros Ex.: { mes: '2026-10', lancamento: 7 }
 * @param paginacao A página atual, a última, o total e os links.
 * @returns A tela do histórico.
 */
export default function Historico({
    linhas,
    filtros,
    paginacao,
}: {
    linhas: LinhaHistorico[];
    filtros: { mes: string; lancamento: number | null };
    paginacao: {
        atual: number;
        ultima: number;
        total: number;
        anterior: string | null;
        proxima: string | null;
    };
}) {
    /**
     * Recarrega com um filtro trocado, mantendo o outro (e voltando à página 1).
     *
     * @param novo Ex.: { mes: '2026-10' }
     */
    const filtrar = (novo: Partial<{ mes: string; lancamento: string }>) => {
        const tudo = {
            mes: filtros.mes,
            lancamento: filtros.lancamento ?? '',
            ...novo,
        };
        // Só manda o que tem valor (endereço limpo).
        const parametros = Object.fromEntries(
            Object.entries(tudo).filter(
                ([, valor]) => valor !== '' && valor !== null,
            ),
        );
        router.get(historico().url, parametros, { preserveScroll: true });
    };

    /**
     * Os links dos lançamentos de uma linha (cada um filtra o histórico por ele).
     */
    const numeros = (linha: LinhaHistorico) =>
        linha.lancamentos.map((numero, posicao) => (
            <span key={numero}>
                {posicao > 0 && ', '}
                <Link
                    href={historico({ query: { lancamento: numero } })}
                    className="underline underline-offset-4"
                    aria-label={`Ver o histórico do lançamento ${numero}`}
                >
                    nº {numero}
                </Link>
            </span>
        ));

    return (
        <>
            <Head title="Histórico do financeiro" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Histórico do financeiro
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Toda movimentação, com quem fez e quando. Só
                            leitura: nada aqui pode ser apagado ou alterado.
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="w-full sm:w-auto"
                    >
                        <Link href={lancamentos()}>Voltar ao livro-caixa</Link>
                    </Button>
                </div>

                {/* Filtros: em grade de 2 colunas iguais no celular. */}
                <div className="grid grid-cols-2 items-end gap-3 sm:flex sm:flex-wrap">
                    <div className="grid gap-1">
                        <Label htmlFor="mes">Mês</Label>
                        <Input
                            id="mes"
                            type="month"
                            value={filtros.mes}
                            onChange={(evento) =>
                                filtrar({ mes: evento.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="lancamento">Lançamento nº</Label>
                        <Input
                            id="lancamento"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            defaultValue={filtros.lancamento ?? ''}
                            // Filtra ao sair do campo ou apertar Enter (não a cada número digitado).
                            onBlur={(evento) =>
                                filtrar({ lancamento: evento.target.value })
                            }
                            onKeyDown={(evento) => {
                                if (evento.key === 'Enter') {
                                    filtrar({
                                        lancamento: evento.currentTarget.value,
                                    });
                                }
                            }}
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        className="col-span-2 sm:col-span-1"
                        onClick={() => router.get(historico().url)}
                    >
                        Limpar filtros
                    </Button>
                </div>

                <p className="text-sm text-muted-foreground" role="status">
                    {paginacao.total} registro(s)
                    {filtros.lancamento &&
                        ` do lançamento nº ${filtros.lancamento}`}
                    .
                </p>

                {/* TABELA: só em tela larga. */}
                <div className="hidden overflow-x-auto rounded-md border border-border md:block">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">
                            Histórico do financeiro
                        </caption>
                        <thead className="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Quando
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Quem
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Ação
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Lançamento
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Detalhe
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {linhas.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        Nenhuma movimentação neste filtro.
                                    </td>
                                </tr>
                            )}
                            {linhas.map((linha) => (
                                <tr
                                    key={linha.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        {quandoBr(linha.quando)}
                                    </td>
                                    <td className="px-4 py-2">{linha.quem}</td>
                                    <td className="px-4 py-2 font-medium">
                                        {ROTULOS[linha.acao] ?? linha.acao}
                                    </td>
                                    <td className="px-4 py-2">
                                        {numeros(linha)}
                                    </td>
                                    <td className="px-4 py-2">
                                        {detalhe(linha)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* CARTÕES: só no celular. */}
                <ul
                    aria-label="Histórico do financeiro"
                    className="flex flex-col gap-3 md:hidden"
                >
                    {linhas.length === 0 && (
                        <li className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                            Nenhuma movimentação neste filtro.
                        </li>
                    )}
                    {linhas.map((linha) => (
                        <li
                            key={linha.id}
                            className="flex flex-col gap-1 rounded-md border border-border p-4 text-sm"
                        >
                            <div className="flex items-baseline justify-between gap-3">
                                <span className="font-medium">
                                    {ROTULOS[linha.acao] ?? linha.acao}
                                </span>
                                <span className="text-muted-foreground">
                                    {quandoBr(linha.quando)}
                                </span>
                            </div>
                            <span>{detalhe(linha)}</span>
                            <span className="text-muted-foreground">
                                {linha.quem} · {numeros(linha)}
                            </span>
                        </li>
                    ))}
                </ul>

                {/* Páginas: anterior e próxima, em 2 colunas iguais. */}
                {paginacao.ultima > 1 && (
                    <nav
                        aria-label="Páginas do histórico"
                        className="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:justify-end"
                    >
                        <span className="col-span-2 text-center text-sm text-muted-foreground sm:col-span-1">
                            Página {paginacao.atual} de {paginacao.ultima}
                        </span>
                        {paginacao.anterior ? (
                            <Button asChild variant="outline">
                                <Link href={paginacao.anterior} preserveScroll>
                                    Anterior
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" disabled>
                                Anterior
                            </Button>
                        )}
                        {paginacao.proxima ? (
                            <Button asChild variant="outline">
                                <Link href={paginacao.proxima} preserveScroll>
                                    Próxima
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" disabled>
                                Próxima
                            </Button>
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
Historico.layout = {
    breadcrumbs: [
        { title: 'Financeiro', href: lancamentos() },
        { title: 'Histórico', href: historico() },
    ],
};
