/**
 * PÁGINA DE UM POST DO BLOG (#61): a capa (com o crédito), a categoria, a
 * data, o título, o resumo, o texto (as quebras de linha digitadas ficam), o
 * vídeo (YouTube sem cookies ou arquivo) e, se houver, o link para o curso
 * ligado. Curso fechado não aparece (o servidor já manda sem ele).
 */
import { Head, Link } from '@inertiajs/react';
import { dataBr } from '@/pages/catalogo/index';
import { curso as paginaDoCurso } from '@/routes/catalogo';
import { index } from '@/routes/blog';

/** Como tocar o vídeo (vem de App\Support\Video no servidor). */
type Video =
    | { tipo: 'youtube'; id: string; embed: string }
    | { tipo: 'arquivo'; url: string };

/**
 * @param post O post, com o texto, o vídeo e o curso ligado (ou null).
 * @returns A página do post.
 */
export default function PaginaPost({
    post,
}: {
    post: {
        titulo: string;
        categoria: string;
        resumo: string | null;
        capa_url: string | null;
        capa_descricao: string | null;
        data: string | null;
        texto: string | null;
        credito: string | null;
        video: Video | null;
        curso: { id: number; titulo: string } | null;
    };
}) {
    return (
        <>
            <Head title={post.titulo} />

            <article className="mx-auto flex max-w-3xl flex-col gap-5">
                <Link
                    href={index()}
                    className="inline-flex min-h-11 w-fit items-center text-sm text-[#6b5d3a]"
                >
                    ← Voltar ao blog
                </Link>

                <header className="flex flex-col gap-2">
                    <span className="text-sm text-[#5c5644]">
                        {post.categoria}
                        {post.data && ` · ${dataBr(post.data)}`}
                    </span>
                    <h1 className="font-[Audiowide,sans-serif] text-2xl leading-tight sm:text-3xl">
                        {post.titulo}
                    </h1>
                    {post.resumo && (
                        <p className="text-lg text-[#3f3a2c]">{post.resumo}</p>
                    )}
                </header>

                {/* Capa com o crédito embaixo (figure + figcaption). */}
                {post.capa_url && (
                    <figure className="flex flex-col gap-1">
                        <img
                            src={post.capa_url}
                            alt={post.capa_descricao ?? ''}
                            className="w-full rounded-xl object-cover"
                        />
                        {post.credito && (
                            <figcaption className="text-xs text-[#5c5644]">
                                Foto: {post.credito}
                            </figcaption>
                        )}
                    </figure>
                )}

                {/* O texto: as quebras de linha digitadas são mantidas. */}
                {post.texto && (
                    <div className="leading-relaxed whitespace-pre-line text-[#1c1b17]">
                        {post.texto}
                    </div>
                )}

                {/* Vídeo: YouTube num quadro (sem cookies de propaganda) ou o arquivo no tocador do navegador. */}
                {post.video?.tipo === 'youtube' && (
                    <iframe
                        src={post.video.embed}
                        title={`Vídeo: ${post.titulo}`}
                        className="aspect-video w-full rounded-xl bg-black"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowFullScreen
                        loading="lazy"
                        referrerPolicy="strict-origin-when-cross-origin"
                    />
                )}
                {post.video?.tipo === 'arquivo' && (
                    <video
                        src={post.video.url}
                        controls
                        preload="metadata"
                        className="aspect-video w-full rounded-xl bg-black"
                        aria-label={`Vídeo: ${post.titulo}`}
                    />
                )}

                {/* O curso ligado (só curso publicado e aberto). */}
                {post.curso && (
                    <aside className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-[#1c1b17] p-4 text-[#f4f1e8]">
                        <span>
                            Curso:{' '}
                            <strong className="font-semibold">
                                {post.curso.titulo}
                            </strong>
                        </span>
                        <Link
                            href={paginaDoCurso(post.curso.id)}
                            className="inline-flex min-h-11 items-center rounded-lg bg-[#6b5d3a] px-4 font-medium text-white no-underline"
                        >
                            Ver o curso
                        </Link>
                    </aside>
                )}
            </article>
        </>
    );
}
