/**
 * TABELA DE UM RELATÓRIO (#73), usada na tela e na versão para impressão.
 *
 * De 1024 px para cima é tabela; abaixo, cada linha vira um cartão (classe
 * "tabela-cartoes", regra da casa: nenhuma tela rola de lado). Formata cada
 * célula pelo tipo da coluna: dinheiro em reais, percentual com %, vazio "—".
 */
import { formatarReais } from '@/lib/dinheiro';

/** Uma coluna do relatório. */
export type ColunaRelatorio = {
    chave: string;
    rotulo: string;
    tipo: 'texto' | 'numero' | 'dinheiro' | 'percentual';
};

/**
 * Formata o valor de uma célula pelo tipo da coluna.
 *
 * @param valor O valor cru. Ex.: 25000 (dinheiro, em centavos).
 * @param tipo O tipo da coluna. Ex.: 'dinheiro'.
 * @returns O texto. Ex.: 'R$ 250,00'.
 */
export function formatarCelula(
    valor: unknown,
    tipo: ColunaRelatorio['tipo'],
): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }
    if (tipo === 'dinheiro') {
        return formatarReais(Number(valor));
    }
    if (tipo === 'percentual') {
        return `${Number(valor)}%`;
    }

    // Texto e número saem como estão; qualquer outra coisa (raro) vira o JSON dela.
    return typeof valor === 'string' || typeof valor === 'number'
        ? String(valor)
        : JSON.stringify(valor);
}

/**
 * @param colunas As colunas, na ordem.
 * @param linhas As linhas (chave da coluna → valor).
 * @param totais A linha de totais (null nas colunas que não somam).
 * @param titulo O título, para o leitor de tela (legenda da tabela).
 * @returns A tabela.
 */
export default function TabelaRelatorio({
    colunas,
    linhas,
    totais,
    titulo,
}: {
    colunas: ColunaRelatorio[];
    linhas: Record<string, unknown>[];
    totais: Record<string, unknown>;
    titulo: string;
}) {
    // Números à direita (fica fácil comparar); texto à esquerda.
    const alinhamento = (tipo: ColunaRelatorio['tipo']) =>
        tipo === 'texto' ? 'text-left' : 'text-right';

    return (
        <table className="tabela-cartoes w-full text-sm">
            <caption className="sr-only">{titulo}</caption>
            <thead>
                <tr className="border-b border-border">
                    {colunas.map((coluna) => (
                        <th
                            key={coluna.chave}
                            scope="col"
                            className={`px-2 py-2 font-semibold ${alinhamento(coluna.tipo)}`}
                        >
                            {coluna.rotulo}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {linhas.length === 0 && (
                    <tr>
                        <td
                            colSpan={colunas.length}
                            className="px-2 py-6 text-center text-muted-foreground"
                        >
                            Nada no período.
                        </td>
                    </tr>
                )}
                {linhas.map((linha, indice) => (
                    <tr key={indice} className="border-b border-border/60">
                        {colunas.map((coluna) => (
                            <td
                                key={coluna.chave}
                                data-rotulo={coluna.rotulo}
                                className={`px-2 py-2 [overflow-wrap:anywhere] ${alinhamento(coluna.tipo)}`}
                            >
                                {formatarCelula(
                                    linha[coluna.chave],
                                    coluna.tipo,
                                )}
                            </td>
                        ))}
                    </tr>
                ))}
                {/* Linha de totais (só as colunas que somam). */}
                {linhas.length > 0 && (
                    <tr className="font-semibold">
                        {colunas.map((coluna, indice) => (
                            <td
                                key={coluna.chave}
                                data-rotulo={coluna.rotulo}
                                className={`px-2 py-2 ${alinhamento(coluna.tipo)}`}
                            >
                                {indice === 0
                                    ? 'Total'
                                    : totais[coluna.chave] === null
                                      ? ''
                                      : formatarCelula(
                                            totais[coluna.chave],
                                            coluna.tipo,
                                        )}
                            </td>
                        ))}
                    </tr>
                )}
            </tbody>
        </table>
    );
}
