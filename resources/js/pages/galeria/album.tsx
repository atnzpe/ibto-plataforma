/**
 * PÁGINA DE UM ÁLBUM (galeria pública, #61 bloco B2): o título, a data, o
 * local, a descrição do evento, o link para o post do blog ligado (se já está
 * no site) e a grade de fotos com a descrição e o crédito de cada uma.
 */
import { Head, Link } from '@inertiajs/react';
import GradeFotos from '@/components/grade-fotos';
import type { FotoGaleria } from '@/components/grade-fotos';
import { dataBr } from '@/pages/catalogo/index';
import { post as paginaDoPost } from '@/routes/blog';
import { index } from '@/routes/galeria';

/**
 * @param album O álbum, com as fotos e o post ligado (ou null).
 * @returns A página do álbum.
 */
export default function PaginaAlbum({
    album,
}: {
    album: {
        titulo: string;
        descricao: string | null;
        data_evento: string | null;
        local_evento: string | null;
        post: { slug: string; titulo: string } | null;
        fotos: FotoGaleria[];
    };
}) {
    return (
        <>
            <Head title={album.titulo} />

            <article className="flex flex-col gap-5">
                <Link
                    href={index()}
                    className="inline-flex min-h-11 w-fit items-center text-sm text-[#6b5d3a]"
                >
                    ← Todos os eventos
                </Link>

                <header className="flex flex-col gap-2">
                    <span className="text-sm text-[#5c5644]">
                        {[
                            album.data_evento && dataBr(album.data_evento),
                            album.local_evento,
                            `${album.fotos.length} foto(s)`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                    <h1 className="font-[Audiowide,sans-serif] text-2xl leading-tight sm:text-3xl">
                        {album.titulo}
                    </h1>
                    {/* Sobre o evento: as quebras de linha digitadas ficam. */}
                    {album.descricao && (
                        <p className="whitespace-pre-line text-[#3f3a2c]">
                            {album.descricao}
                        </p>
                    )}
                    {/* O post do blog ligado (só se já está no site). */}
                    {album.post && (
                        <Link
                            href={paginaDoPost(album.post.slug)}
                            className="inline-flex min-h-11 w-fit items-center font-medium text-[#6b5d3a]"
                        >
                            Ler a matéria: {album.post.titulo}
                        </Link>
                    )}
                </header>

                <GradeFotos fotos={album.fotos} />
            </article>
        </>
    );
}
