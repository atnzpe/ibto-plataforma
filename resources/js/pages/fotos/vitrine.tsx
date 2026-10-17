/**
 * VITRINE DAS FOTOS DO EVENTO (#90): as prévias com marca d'água de um curso que
 * a pessoa fez. Toque nas fotos para escolher, veja o total e compre (Pix).
 * Os pedidos anteriores aparecem no topo, para voltar ao Pix ou baixar.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import VitrineFotosController from '@/actions/App/Http/Controllers/Fotos/VitrineFotosController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';

/** O álbum com as prévias. */
type Album = {
    id: number;
    titulo: string;
    curso: string;
    preco_centavos: number;
    expira_em: string;
    fotos: { id: number; previa_url: string }[];
};
/** Um pedido anterior desta pessoa. */
type MeuPedido = { id: number; situacao: string; valor_centavos: number };

// Rótulo escrito de cada situação.
const SITUACAO: Record<string, string> = {
    aguardando_pagamento: 'aguardando pagamento',
    pago: 'pago',
    cancelado: 'cancelado',
};

/**
 * @param album O álbum, com o preço por foto e as prévias.
 * @param meus_pedidos Os pedidos desta pessoa neste álbum.
 * @returns A vitrine.
 */
export default function VitrineFotos({
    album,
    meus_pedidos,
}: {
    album: Album;
    meus_pedidos: MeuPedido[];
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    // As fotos escolhidas (ids).
    const [escolhidas, setEscolhidas] = useState<number[]>([]);
    // true enquanto envia o pedido.
    const [enviando, setEnviando] = useState(false);

    /**
     * Marca ou desmarca uma foto.
     *
     * @param id O número da foto.
     */
    function alternar(id: number) {
        setEscolhidas((atual) =>
            atual.includes(id)
                ? atual.filter((item) => item !== id)
                : [...atual, id],
        );
    }

    return (
        <>
            <Head title={`Fotos: ${album.titulo}`} />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Fotos: {album.titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {album.curso} · {formatarReais(album.preco_centavos)}{' '}
                        por foto · disponíveis até {album.expira_em}. Toque nas
                        fotos para escolher.
                    </p>
                </div>

                {meus_pedidos.length > 0 && (
                    <ul
                        className="flex flex-wrap gap-2 text-sm"
                        aria-label="Meus pedidos neste álbum"
                    >
                        {meus_pedidos.map((pedido) => (
                            <li key={pedido.id}>
                                <Link
                                    href={VitrineFotosController.pedido.url(
                                        pedido.id,
                                    )}
                                    className="inline-flex min-h-11 items-center rounded-md border border-border px-3 underline-offset-4 hover:underline"
                                >
                                    Pedido nº {pedido.id}:{' '}
                                    {SITUACAO[pedido.situacao] ??
                                        pedido.situacao}{' '}
                                    ({formatarReais(pedido.valor_centavos)})
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {album.fotos.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        O fotógrafo ainda não enviou as fotos deste evento.
                    </p>
                )}

                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {album.fotos.map((foto) => {
                        const marcada = escolhidas.includes(foto.id);

                        return (
                            <li key={foto.id}>
                                {/* Botão com estado escrito para o leitor de tela (aria-pressed). */}
                                <button
                                    type="button"
                                    aria-pressed={marcada}
                                    onClick={() => alternar(foto.id)}
                                    className={`relative block w-full overflow-hidden rounded-md ring-offset-2 focus-visible:outline-2 ${marcada ? 'ring-4 ring-[#6b5d3a]' : ''}`}
                                >
                                    <img
                                        src={foto.previa_url}
                                        alt={`Foto ${foto.id} do evento (prévia com marca d'água)`}
                                        loading="lazy"
                                        className="aspect-square w-full object-cover"
                                    />
                                    <span className="absolute top-2 right-2 rounded bg-black/70 px-2 py-0.5 text-xs text-white">
                                        {marcada ? '✓ Escolhida' : 'Escolher'}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>

                {/* Barra do total, grudada no rodapé ACIMA da barra de gestos do celular (safe area), com o botão de comprar. */}
                <div style={{ bottom: 'env(safe-area-inset-bottom)' }} className="sticky flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-background p-4">
                    <p className="font-semibold" aria-live="polite">
                        {escolhidas.length} foto(s) ·{' '}
                        {formatarReais(
                            escolhidas.length * album.preco_centavos,
                        )}
                    </p>
                    <InputError message={errors.fotos} />
                    <Button
                        type="button"
                        disabled={escolhidas.length === 0 || enviando}
                        className="min-h-11"
                        onClick={() =>
                            router.post(
                                VitrineFotosController.comprar.url(album.id),
                                { fotos: escolhidas },
                                {
                                    onStart: () => setEnviando(true),
                                    onFinish: () => setEnviando(false),
                                },
                            )
                        }
                    >
                        Comprar com Pix
                    </Button>
                </div>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
VitrineFotos.layout = {
    breadcrumbs: [{ title: 'Meus cursos', href: '/meus-cursos' }],
};
