/**
 * FOTÓGRAFO — MEUS ÁLBUNS (#90): os álbuns para os quais o fotógrafo foi escalado
 * e o atalho para "Meu financeiro". Ele não vê nada além do que é dele.
 */
import { Head, Link } from '@inertiajs/react';
import FotografoController from '@/actions/App/Http/Controllers/Fotos/FotografoController';
import { Button } from '@/components/ui/button';

/** Um álbum do fotógrafo. */
type Album = {
    id: number;
    titulo: string;
    curso: string;
    fotos: number;
    expira_em: string;
    expirado: boolean;
};

/**
 * @param albuns Os álbuns do fotógrafo logado, do mais novo ao mais antigo.
 * @returns A tela "Meus álbuns".
 */
export default function MeusAlbunsFotografo({ albuns }: { albuns: Album[] }) {
    return (
        <>
            <Head title="Meus álbuns de fotos" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Meus álbuns de fotos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Envie as fotos dos eventos para os quais o IBTO te
                            escalou. A prévia ganha a marca d'água sozinha.
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="w-full sm:w-auto"
                    >
                        <Link href={FotografoController.financeiro.url()}>
                            Meu financeiro
                        </Link>
                    </Button>
                </div>
                {albuns.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nenhum álbum para você ainda. O Comando Geral cria o
                        álbum e te escolhe como fotógrafo.
                    </p>
                )}
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {albuns.map((album) => (
                        <li
                            key={album.id}
                            className="flex flex-col gap-1 rounded-md border border-border p-4 text-sm"
                        >
                            <strong>{album.titulo}</strong>
                            <span className="text-muted-foreground">
                                {album.curso}
                            </span>
                            <span>
                                {album.fotos} fotos ·{' '}
                                {album.expirado
                                    ? 'expirado (fotos apagadas)'
                                    : `expira em ${album.expira_em}`}
                            </span>
                            {!album.expirado && (
                                <Button asChild className="mt-2 min-h-11">
                                    <Link
                                        href={FotografoController.album.url(
                                            album.id,
                                        )}
                                    >
                                        Enviar e ver fotos
                                    </Link>
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
MeusAlbunsFotografo.layout = {
    breadcrumbs: [{ title: 'Meus álbuns de fotos', href: '/fotografo' }],
};
