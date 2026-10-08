/**
 * Tela de LISTA das áreas de atuação (#37), só para administradores.
 *
 * Mostra todas as áreas (ativas e desativadas), com o botão "Nova área" e um
 * link "Editar" em cada linha. Desativar é feito pela edição (não há apagar).
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/cadastros/areas-atuacao';
import type { AreaAtuacao } from '@/types/cadastros';

/**
 * @param areas Todas as áreas, em ordem de nome. Ex.: [{ id: 4, nome: 'Krav Maga', ativa: true }]
 * @returns A tela da lista.
 */
export default function AreasAtuacaoIndex({ areas }: { areas: AreaAtuacao[] }) {
    // Mensagem da última ação (ex.: 'Área "Selva" atualizada.'), ou null.
    const { sucesso } = usePage().props;

    // Quantas estão ativas, para o resumo no topo.
    const ativas = areas.filter((area) => area.ativa).length;

    return (
        <>
            <Head title="Áreas de atuação" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Áreas de atuação
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {areas.length} áreas cadastradas, {ativas} ativas.
                            Desativar tira a área das listas de escolha, sem
                            apagar.
                        </p>
                    </div>

                    <Button asChild>
                        <Link href={create()}>Nova área</Link>
                    </Button>
                </div>

                {/* Aviso de sucesso. role="status": o leitor de tela anuncia sem tirar o foco. */}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full text-left text-sm">
                        {/* Título da tabela só para leitor de tela (visualmente, o h1 já diz). */}
                        <caption className="sr-only">
                            Lista das áreas de atuação
                        </caption>
                        <thead className="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Imagem
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Nome
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Situação
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    <span className="sr-only">Ações</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {areas.map((area) => (
                                <tr
                                    key={area.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2">
                                        {/* Miniatura da imagem; sem imagem, o aviso escrito.
                                            alt="" : a imagem é decorativa aqui, o nome está ao lado. */}
                                        {area.imagem_url ? (
                                            <img
                                                src={area.imagem_url}
                                                alt=""
                                                loading="lazy"
                                                className="h-10 w-16 rounded border border-border object-cover"
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                Sem imagem
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2">{area.nome}</td>
                                    <td className="px-4 py-2">
                                        {/* Situação escrita (não só cor), para quem não distingue cores. */}
                                        {area.ativa ? (
                                            <span className="text-green-700 dark:text-green-400">
                                                Ativa
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Desativada
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Link
                                            href={edit(area.id)}
                                            className="underline underline-offset-4"
                                            // O nome da área no rótulo: o leitor de tela diz "Editar Krav Maga".
                                            aria-label={`Editar ${area.nome}`}
                                        >
                                            Editar
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
AreasAtuacaoIndex.layout = {
    breadcrumbs: [{ title: 'Áreas de atuação', href: index() }],
};
