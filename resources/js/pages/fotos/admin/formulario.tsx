/**
 * ÁLBUM À VENDA — FORMULÁRIO (#90): o curso do evento, o fotógrafo credenciado,
 * o título, o preço por foto, a parte do fotógrafo e quando as fotos expiram.
 */
import { Form, Head, Link } from '@inertiajs/react';
import AlbunsVendaController from '@/actions/App/Http/Controllers/Fotos/AlbunsVendaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** O álbum aberto para edição (valores no formato dos campos). */
type AlbumEdicao = {
    id: number;
    curso_id: number;
    fotografo_id: number;
    titulo: string;
    // Ex.: '15,00'.
    preco: string;
    percentual_fotografo: number;
    // Ex.: '2026-12-15'.
    expira_em: string;
    ativo: boolean;
};

/**
 * @param album O álbum a editar, ou null para criar.
 * @param cursos Os cursos (não cancelados).
 * @param fotografos As contas com o papel Fotógrafo.
 * @param expira_sugerida A data sugerida de expiração (60 dias). Ex.: '2026-12-15'.
 * @returns A tela do formulário.
 */
export default function AlbumVendaFormulario({
    album,
    cursos,
    fotografos,
    expira_sugerida,
}: {
    album: AlbumEdicao | null;
    cursos: { id: number; titulo: string }[];
    fotografos: { id: number; name: string }[];
    expira_sugerida: string;
}) {
    const editando = album !== null;
    const destino = editando
        ? AlbunsVendaController.update.form(album.id)
        : AlbunsVendaController.store.form();
    const classeSelect =
        'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

    return (
        <>
            <Head
                title={editando ? 'Editar álbum à venda' : 'Novo álbum à venda'}
            />
            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {editando ? 'Editar álbum à venda' : 'Novo álbum à venda'}
                </h1>

                {/* Sem fotógrafo cadastrado: explica como criar um. */}
                {fotografos.length === 0 && (
                    <p
                        role="note"
                        className="rounded-md border border-border bg-muted p-3 text-sm"
                    >
                        Nenhuma conta com o papel Fotógrafo. Em Usuários, dê o
                        papel "Fotógrafo" à conta do fotógrafo credenciado.
                    </p>
                )}

                <Form {...destino} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="curso_id">
                                    Curso do evento
                                </Label>
                                <select
                                    id="curso_id"
                                    name="curso_id"
                                    defaultValue={album?.curso_id ?? ''}
                                    className={classeSelect}
                                >
                                    <option value="">Escolha…</option>
                                    {cursos.map((curso) => (
                                        <option key={curso.id} value={curso.id}>
                                            {curso.titulo}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    Só quem tem matrícula confirmada neste curso
                                    vê as fotos.
                                </p>
                                <InputError message={errors.curso_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fotografo_id">Fotógrafo</Label>
                                <select
                                    id="fotografo_id"
                                    name="fotografo_id"
                                    defaultValue={album?.fotografo_id ?? ''}
                                    className={classeSelect}
                                >
                                    <option value="">Escolha…</option>
                                    {fotografos.map((pessoa) => (
                                        <option
                                            key={pessoa.id}
                                            value={pessoa.id}
                                        >
                                            {pessoa.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.fotografo_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="titulo">Título</Label>
                                <Input
                                    id="titulo"
                                    name="titulo"
                                    maxLength={150}
                                    defaultValue={album?.titulo}
                                    placeholder="Ex.: Combate com Faca — 10/10/2026"
                                />
                                <InputError message={errors.titulo} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="preco">
                                        Preço por foto (R$)
                                    </Label>
                                    <Input
                                        id="preco"
                                        name="preco"
                                        inputMode="decimal"
                                        maxLength={20}
                                        defaultValue={album?.preco}
                                        placeholder="Ex.: 15,00"
                                    />
                                    <InputError message={errors.preco} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="percentual_fotografo">
                                        Parte do fotógrafo (%)
                                    </Label>
                                    <Input
                                        id="percentual_fotografo"
                                        name="percentual_fotografo"
                                        type="number"
                                        min={0}
                                        max={100}
                                        defaultValue={
                                            album?.percentual_fotografo ?? 70
                                        }
                                    />
                                    <InputError
                                        message={errors.percentual_fotografo}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expira_em">
                                        Fotos expiram em
                                    </Label>
                                    <Input
                                        id="expira_em"
                                        name="expira_em"
                                        type="date"
                                        defaultValue={
                                            album?.expira_em ?? expira_sugerida
                                        }
                                    />
                                    <InputError message={errors.expira_em} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <input type="hidden" name="ativo" value="0" />
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="ativo"
                                        value="1"
                                        defaultChecked={album?.ativo ?? true}
                                        className="size-4"
                                    />
                                    Ativo (aparece para a turma quando a venda
                                    estiver ligada)
                                </label>
                            </div>
                            <div className="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                                <Button disabled={processing}>Salvar</Button>
                                <Button asChild variant="outline">
                                    <Link href="/comando-geral/fotos">
                                        Cancelar
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
AlbumVendaFormulario.layout = {
    breadcrumbs: [{ title: 'Venda de fotos', href: '/comando-geral/fotos' }],
};
