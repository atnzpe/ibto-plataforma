/**
 * FOTOS DOS EVENTOS (#90, decisão do Gleyson em 17/10/2026): o item do menu do
 * aluno. Mostra os álbuns à venda dos cursos em que a pessoa tem matrícula
 * confirmada (a turma) e todos os pedidos dela: Pix pendente, pago (com o
 * download) ou cancelado. Responsivo: cartões no celular, sem rolagem lateral.
 */
import { Head, Link } from '@inertiajs/react';
import { formatarReais } from '@/lib/dinheiro';

/** Um álbum que a pessoa pode ver. */
type Album = {
    id: number;
    titulo: string;
    curso: string;
    preco_centavos: number;
    expira_em: string;
    fotos: number;
};
/** Um pedido da pessoa. */
type Pedido = {
    id: number;
    album: string;
    situacao: string;
    valor_centavos: number;
    data: string | null;
    pode_baixar: boolean;
};

// Rótulo escrito de cada situação do pedido.
const SITUACAO: Record<string, string> = {
    aguardando_pagamento: 'Aguardando o Pix',
    pago: 'Pago',
    cancelado: 'Cancelado',
};

/**
 * @param ligada Se a loja de fotos está ligada.
 * @param albuns Os álbuns da turma da pessoa.
 * @param pedidos Os pedidos dela (os 50 mais novos).
 * @returns A tela "Fotos dos eventos".
 */
export default function FotosDosEventos({
    ligada,
    albuns,
    pedidos,
}: {
    ligada: boolean;
    albuns: Album[];
    pedidos: Pedido[];
}) {
    return (
        <>
            <Head title="Fotos dos eventos" />
            <div className="flex flex-col gap-8 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Fotos dos eventos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        As fotos dos cursos de que você participou. Só a sua
                        turma vê estes álbuns.
                    </p>
                </div>

                {/* Loja desligada: explica (os pedidos antigos continuam abaixo). */}
                {!ligada && (
                    <p
                        role="status"
                        className="rounded-md border border-amber-700 bg-amber-50 p-3 text-sm text-amber-900"
                    >
                        A venda de fotos está pausada no momento.
                    </p>
                )}

                <section
                    aria-labelledby="t-albuns"
                    className="flex flex-col gap-3"
                >
                    <h2 id="t-albuns" className="font-medium">
                        Álbuns da sua turma
                    </h2>
                    {albuns.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nenhum álbum à venda para você agora. Quando o
                            fotógrafo do seu curso publicar, ele aparece aqui.
                        </p>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {albuns.map((album) => (
                                <li
                                    key={album.id}
                                    className="flex flex-col gap-1 rounded-md border p-4 text-sm"
                                >
                                    <p className="font-medium">
                                        {album.titulo}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {album.curso}
                                    </p>
                                    <p>
                                        {album.fotos} fotos ·{' '}
                                        {formatarReais(album.preco_centavos)}{' '}
                                        cada · até {album.expira_em}
                                    </p>
                                    <Link
                                        href={`/fotos/albuns/${album.id}`}
                                        className="mt-2 inline-flex min-h-11 items-center self-start rounded-md border px-4 font-medium"
                                    >
                                        Ver as fotos
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section
                    aria-labelledby="t-pedidos"
                    className="flex flex-col gap-3"
                >
                    <h2 id="t-pedidos" className="font-medium">
                        Meus pedidos
                    </h2>
                    {pedidos.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Você ainda não comprou fotos.
                        </p>
                    ) : (
                        <table className="tabela-cartoes w-full text-sm">
                            <thead>
                                <tr className="text-left">
                                    <th scope="col" className="py-2">
                                        Pedido
                                    </th>
                                    <th scope="col" className="py-2">
                                        Álbum
                                    </th>
                                    <th scope="col" className="py-2">
                                        Valor
                                    </th>
                                    <th scope="col" className="py-2">
                                        Situação
                                    </th>
                                    <th scope="col" className="py-2">
                                        <span className="sr-only">Abrir</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {pedidos.map((pedido) => (
                                    <tr key={pedido.id} className="border-t">
                                        <td
                                            data-rotulo="Pedido"
                                            className="py-2"
                                        >
                                            #{pedido.id}{' '}
                                            {pedido.data
                                                ? `· ${pedido.data}`
                                                : ''}
                                        </td>
                                        <td
                                            data-rotulo="Álbum"
                                            className="py-2"
                                        >
                                            {pedido.album}
                                        </td>
                                        <td
                                            data-rotulo="Valor"
                                            className="py-2"
                                        >
                                            {formatarReais(
                                                pedido.valor_centavos,
                                            )}
                                        </td>
                                        <td
                                            data-rotulo="Situação"
                                            className="py-2"
                                        >
                                            {SITUACAO[pedido.situacao] ??
                                                pedido.situacao}
                                        </td>
                                        <td className="py-2">
                                            {/* Aguardando: volta ao Pix. Pago no prazo: baixar. */}
                                            <Link
                                                href={`/fotos/pedidos/${pedido.id}`}
                                                className="inline-flex min-h-11 items-center underline underline-offset-4"
                                            >
                                                {pedido.pode_baixar
                                                    ? 'Baixar as fotos'
                                                    : pedido.situacao ===
                                                        'aguardando_pagamento'
                                                      ? 'Ver o Pix'
                                                      : 'Abrir'}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </>
    );
}

FotosDosEventos.layout = {
    breadcrumbs: [{ title: 'Fotos dos eventos', href: '/fotos' }],
};
