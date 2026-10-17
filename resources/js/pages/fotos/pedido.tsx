/**
 * PEDIDO DE FOTOS (#90): aguardando pagamento → o Pix copia e cola com QR (valor
 * exato e identificador IBTOFOT…); pago → os links para baixar os originais, sem
 * marca d'água, até a data limite.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import VitrineFotosController from '@/actions/App/Http/Controllers/Fotos/VitrineFotosController';
import PixCopiaECola from '@/components/cursos/pix-copia-e-cola';
import PassosCompra from '@/components/fotos/passos-compra';
import type { PixDaMatricula } from '@/components/cursos/pix-copia-e-cola';
import { formatarReais } from '@/lib/dinheiro';

/** O pedido com as fotos. */
type Pedido = {
    id: number;
    album: string;
    album_id: number;
    situacao: string;
    valor_centavos: number;
    download_ate: string | null;
    pode_baixar: boolean;
    fotos: { id: number; previa_url: string; download_url: string | null }[];
};

/**
 * @param pedido O pedido.
 * @param pix O Pix (só aguardando pagamento e com chave cadastrada), ou null.
 * @returns A tela do pedido.
 */
export default function PedidoFotos({
    pedido,
    pix,
}: {
    pedido: Pedido;
    pix: (PixDaMatricula & { favorecido: string; chave: string }) | null;
}) {
    const { sucesso } = usePage<{ sucesso?: string }>().props;

    return (
        <>
            <Head title={`Pedido de fotos nº ${pedido.id}`} />
            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Pedido de fotos nº {pedido.id}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {pedido.album} · {pedido.fotos.length} foto(s) ·{' '}
                        {formatarReais(pedido.valor_centavos)}
                    </p>
                </div>
                {/* Os 3 passos (17/10/2026): aguardando = passo 2 (pagar); pago = passo 3 (baixar). Cancelado: sem a faixa. */}
                {pedido.situacao !== 'cancelado' && (
                    <PassosCompra atual={pedido.situacao === 'pago' ? 3 : 2} />
                )}
                {pedido.situacao === 'aguardando_pagamento' && (
                    <p className="text-base">
                        Pague o Pix abaixo. Quando o IBTO confirmar, as fotos
                        sem marca ficam liberadas aqui mesmo e em{' '}
                        <strong>Fotos dos eventos</strong>.
                    </p>
                )}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {pedido.situacao === 'aguardando_pagamento' &&
                    (pix ? (
                        <PixCopiaECola
                            pix={pix}
                            favorecido={pix.favorecido}
                            chave={pix.chave}
                        />
                    ) : (
                        <p className="text-sm">
                            Fale com o IBTO pelo suporte para receber os dados
                            para pagamento.
                        </p>
                    ))}

                {pedido.situacao === 'cancelado' && (
                    <p className="text-sm">Este pedido foi cancelado.</p>
                )}

                {/* Pago: os originais para baixar, até a data limite. */}
                {pedido.situacao === 'pago' && (
                    <p className="rounded-md border border-border p-3 text-sm">
                        {pedido.pode_baixar
                            ? `Pago! Baixe as suas fotos até ${pedido.download_ate}.`
                            : 'O prazo para baixar estas fotos terminou.'}
                    </p>
                )}

                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {pedido.fotos.map((foto) => (
                        <li key={foto.id} className="flex flex-col gap-1">
                            <img
                                src={foto.previa_url}
                                alt={`Foto ${foto.id} do pedido`}
                                loading="lazy"
                                className="aspect-square w-full rounded-md object-cover"
                            />
                            {foto.download_url && (
                                <a
                                    href={foto.download_url}
                                    className="inline-flex min-h-11 items-center justify-center rounded-md border border-border text-sm font-medium underline-offset-4 hover:underline"
                                >
                                    Baixar original
                                </a>
                            )}
                        </li>
                    ))}
                </ul>

                <Link
                    href={VitrineFotosController.show.url(pedido.album_id)}
                    className="text-sm underline underline-offset-4"
                >
                    Voltar às fotos do evento
                </Link>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
PedidoFotos.layout = {
    breadcrumbs: [{ title: 'Fotos dos eventos', href: '/fotos' }],
};
