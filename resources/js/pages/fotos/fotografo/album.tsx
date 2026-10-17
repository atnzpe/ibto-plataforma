/**
 * FOTÓGRAFO — UM ÁLBUM (#90): envia as fotos (até 30 por vez, até 15 MB cada) e vê
 * as prévias com a marca d'água. "Tirar" apaga a foto do álbum.
 */
import { Form, Head, router, usePage } from '@inertiajs/react';
import FotografoController from '@/actions/App/Http/Controllers/Fotos/FotografoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

/** O álbum com as prévias. */
type Album = {
    id: number;
    titulo: string;
    expirado: boolean;
    expira_em: string;
    fotos: { id: number; previa_url: string }[];
};

/**
 * @param album O álbum do fotógrafo, com as fotos.
 * @returns A tela do álbum.
 */
export default function AlbumFotografo({ album }: { album: Album }) {
    const { sucesso } = usePage<{ sucesso?: string }>().props;

    return (
        <>
            <Head title={album.titulo} />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {album.titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {album.fotos.length} fotos · as fotos são apagadas em{' '}
                        {album.expira_em}.
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

                {/* Envio: várias fotos de uma vez; o servidor guarda o original e gera a prévia. */}
                <Form
                    {...FotografoController.enviar.form(album.id)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-col gap-2 rounded-md border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <label
                                htmlFor="fotos"
                                className="text-sm font-medium"
                            >
                                Fotos (JPG, PNG ou WebP; até 30 por vez, até 15
                                MB cada)
                            </label>
                            <input
                                id="fotos"
                                name="fotos[]"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                className="text-sm"
                            />
                            <InputError
                                message={errors.fotos ?? errors['fotos.0']}
                            />
                            <Button
                                disabled={processing}
                                className="min-h-11 self-start"
                            >
                                {processing
                                    ? 'Enviando… (as prévias estão sendo geradas)'
                                    : 'Enviar fotos'}
                            </Button>
                        </>
                    )}
                </Form>

                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {album.fotos.map((foto) => (
                        <li key={foto.id} className="flex flex-col gap-1">
                            <img
                                src={foto.previa_url}
                                alt={`Prévia da foto ${foto.id}`}
                                loading="lazy"
                                className="aspect-square w-full rounded-md object-cover"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                className="min-h-11"
                                onClick={() =>
                                    router.delete(
                                        FotografoController.apagar.url(foto.id),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Tirar
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
AlbumFotografo.layout = {
    breadcrumbs: [{ title: 'Meus álbuns de fotos', href: '/fotografo' }],
};
