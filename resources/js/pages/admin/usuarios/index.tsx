/**
 * Tela de LISTA de usuários (#36): busca por nome ou e-mail, mostra os papéis
 * de cada pessoa e as AÇÕES na conta (pedido do Gleyson, 10/10/2026):
 * - Papéis: marcar o que a pessoa pode fazer;
 * - Desativar / Reativar: "apagar sem apagar" (a conta fica, mas não entra);
 * - Nova senha: manda o link para a PESSOA criar a senha (ninguém vê senha);
 * - Convite: só para quem ainda não criou a senha.
 * Só para quem tem "usuarios.gerenciar".
 *
 * Sem rolagem lateral: um CARTÃO por pessoa (1 coluna no celular, 2 na tela
 * larga) e os botões em grade de colunas iguais.
 */
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    convite,
    desativar,
    edit,
    index,
    novaSenha,
    reativar,
} from '@/routes/admin/usuarios';

/** Uma pessoa na lista. */
type UsuarioLista = {
    id: number;
    nome: string;
    // null = filiado importado sem e-mail (cadastro incompleto).
    email: string | null;
    papeis: string[];
    // false = conta desativada (não entra).
    ativa: boolean;
    // true = já criou a senha (não precisa de convite).
    senha_criada: boolean;
    // true = é a própria pessoa logada (não pode se desativar).
    eu: boolean;
};

/** Página da lista, no formato de paginação do Laravel (20 por página). */
type Pagina = {
    data: UsuarioLista[];
    // Links "anterior", números e "próxima"; url null = sem link (ex.: já na primeira).
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

/**
 * Faz uma ação na conta (POST), pedindo confirmação antes.
 *
 * @param url O endereço da ação. Ex.: '/admin/usuarios/7/desativar'
 * @param pergunta O texto da confirmação. Ex.: 'Desativar a conta de Fulano?'
 */
function confirmarEEnviar(url: string, pergunta: string) {
    // Cancelou na janela de confirmação: não faz nada.
    if (!window.confirm(pergunta)) {
        return;
    }

    // preserveScroll: a lista não pula para o topo depois da ação.
    router.post(url, {}, { preserveScroll: true });
}

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
    // Mensagem de sucesso e erros da última ação (o erro das ações vem em "conta").
    const { sucesso, errors } = usePage().props;

    return (
        <>
            <Head title="Usuários" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Usuários
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {usuarios.total} pessoas com conta. Aqui você marca os
                        papéis, desativa ou reativa a conta e manda o link de
                        nova senha ou o convite. Ninguém vê a senha de ninguém.
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
                {/* Ação recusada (ex.: desativar a própria conta): o motivo com o código. */}
                {errors.conta && (
                    <p
                        role="alert"
                        className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                    >
                        {errors.conta}
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

                {usuarios.data.length === 0 && (
                    <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Ninguém encontrado.
                    </p>
                )}

                {/* Um cartão por pessoa: 1 coluna no celular, 2 na tela larga. */}
                <ul className="grid gap-3 lg:grid-cols-2">
                    {usuarios.data.map((usuario) => (
                        <li
                            key={usuario.id}
                            className={`flex flex-col gap-3 rounded-md border border-border p-4 text-sm ${
                                usuario.ativa ? '' : 'bg-muted/40'
                            }`}
                        >
                            <div className="flex flex-col gap-1">
                                <span className="flex flex-wrap items-center gap-2">
                                    <strong className="text-base">
                                        {usuario.nome}
                                    </strong>
                                    {/* Selos escritos (não só cor): situação da conta. */}
                                    {!usuario.ativa && (
                                        <span className="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-900 dark:bg-red-900/40 dark:text-red-100">
                                            Desativada
                                        </span>
                                    )}
                                    {usuario.ativa && !usuario.senha_criada && (
                                        <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                                            Ainda não criou a senha
                                        </span>
                                    )}
                                </span>
                                {/* overflow-wrap: e-mail comprido quebra a linha em vez de empurrar a tela. */}
                                <span className="[overflow-wrap:anywhere] text-muted-foreground">
                                    {usuario.email ?? 'Sem e-mail'}
                                </span>
                                <span>
                                    <span className="text-muted-foreground">
                                        Papéis:{' '}
                                    </span>
                                    {usuario.papeis.length > 0
                                        ? usuario.papeis.join(', ')
                                        : 'Nenhum'}
                                </span>
                            </div>

                            {/* Ações em grade de colunas iguais (2 x 2 no celular, 4 lado a lado na tela média). */}
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <Button
                                    asChild
                                    size="sm"
                                    variant="outline"
                                    className="min-h-11"
                                >
                                    <Link
                                        href={edit(usuario.id)}
                                        aria-label={`Papéis de ${usuario.nome}`}
                                    >
                                        Papéis
                                    </Link>
                                </Button>

                                {/* Nova senha: só conta ativa e com e-mail. */}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="min-h-11"
                                    disabled={!usuario.ativa || !usuario.email}
                                    onClick={() =>
                                        confirmarEEnviar(
                                            novaSenha(usuario.id).url,
                                            `Mandar para ${usuario.nome} o link de criar uma senha nova? A senha atual continua valendo até a pessoa trocar.`,
                                        )
                                    }
                                    aria-label={`Mandar link de nova senha para ${usuario.nome}`}
                                >
                                    Nova senha
                                </Button>

                                {/* Convite: só conta ativa, com e-mail e sem senha criada. */}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="min-h-11"
                                    disabled={
                                        !usuario.ativa ||
                                        !usuario.email ||
                                        usuario.senha_criada
                                    }
                                    onClick={() =>
                                        confirmarEEnviar(
                                            convite(usuario.id).url,
                                            `Mandar o convite para ${usuario.nome} criar a senha?`,
                                        )
                                    }
                                    aria-label={`Mandar convite para ${usuario.nome}`}
                                >
                                    Convite
                                </Button>

                                {/* Desativar (ativa) ou Reativar (desativada). A própria conta não se desativa. */}
                                {usuario.ativa ? (
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        className="min-h-11"
                                        disabled={usuario.eu}
                                        onClick={() =>
                                            confirmarEEnviar(
                                                desativar(usuario.id).url,
                                                `Desativar a conta de ${usuario.nome}? A pessoa não vai conseguir entrar, mas nada é apagado: dá para reativar depois.`,
                                            )
                                        }
                                        aria-label={`Desativar a conta de ${usuario.nome}`}
                                    >
                                        Desativar
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        className="min-h-11"
                                        onClick={() =>
                                            confirmarEEnviar(
                                                reativar(usuario.id).url,
                                                `Reativar a conta de ${usuario.nome}?`,
                                            )
                                        }
                                        aria-label={`Reativar a conta de ${usuario.nome}`}
                                    >
                                        Reativar
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>

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
