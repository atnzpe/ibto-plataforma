/**
 * VENDA DE FOTOS — COMANDO GERAL (#90): o interruptor liga/desliga, os álbuns à
 * venda e os pedidos (confirmar o Pix ou cancelar). Lista sem o nome do aluno
 * (LGPD): o identificador do Pix (IBTOFOT…) basta para conferir no extrato.
 */
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import AlbunsVendaController from '@/actions/App/Http/Controllers/Fotos/AlbunsVendaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';

/** Um álbum na lista. */
type Album = {
    id: number;
    titulo: string;
    curso: string;
    fotografo: string;
    fotos: number;
    preco_centavos: number;
    expira_em: string;
    situacao: string;
};
/** Um pedido na lista. */
type Pedido = {
    id: number;
    txid: string;
    album: string;
    fotos: number;
    valor_centavos: number;
    situacao: string;
    criado_em: string | null;
};

// Rótulo escrito de cada situação do pedido (não só cor).
const SITUACAO: Record<string, string> = {
    aguardando_pagamento: 'Aguardando pagamento',
    pago: 'Pago',
    cancelado: 'Cancelado',
};

/**
 * @param ligada true = a venda está ligada (os alunos veem as fotos dos cursos que fizeram).
 * @param albuns Os álbuns à venda.
 * @param pedidos Os pedidos, aguardando primeiro.
 * @param formas As formas de pagamento ativas (para a confirmação).
 * @returns A tela da venda de fotos.
 */
export default function VendaFotosAdmin({
    ligada,
    albuns,
    pedidos,
    formas,
}: {
    ligada: boolean;
    albuns: Album[];
    pedidos: Pedido[];
    formas: { id: number; nome: string }[];
}) {
    const { sucesso, errors } = usePage<{
        sucesso?: string;
        errors: Record<string, string>;
    }>().props;
    // A forma "PIX", se existir, já vem escolhida na confirmação.
    const pix =
        formas.find((forma) => forma.nome.toUpperCase() === 'PIX')?.id ??
        formas[0]?.id;

    return (
        <>
            <Head title="Venda de fotos" />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Venda de fotos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        O fotógrafo sobe as fotos do evento; quem fez o curso vê
                        as prévias com marca d'água, compra pelo Pix e baixa o
                        original. As fotos são apagadas na data de expiração.
                    </p>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* O interruptor: desligada, nada aparece para os alunos. */}
                <section
                    aria-labelledby="t-interruptor"
                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4"
                >
                    <div>
                        <h2 id="t-interruptor" className="font-semibold">
                            Venda de fotos: {ligada ? 'LIGADA' : 'DESLIGADA'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {ligada
                                ? 'Os alunos veem e compram as fotos dos cursos que fizeram.'
                                : 'Nada aparece para os alunos. O fotógrafo já pode subir as fotos.'}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant={ligada ? 'outline' : 'default'}
                        className="min-h-11"
                        onClick={() =>
                            router.post(
                                AlbunsVendaController.interruptor.url(),
                                { ligada: !ligada },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {ligada ? 'Desligar a venda' : 'Ligar a venda'}
                    </Button>
                </section>

                <section
                    aria-labelledby="t-albuns"
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <h2 id="t-albuns" className="text-lg font-semibold">
                            Álbuns à venda
                        </h2>
                        <Button asChild className="w-full sm:w-auto">
                            <Link href={AlbunsVendaController.create.url()}>
                                Novo álbum
                            </Link>
                        </Button>
                    </div>
                    {albuns.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nenhum álbum ainda.
                        </p>
                    )}
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {albuns.map((album) => (
                            <li
                                key={album.id}
                                className="flex flex-col gap-1 rounded-md border border-border p-4 text-sm"
                            >
                                <strong>{album.titulo}</strong>
                                <span className="text-muted-foreground">
                                    {album.curso}
                                </span>
                                <span>
                                    {album.fotos} fotos ·{' '}
                                    {formatarReais(album.preco_centavos)} cada ·
                                    fotógrafo: {album.fotografo}
                                </span>
                                <span className="text-muted-foreground">
                                    Expira em {album.expira_em} ·{' '}
                                    {album.situacao}
                                </span>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-2 min-h-11"
                                >
                                    <Link
                                        href={AlbunsVendaController.edit.url(
                                            album.id,
                                        )}
                                    >
                                        Editar
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                </section>

                <section
                    aria-labelledby="t-pedidos"
                    className="flex flex-col gap-3"
                >
                    <h2 id="t-pedidos" className="text-lg font-semibold">
                        Pedidos
                    </h2>
                    <InputError message={errors.pedido} />
                    {pedidos.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nenhum pedido ainda.
                        </p>
                    )}
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {pedidos.map((pedido) => (
                            <li
                                key={pedido.id}
                                className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                            >
                                <span className="flex flex-wrap items-center justify-between gap-2">
                                    <strong className="font-mono">
                                        {pedido.txid}
                                    </strong>
                                    <span className="rounded bg-muted px-1.5 py-0.5 text-xs font-medium">
                                        {SITUACAO[pedido.situacao] ??
                                            pedido.situacao}
                                    </span>
                                </span>
                                <span>
                                    {pedido.album} · {pedido.fotos} fotos ·{' '}
                                    {formatarReais(pedido.valor_centavos)}
                                </span>
                                <span className="text-muted-foreground">
                                    Pedido em {pedido.criado_em}
                                </span>
                                {/* Aguardando: confirmar (com o dia e a forma) ou cancelar. */}
                                {pedido.situacao === 'aguardando_pagamento' && (
                                    <div className="flex flex-col gap-2">
                                        <Form
                                            {...AlbunsVendaController.confirmar.form(
                                                pedido.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                            className="flex flex-wrap items-end gap-2"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <label className="grid gap-1 text-xs">
                                                        Pago em
                                                        <input
                                                            type="date"
                                                            name="pago_em"
                                                            defaultValue={new Date()
                                                                .toISOString()
                                                                .slice(0, 10)}
                                                            className="h-9 rounded-md border border-input px-2"
                                                        />
                                                    </label>
                                                    <label className="grid gap-1 text-xs">
                                                        Forma
                                                        <select
                                                            name="forma_pagamento_id"
                                                            defaultValue={pix}
                                                            className="h-9 rounded-md border border-input px-2"
                                                        >
                                                            {formas.map(
                                                                (forma) => (
                                                                    <option
                                                                        key={
                                                                            forma.id
                                                                        }
                                                                        value={
                                                                            forma.id
                                                                        }
                                                                    >
                                                                        {
                                                                            forma.nome
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </label>
                                                    <Button
                                                        disabled={processing}
                                                        className="min-h-11"
                                                    >
                                                        Confirmar pagamento
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="min-h-11 self-start"
                                            onClick={() =>
                                                router.post(
                                                    AlbunsVendaController.cancelar.url(
                                                        pedido.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Cancelar pedido
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
VendaFotosAdmin.layout = {
    breadcrumbs: [{ title: 'Venda de fotos', href: '/comando-geral/fotos' }],
};
