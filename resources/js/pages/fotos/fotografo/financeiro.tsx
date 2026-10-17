/**
 * FOTÓGRAFO — MEU FINANCEIRO (#90): os pedidos pagos dos álbuns dele e a parte
 * que cabe a ele em cada um. Só os dele (nunca o financeiro do IBTO).
 */
import { Head } from '@inertiajs/react';
import { formatarReais } from '@/lib/dinheiro';

/** Um pedido pago. */
type Linha = {
    id: number;
    album: string;
    fotos: number;
    valor_centavos: number;
    parte_centavos: number;
    pago_em: string | null;
};

/**
 * @param pedidos Os pedidos pagos, do mais recente ao mais antigo.
 * @param total_parte_centavos A soma da parte do fotógrafo. Ex.: 31500 (R$ 315,00).
 * @returns A tela "Meu financeiro".
 */
export default function FinanceiroFotografo({
    pedidos,
    total_parte_centavos,
}: {
    pedidos: Linha[];
    total_parte_centavos: number;
}) {
    return (
        <>
            <Head title="Meu financeiro" />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Meu financeiro
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        As vendas pagas dos seus álbuns e a sua parte. O IBTO
                        faz o repasse e dá a baixa no livro-caixa.
                    </p>
                </div>
                <p className="rounded-md border border-border p-4 text-lg font-semibold">
                    Sua parte no total: {formatarReais(total_parte_centavos)}
                </p>
                {pedidos.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nenhuma venda paga ainda.
                    </p>
                )}
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {pedidos.map((pedido) => (
                        <li
                            key={pedido.id}
                            className="flex flex-col gap-1 rounded-md border border-border p-4 text-sm"
                        >
                            <strong>Pedido nº {pedido.id}</strong>
                            <span className="text-muted-foreground">
                                {pedido.album}
                            </span>
                            <span>
                                {pedido.fotos} fotos · venda{' '}
                                {formatarReais(pedido.valor_centavos)}
                            </span>
                            <span className="font-semibold">
                                Sua parte:{' '}
                                {formatarReais(pedido.parte_centavos)}
                            </span>
                            <span className="text-muted-foreground">
                                Pago em {pedido.pago_em}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
FinanceiroFotografo.layout = {
    breadcrumbs: [
        { title: 'Meus álbuns de fotos', href: '/fotografo' },
        { title: 'Meu financeiro', href: '/fotografo/financeiro' },
    ],
};
