/**
 * Tela de LISTA de filiados (#37), com filtro por equipe e 20 por página.
 * LGPD: CPF, nascimento e telefone NÃO aparecem aqui (só no formulário de edição).
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { create, edit, index } from '@/routes/cadastros/filiados';

/** Um filiado na lista. */
type FiliadoLista = {
    id: number;
    registro: string;
    nome: string | null;
    equipe: string | null;
    nivel: string | null;
    local: string;
    status: string;
};

/** Página da lista, no formato de paginação do Laravel. */
type Pagina = {
    data: FiliadoLista[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

// Visual da caixa de escolha (igual aos campos de texto).
const classeSelect =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param filiados A página atual de filiados.
 * @param equipes Todas as equipes, para o filtro.
 * @param equipe_escolhida A equipe do filtro, ou null (todas).
 * @returns A tela da lista.
 */
export default function FiliadosIndex({
    filiados,
    equipes,
    equipe_escolhida,
}: {
    filiados: Pagina;
    equipes: { id: number; nome: string }[];
    equipe_escolhida: number | null;
}) {
    const { sucesso } = usePage().props;

    /**
     * Troca o filtro: recarrega a lista com ?equipe=N (ou sem filtro).
     *
     * @param valor O número da equipe escolhida, ou '' para todas.
     */
    const filtrar = (valor: string) => {
        router.get(index().url, valor ? { equipe: valor } : {}, {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title="Filiados" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Filiados
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {filiados.total} filiados. Cadastrar um filiado cria
                            também a conta dele no app.
                        </p>
                    </div>

                    <Button asChild>
                        <Link href={create()}>Novo filiado</Link>
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

                <div className="flex items-center gap-2">
                    <Label htmlFor="filtro-equipe">Equipe</Label>
                    <select
                        id="filtro-equipe"
                        value={equipe_escolhida ?? ''}
                        onChange={(evento) => filtrar(evento.target.value)}
                        className={classeSelect}
                    >
                        <option value="">Todas</option>
                        {equipes.map((equipe) => (
                            <option key={equipe.id} value={equipe.id}>
                                {equipe.nome}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Lista de filiados</caption>
                        <thead className="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Registro
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
                                    Equipe
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Nível
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Local
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
                            {filiados.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        Nenhum filiado ainda.
                                    </td>
                                </tr>
                            )}
                            {filiados.data.map((filiado) => (
                                <tr
                                    key={filiado.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2 font-mono">
                                        {filiado.registro}
                                    </td>
                                    <td className="px-4 py-2">
                                        {filiado.nome ?? '—'}
                                    </td>
                                    <td className="px-4 py-2">
                                        {filiado.equipe ?? (
                                            <span className="text-muted-foreground">
                                                Sem equipe
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2">
                                        {filiado.nivel ?? '—'}
                                    </td>
                                    <td className="px-4 py-2">
                                        {filiado.local}
                                    </td>
                                    <td className="px-4 py-2">
                                        {filiado.status === 'ativo'
                                            ? 'Ativo'
                                            : 'Inativo'}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Link
                                            href={edit(filiado.id)}
                                            className="underline underline-offset-4"
                                            aria-label={`Editar ${filiado.registro}`}
                                        >
                                            Editar
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Páginas (só aparece quando há mais de 20 filiados). */}
                {filiados.links.length > 3 && (
                    <nav aria-label="Páginas" className="flex flex-wrap gap-2">
                        {filiados.links.map((link, posicao) =>
                            link.url ? (
                                <Link
                                    key={posicao}
                                    href={link.url}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className="rounded border border-border px-3 py-1 text-sm aria-[current=page]:font-semibold"
                                    // O Laravel manda os rótulos com HTML (ex.: "&laquo; Anterior").
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : null,
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
FiliadosIndex.layout = {
    breadcrumbs: [{ title: 'Filiados', href: index() }],
};
