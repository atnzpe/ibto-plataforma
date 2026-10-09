/**
 * Tela de LISTA das equipes filiadas (#37), só para administradores.
 * Mostra logo, nome, cidade/UF, áreas e situação. LGPD: contato e documento
 * do responsável NÃO aparecem aqui, só no formulário de edição.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/cadastros/equipes';
import type { EquipeLista } from '@/types/cadastros';

// Cor de cada situação. A situação também vai ESCRITA (não só a cor), para quem não distingue cores.
const CORES_SITUACAO: Record<string, string> = {
    ativa: 'text-green-700 dark:text-green-400',
    em_homologacao: 'text-amber-700 dark:text-amber-400',
    inativa: 'text-muted-foreground',
};

/**
 * @param equipes Todas as equipes, em ordem de nome.
 * @returns A tela da lista.
 */
export default function EquipesIndex({ equipes }: { equipes: EquipeLista[] }) {
    // Mensagem da última ação (ex.: 'Equipe "Vulcan" atualizada.'), ou null.
    const { sucesso } = usePage().props;

    // Quantas em cada situação, para o resumo no topo.
    const ativas = equipes.filter((e) => e.status === 'ativa').length;
    const emHomologacao = equipes.filter(
        (e) => e.status === 'em_homologacao',
    ).length;

    return (
        <>
            <Head title="Equipes" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Equipes filiadas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {equipes.length} equipes: {ativas} ativas,{' '}
                            {emHomologacao} em homologação.
                        </p>
                    </div>

                    <Button asChild>
                        <Link href={create()}>Nova equipe</Link>
                    </Button>
                </div>

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
                        <caption className="sr-only">
                            Lista das equipes filiadas
                        </caption>
                        <thead className="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Logo
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Equipe
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Cidade
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Áreas
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
                            {equipes.map((equipe) => (
                                <tr
                                    key={equipe.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2">
                                        {/* alt="": decorativa aqui, o nome está ao lado. */}
                                        {equipe.logo_url ? (
                                            <img
                                                src={equipe.logo_url}
                                                alt=""
                                                loading="lazy"
                                                className="size-10 rounded border border-border object-contain"
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                Sem logo
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2">{equipe.nome}</td>
                                    <td className="px-4 py-2">
                                        {equipe.local ?? (
                                            <span className="text-muted-foreground">
                                                A preencher
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2">
                                        {equipe.areas.length > 0 ? (
                                            equipe.areas.join(', ')
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Nenhuma
                                            </span>
                                        )}
                                    </td>
                                    <td
                                        className={`px-4 py-2 ${CORES_SITUACAO[equipe.status] ?? ''}`}
                                    >
                                        {equipe.status_rotulo}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Link
                                            href={edit(equipe.id)}
                                            className="underline underline-offset-4"
                                            aria-label={`Editar ${equipe.nome}`}
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
EquipesIndex.layout = {
    breadcrumbs: [{ title: 'Equipes', href: index() }],
};
