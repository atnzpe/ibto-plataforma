/**
 * PROGRAMAS DE AULAS REGULARES — COMANDO GERAL (#80): a lista dos programas
 * (Krav Maga, Kapap...), com o valor, quantos estão em dia e quantos aguardam a
 * 1ª mensalidade. Botões para criar um programa e para a fila de mensalidades.
 * Responsivo: cartões no celular, tabela a partir de 1024 px.
 */
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';

/** Um programa na lista. */
type Programa = {
    id: number;
    titulo: string;
    modalidade: string;
    valor_mensal_centavos: number;
    ativas: number;
    aguardando: number;
    vagas: number | null;
    ativo: boolean;
};

/**
 * @param programas Os programas cadastrados.
 * @returns A lista.
 */
export default function ProgramasAdmin({
    programas,
}: {
    programas: Programa[];
}) {
    return (
        <>
            <Head title="Programas (aulas regulares)" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Programas (aulas regulares)
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Aulas contínuas com mensalidade: Krav Maga, Kapap,
                            Combate corpo a corpo...
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href="/comando-geral/mensalidades">
                                Mensalidades
                            </Link>
                        </Button>
                        <Button asChild className="min-h-11">
                            <Link href="/comando-geral/programas/create">
                                Novo programa
                            </Link>
                        </Button>
                    </div>
                </div>

                {programas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nenhum programa ainda. Clique em "Novo programa".
                    </p>
                ) : (
                    <table className="tabela-cartoes w-full text-sm">
                        <thead>
                            <tr className="text-left">
                                <th scope="col" className="py-2">
                                    Programa
                                </th>
                                <th scope="col" className="py-2">
                                    Modalidade
                                </th>
                                <th scope="col" className="py-2">
                                    Mensalidade
                                </th>
                                <th scope="col" className="py-2">
                                    Em dia
                                </th>
                                <th scope="col" className="py-2">
                                    Aguardando
                                </th>
                                <th scope="col" className="py-2">
                                    Situação
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {programas.map((p) => (
                                <tr key={p.id} className="border-t">
                                    <td data-rotulo="Programa" className="py-2">
                                        <Link
                                            href={`/comando-geral/programas/${p.id}/edit`}
                                            className="inline-flex min-h-11 items-center underline underline-offset-4"
                                        >
                                            {p.titulo}
                                        </Link>
                                    </td>
                                    <td
                                        data-rotulo="Modalidade"
                                        className="py-2"
                                    >
                                        {p.modalidade}
                                    </td>
                                    <td
                                        data-rotulo="Mensalidade"
                                        className="py-2"
                                    >
                                        {formatarReais(p.valor_mensal_centavos)}
                                    </td>
                                    <td data-rotulo="Em dia" className="py-2">
                                        {p.ativas}
                                        {p.vagas !== null
                                            ? ` / ${p.vagas} vagas`
                                            : ''}
                                    </td>
                                    <td
                                        data-rotulo="Aguardando"
                                        className="py-2"
                                    >
                                        {p.aguardando}
                                    </td>
                                    <td data-rotulo="Situação" className="py-2">
                                        {p.ativo ? 'Aberto' : 'Desativado'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </>
    );
}

ProgramasAdmin.layout = {
    breadcrumbs: [
        {
            title: 'Programas (aulas regulares)',
            href: '/comando-geral/programas',
        },
    ],
};
