/**
 * BLOG no Comando Geral (#61): a lista dos posts (notícias e eventos), com os
 * filtros de busca, categoria, situação e período (De / Até, como em todas as
 * telas com data). Abaixo de 1024 px a tabela vira cartões (tabela-cartoes):
 * nenhuma tela rola de lado.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
// Filtro "De / Até" (o mesmo em todas as telas com data).
import FiltroPeriodo from '@/components/filtro-periodo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dataBr } from '@/pages/catalogo/index';
import { create, edit, index } from '@/routes/comando-geral/blog';

/** Uma opção de lista fixa. Ex.: { valor: 'evento', rotulo: 'Eventos' } */
type Opcao = { valor: string; rotulo: string };

/** Um post como a lista mostra. */
type PostLista = {
    id: number;
    titulo: string;
    categoria: string;
    situacao: 'rascunho' | 'em_revisao' | 'publicado' | 'arquivado';
    situacao_rotulo: string;
    // true = publicado com data no futuro (ainda não aparece no site).
    agendado: boolean;
    publicar_em: string | null;
    criado_em: string | null;
};

// Cor de cada situação (o texto vai escrito ao lado; a cor só ajuda).
const CORES: Record<PostLista['situacao'], string> = {
    rascunho: 'text-muted-foreground',
    em_revisao: 'text-amber-700 dark:text-amber-300',
    publicado: 'text-green-700 dark:text-green-300',
    arquivado: 'text-muted-foreground line-through',
};

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

/**
 * @param filtros Os filtros atuais (vazios = sem filtro).
 * @param posts Os posts, os mais novos primeiro.
 * @param categorias Notícias e Eventos.
 * @param situacoes Rascunho, Em revisão, Publicado, Arquivado.
 * @returns A tela da lista.
 */
export default function BlogAdmin({
    filtros,
    posts,
    categorias,
    situacoes,
}: {
    filtros: {
        busca: string;
        categoria: string;
        situacao: string;
        de: string;
        ate: string;
    };
    posts: PostLista[];
    categorias: Opcao[];
    situacoes: Opcao[];
}) {
    // Mensagem da última ação (ex.: "Post salvo como rascunho").
    const { sucesso } = usePage().props;

    /**
     * Refaz a lista com um filtro trocado (os vazios saem do endereço).
     *
     * @param novo O filtro que mudou. Ex.: { categoria: 'evento' }
     */
    const filtrar = (novo: Partial<typeof filtros>) => {
        const tudo = { ...filtros, ...novo };
        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(tudo).filter(([, valor]) => valor !== ''),
            ),
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Blog" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Blog
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Notícias e eventos do IBTO. Só os publicados
                            aparecem no site; arquivar tira do site sem apagar.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={create()}>Novo post</Link>
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

                {/* Filtros: busca, categoria, situação e período. */}
                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid w-full gap-1 sm:w-64">
                        <Label htmlFor="busca">Buscar pelo título</Label>
                        <Input
                            id="busca"
                            type="search"
                            defaultValue={filtros.busca}
                            // Busca ao apertar Enter ou sair do campo (não a cada letra).
                            onKeyDown={(evento) =>
                                evento.key === 'Enter' &&
                                filtrar({ busca: evento.currentTarget.value })
                            }
                            onBlur={(evento) =>
                                evento.target.value !== filtros.busca &&
                                filtrar({ busca: evento.target.value })
                            }
                        />
                    </div>
                    <div className="grid w-full gap-1 sm:w-40">
                        <Label htmlFor="categoria">Categoria</Label>
                        <select
                            id="categoria"
                            value={filtros.categoria}
                            onChange={(evento) =>
                                filtrar({ categoria: evento.target.value })
                            }
                            className={classeSelect}
                        >
                            <option value="">Todas</option>
                            {categorias.map((opcao) => (
                                <option key={opcao.valor} value={opcao.valor}>
                                    {opcao.rotulo}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid w-full gap-1 sm:w-40">
                        <Label htmlFor="situacao">Situação</Label>
                        <select
                            id="situacao"
                            value={filtros.situacao}
                            onChange={(evento) =>
                                filtrar({ situacao: evento.target.value })
                            }
                            className={classeSelect}
                        >
                            <option value="">Todas</option>
                            {situacoes.map((opcao) => (
                                <option key={opcao.valor} value={opcao.valor}>
                                    {opcao.rotulo}
                                </option>
                            ))}
                        </select>
                    </div>
                    <FiltroPeriodo
                        prefixo="blog"
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(periodo) => filtrar(periodo)}
                    />
                </div>

                {/* Lista vazia: frase em vez de uma tabela vazia. */}
                {posts.length === 0 ? (
                    <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Nenhum post com estes filtros.
                    </p>
                ) : (
                    <div className="caixa-tabela-cartoes overflow-x-auto rounded-md border border-border">
                        <table className="tabela-cartoes w-full text-left text-sm">
                            <caption className="sr-only">Posts do blog</caption>
                            <thead className="bg-muted/50">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Título
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Categoria
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
                                        Criado em
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
                                {posts.map((post) => (
                                    <tr
                                        key={post.id}
                                        className="border-t border-border"
                                    >
                                        <td
                                            data-rotulo="Título"
                                            className="px-4 py-2 font-medium"
                                        >
                                            {post.titulo}
                                        </td>
                                        <td
                                            data-rotulo="Categoria"
                                            className="px-4 py-2"
                                        >
                                            {post.categoria}
                                        </td>
                                        <td
                                            data-rotulo="Situação"
                                            className={`px-4 py-2 ${CORES[post.situacao]}`}
                                        >
                                            {/* Agendado: escrito, com a data em que aparece. */}
                                            {post.agendado
                                                ? `Agendado para ${post.publicar_em}`
                                                : post.situacao_rotulo}
                                        </td>
                                        <td
                                            data-rotulo="Criado em"
                                            className="px-4 py-2 whitespace-nowrap"
                                        >
                                            {/* Sem data (não deveria acontecer): um traço no lugar. */}
                                            {post.criado_em
                                                ? dataBr(post.criado_em)
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="min-h-11"
                                            >
                                                <Link href={edit(post.id)}>
                                                    Editar
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
BlogAdmin.layout = {
    breadcrumbs: [{ title: 'Blog', href: index() }],
};
