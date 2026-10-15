/**
 * GALERIA DE EVENTOS no Comando Geral (#61, bloco B2): os álbuns, os eventos
 * mais novos primeiro, com a quantidade de fotos e a situação ESCRITA. Em
 * cartões (1 coluna no celular, 2 no tablet, 3 na tela larga): nenhuma tela
 * rola de lado.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dataBr } from '@/pages/catalogo/index';
import { create, edit, index } from '@/routes/comando-geral/galeria';

/** Um álbum como a lista mostra. */
type AlbumLista = {
    id: number;
    titulo: string;
    data_evento: string | null;
    local_evento: string | null;
    fotos: number;
    situacao: 'rascunho' | 'publicado' | 'arquivado';
    situacao_rotulo: string;
};

// Cor de cada situação (o texto vai escrito; a cor só ajuda).
const CORES: Record<AlbumLista['situacao'], string> = {
    rascunho: 'text-muted-foreground',
    publicado: 'text-green-700 dark:text-green-300',
    arquivado: 'text-muted-foreground line-through',
};

/**
 * @param albuns Os álbuns da galeria.
 * @returns A tela da lista.
 */
export default function GaleriaAdmin({ albuns }: { albuns: AlbumLista[] }) {
    // Mensagem da última ação (ex.: "Álbum criado").
    const { sucesso } = usePage().props;

    return (
        <>
            <Head title="Galeria de eventos" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Galeria de eventos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Álbuns de fotos dos cursos e eventos. Só os
                            publicados aparecem no site.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={create()}>Novo álbum</Link>
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

                {/* Sem álbuns: frase em vez de uma lista vazia. */}
                {albuns.length === 0 && (
                    <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Nenhum álbum ainda.
                    </p>
                )}

                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {albuns.map((album) => (
                        <li
                            key={album.id}
                            className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                        >
                            <strong className="text-base">
                                {album.titulo}
                            </strong>
                            <span className="text-muted-foreground">
                                {[
                                    album.data_evento &&
                                        dataBr(album.data_evento),
                                    album.local_evento,
                                    `${album.fotos} foto(s)`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                            <span className={CORES[album.situacao]}>
                                {album.situacao_rotulo}
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                className="mt-auto min-h-11"
                            >
                                <Link href={edit(album.id)}>Abrir o álbum</Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
GaleriaAdmin.layout = {
    breadcrumbs: [{ title: 'Galeria de eventos', href: index() }],
};
