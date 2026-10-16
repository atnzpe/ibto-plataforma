/**
 * EVENTOS (galeria, #61 bloco B2): os álbuns de fotos dos cursos e eventos do
 * IBTO, os mais novos primeiro. Cartões: 1 coluna no celular, 2 no tablet e 3
 * na tela larga. Modo privado (padrão desde 16/10/2026): só filiados e cargos
 * acima veem; quem não pode ver recebe o aviso "exclusivo para filiados".
 */
import { Head, Link } from '@inertiajs/react';
import { dataBr } from '@/pages/catalogo/index';
import { album as paginaDoAlbum } from '@/routes/galeria';

/** Um álbum no cartão da lista. */
type CartaoAlbum = {
    slug: string;
    titulo: string;
    data_evento: string | null;
    local_evento: string | null;
    fotos: number;
    capa_url: string | null;
    capa_descricao: string | null;
};

/**
 * @param albuns Os álbuns desta página e os links das páginas.
 * @returns A tela dos eventos.
 */
export default function Eventos({
    albuns,
    restrita = false,
}: {
    albuns: {
        data: CartaoAlbum[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    // true = a pessoa está logada, mas não é filiada (modo privado): mostra o aviso.
    restrita?: boolean;
}) {
    return (
        <>
            <Head title="Eventos" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="font-[Audiowide,sans-serif] text-3xl">
                        Eventos
                    </h1>
                    <p className="text-[#4a4536]">
                        Fotos dos cursos, treinamentos e encontros do IBTO.
                    </p>
                </div>

                {/* Modo privado e a pessoa não é filiada: o aviso, com o porquê. */}
                {restrita && (
                    <p
                        role="note"
                        className="rounded-xl border border-[#e4dccb] bg-white p-6 text-center text-[#4a4536]"
                    >
                        As fotos dos eventos são exclusivas para filiados do
                        IBTO. Muitos dos nossos alunos e instrutores são
                        operadores de segurança, e protegemos a imagem de quem
                        treina conosco.
                    </p>
                )}

                {/* Sem álbuns: frase em vez de uma lista vazia. */}
                {!restrita && albuns.data.length === 0 && (
                    <p className="rounded-xl border border-[#e4dccb] bg-white p-6 text-center text-[#4a4536]">
                        Nenhum álbum publicado ainda.
                    </p>
                )}

                <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {albuns.data.map((album) => (
                        <li
                            key={album.slug}
                            className="flex flex-col overflow-hidden rounded-xl border border-[#e4dccb] bg-white"
                        >
                            {/* A capa é a primeira foto do álbum. */}
                            {album.capa_url ? (
                                <img
                                    src={album.capa_url}
                                    alt={album.capa_descricao ?? ''}
                                    className="h-48 w-full object-cover"
                                    loading="lazy"
                                />
                            ) : (
                                <div
                                    aria-hidden="true"
                                    className="flex h-48 items-center justify-center bg-[#1c1b17] font-[Audiowide,sans-serif] text-2xl text-[#c9bd94]"
                                >
                                    IBTO
                                </div>
                            )}
                            <div className="flex flex-1 flex-col gap-1 p-4">
                                <h2 className="text-lg leading-snug font-semibold">
                                    {album.titulo}
                                </h2>
                                <span className="text-sm text-[#5c5644]">
                                    {[
                                        album.data_evento &&
                                            dataBr(album.data_evento),
                                        album.local_evento,
                                        `${album.fotos} foto(s)`,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                                <Link
                                    href={paginaDoAlbum(album.slug)}
                                    className="mt-auto inline-flex min-h-11 items-center font-medium text-[#6b5d3a]"
                                >
                                    Ver as fotos
                                    <span className="sr-only">
                                        : {album.titulo}
                                    </span>
                                </Link>
                            </div>
                        </li>
                    ))}
                </ul>

                {/* Páginas (só aparece quando há mais de 12 álbuns). */}
                {albuns.links.length > 3 && (
                    <nav aria-label="Páginas" className="flex flex-wrap gap-2">
                        {albuns.links.map((link, posicao) =>
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
