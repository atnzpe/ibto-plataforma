/**
 * Tela de LISTA de usuários (#36): busca por nome ou e-mail e mostra os
 * papéis de cada pessoa. Só para quem tem "usuarios.gerenciar".
 */
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit, index } from '@/routes/admin/usuarios';

/** Uma pessoa na lista. */
type UsuarioLista = {
    id: number;
    nome: string;
    email: string;
    papeis: string[];
};

/** Página da lista, no formato de paginação do Laravel (20 por página). */
type Pagina = {
    data: UsuarioLista[];
    // Links "anterior", números e "próxima"; url null = sem link (ex.: já na primeira).
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

/**
 * @param usuarios A página atual de usuários.
 * @param busca O texto buscado (vazio = todos).
 * @returns A tela da lista.
 */
export default function UsuariosIndex({
    usuarios,
    busca,
}: {
    usuarios: Pagina;
    busca: string;
}) {
    const { sucesso } = usePage().props;

    return (
        <>
            <Head title="Usuários" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Usuários
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {usuarios.total} pessoas com conta. Cada pessoa cria a
                        própria conta; aqui você marca os papéis dela.
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

                {/* Busca: GET na própria lista, com ?busca=texto no endereço. */}
                <Form
                    action={index().url}
                    method="get"
                    className="flex max-w-md gap-2"
                    role="search"
                >
                    <label htmlFor="busca" className="sr-only">
                        Buscar por nome ou e-mail
                    </label>
                    <Input
                        id="busca"
                        name="busca"
                        defaultValue={busca}
                        placeholder="Buscar por nome ou e-mail"
                    />
                    <Button type="submit" variant="secondary">
                        Buscar
                    </Button>
                </Form>

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Lista de usuários</caption>
                        <thead className="bg-muted/50">
                            <tr>
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
                                    E-mail
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Papéis
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
                            {usuarios.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        Ninguém encontrado.
                                    </td>
                                </tr>
                            )}
                            {usuarios.data.map((usuario) => (
                                <tr
                                    key={usuario.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2">
                                        {usuario.nome}
                                    </td>
                                    <td className="px-4 py-2">
                                        {usuario.email}
                                    </td>
                                    <td className="px-4 py-2">
                                        {usuario.papeis.length > 0 ? (
                                            usuario.papeis.join(', ')
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Nenhum
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <Link
                                            href={edit(usuario.id)}
                                            className="underline underline-offset-4"
                                            aria-label={`Papéis de ${usuario.nome}`}
                                        >
                                            Papéis
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Páginas (só aparece quando há mais de 20 pessoas). */}
                {usuarios.links.length > 3 && (
                    <nav aria-label="Páginas" className="flex flex-wrap gap-2">
                        {usuarios.links.map((link, posicao) =>
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
UsuariosIndex.layout = {
    breadcrumbs: [{ title: 'Usuários', href: index() }],
};
