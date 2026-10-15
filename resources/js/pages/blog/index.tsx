/**
 * BLOG PÚBLICO (#61): as notícias e os eventos do IBTO, os mais novos
 * primeiro, com o filtro por categoria (Todas, Notícias, Eventos) e as
 * páginas. Cartões: 1 coluna no celular, 2 no tablet e 3 na tela larga.
 * Qualquer pessoa vê, sem login.
 */
import { Head, Link } from '@inertiajs/react';
import { dataBr } from '@/pages/catalogo/index';
import { index, post as paginaDoPost } from '@/routes/blog';

/** Um post no cartão da lista. */
type CartaoPost = {
    id: number;
    slug: string;
    titulo: string;
    categoria: string;
    resumo: string | null;
    capa_url: string | null;
    capa_descricao: string | null;
    data: string | null;
};

/**
 * @param categoria A categoria escolhida ('' = todas).
 * @param categorias Notícias e Eventos.
 * @param posts Os posts desta página e os links das páginas.
 * @returns A tela do blog.
 */
export default function Blog({
    categoria,
    categorias,
    posts,
}: {
    categoria: string;
    categorias: { valor: string; rotulo: string }[];
    posts: {
        data: CartaoPost[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}) {
    // As abas do filtro: "Todas" e uma por categoria.
    const abas = [{ valor: '', rotulo: 'Todas' }, ...categorias];

    return (
        <>
            <Head title="Blog" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="font-[Audiowide,sans-serif] text-3xl">
                        Blog do IBTO
                    </h1>
                    <p className="text-[#4a4536]">
                        Notícias, cursos e eventos do Instituto Brasileiro de
                        Treinamento Operacional.
                    </p>
                </div>

                {/* Filtro por categoria: a aba escolhida fica marcada (aria-current). */}
                <nav aria-label="Categorias" className="flex flex-wrap gap-2">
                    {abas.map((aba) => (
                        <Link
                            key={aba.valor}
                            href={index({
                                query: aba.valor
                                    ? { categoria: aba.valor }
                                    : {},
                            })}
                            aria-current={
                                categoria === aba.valor ? 'page' : undefined
                            }
                            className="inline-flex min-h-11 items-center rounded-full border border-[#6b5d3a] px-4 text-sm text-[#3f3722] no-underline aria-[current=page]:bg-[#6b5d3a] aria-[current=page]:text-white"
                        >
                            {aba.rotulo}
                        </Link>
                    ))}
                </nav>

                {/* Sem posts: frase em vez de uma lista vazia. */}
                {posts.data.length === 0 && (
                    <p className="rounded-xl border border-[#e4dccb] bg-white p-6 text-center text-[#4a4536]">
                        Nenhuma publicação por aqui ainda.
                    </p>
                )}

                <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {posts.data.map((post) => (
                        <li
                            key={post.id}
                            className="flex flex-col overflow-hidden rounded-xl border border-[#e4dccb] bg-white"
                        >
                            {/* Capa (com a descrição para o leitor de tela) ou o fundo escuro do IBTO. */}
                            {post.capa_url ? (
                                <img
                                    src={post.capa_url}
                                    alt={post.capa_descricao ?? ''}
                                    className="h-44 w-full object-cover"
                                    loading="lazy"
                                />
                            ) : (
                                <div
                                    aria-hidden="true"
                                    className="flex h-44 items-center justify-center bg-[#1c1b17] font-[Audiowide,sans-serif] text-2xl text-[#c9bd94]"
                                >
                                    IBTO
                                </div>
                            )}
                            <div className="flex flex-1 flex-col gap-2 p-4">
                                <span className="text-xs text-[#5c5644]">
                                    {post.categoria}
                                    {post.data && ` · ${dataBr(post.data)}`}
                                </span>
                                <h2 className="text-lg leading-snug font-semibold">
                                    {post.titulo}
                                </h2>
                                {post.resumo && (
                                    <p className="text-sm text-[#3f3a2c]">
                                        {post.resumo}
                                    </p>
                                )}
                                <Link
                                    href={paginaDoPost(post.slug)}
                                    className="mt-auto inline-flex min-h-11 items-center font-medium text-[#6b5d3a]"
                                >
                                    Ler mais
                                    <span className="sr-only">
                                        : {post.titulo}
                                    </span>
                                </Link>
                            </div>
                        </li>
                    ))}
                </ul>

                {/* Páginas (só aparece quando há mais de 12 posts). */}
                {posts.links.length > 3 && (
                    <nav aria-label="Páginas" className="flex flex-wrap gap-2">
                        {posts.links.map((link, posicao) =>
                            link.url ? (
                                <Link
                                    key={posicao}
                                    href={link.url}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className="rounded border border-[#e4dccb] px-3 py-1 text-sm aria-[current=page]:font-semibold"
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
