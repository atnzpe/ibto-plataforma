/**
 * ÁLBUM DA GALERIA no Comando Geral (#61, bloco B2): cadastro e edição.
 * 1. Os dados do evento (título, data, local, post ligado, crédito) e a
 *    AUTORIZAÇÃO DE USO DE IMAGEM (LGPD);
 * 2. (na edição) o envio de várias fotos de uma vez (até 20, 4 MB cada);
 * 3. (na edição) cada foto com a DESCRIÇÃO (obrigatória para publicar: quem
 *    usa leitor de tela "ouve" a descrição), o crédito e a ordem, e o botão
 *    de apagar;
 * 4. (na edição) a situação e os botões: publicar, arquivar, voltar para rascunho.
 */
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import AlbumController from '@/actions/App/Http/Controllers/Blog/AlbumController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { album as paginaDoAlbum } from '@/routes/galeria';
import { index, situacao as acaoDoAlbum } from '@/routes/comando-geral/galeria';
import { apagar as apagarFoto } from '@/routes/comando-geral/galeria/fotos';

/** Uma foto do álbum, como a edição mostra. */
type FotoEdicao = {
    id: number;
    url: string;
    descricao: string | null;
    credito: string | null;
    ordem: number;
    // false = falta descrever (o álbum não publica assim).
    descrita: boolean;
};

/** O álbum aberto para edição. */
type AlbumEdicao = {
    id: number;
    titulo: string;
    slug: string;
    descricao: string | null;
    data_evento: string | null;
    local_evento: string | null;
    post_id: number | null;
    credito_padrao: string;
    autorizacao_imagem: boolean;
    autorizacao_origem: string | null;
    tem_menores: boolean;
    autorizacao_responsaveis: boolean;
    situacao: string;
    situacao_rotulo: string;
    acoes: string[];
    fotos: FotoEdicao[];
};

// O texto de cada botão do caminho.
const ROTULO_ACAO: Record<string, string> = {
    publicar: 'Publicar o álbum',
    arquivar: 'Arquivar (tirar do site)',
    voltar_rascunho: 'Voltar para rascunho',
};

// Visual comum das caixas de escolha e de texto longo.
const classeCampo =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * Uma caixa de marcar com o texto ao lado.
 *
 * @param nome O name e o id do campo. Ex.: 'tem_menores'
 * @param marcado Como vem do banco.
 * @param texto O texto ao lado da caixa.
 */
function Marcar({
    nome,
    marcado,
    texto,
}: {
    nome: string;
    marcado: boolean;
    texto: string;
}) {
    return (
        <label className="flex items-start gap-2 text-sm">
            <input
                type="checkbox"
                id={nome}
                name={nome}
                value="1"
                defaultChecked={marcado}
                className="mt-0.5 size-4"
            />
            {texto}
        </label>
    );
}

/**
 * @param album O álbum a editar, ou null para criar um novo.
 * @param posts Os posts do blog (para ligar o álbum a um post).
 * @param fotos_por_envio Quantas fotos cabem num envio. Ex.: 20
 * @returns A tela do formulário.
 */
export default function AlbumFormulario({
    album,
    posts,
    fotos_por_envio = 20,
}: {
    album: AlbumEdicao | null;
    posts: { id: number; titulo: string }[];
    fotos_por_envio?: number;
}) {
    const editando = album !== null;
    const titulo = editando ? `Álbum "${album.titulo}"` : 'Novo álbum';
    // Mensagem e erros da última ação.
    const { sucesso, errors } = usePage().props;

    // Para onde os dados do álbum vão: atualizar (edição) ou criar (novo).
    const destino = editando
        ? AlbumController.update.form(album.id)
        : AlbumController.store.form();

    // Quantas fotos ainda estão sem descrição (o aviso no topo das fotos).
    const semDescricao = album
        ? album.fotos.filter((foto) => !foto.descrita).length
        : 0;

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-4xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {titulo}
                </h1>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* ---------- Situação e botões do caminho (só na edição) ---------- */}
                {editando && (
                    <section
                        aria-labelledby="t-situacao"
                        className="flex flex-col gap-3 rounded-xl border border-border p-4"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="t-situacao" className="font-semibold">
                                Situação: {album.situacao_rotulo}
                            </h2>
                            {/* Publicado: o link para ver como o público vê. */}
                            {album.situacao === 'publicado' && (
                                <Link
                                    href={paginaDoAlbum(album.slug)}
                                    className="text-sm underline underline-offset-4"
                                >
                                    Ver no site
                                </Link>
                            )}
                        </div>
                        {/* O motivo da recusa, com o código (ex.: ALBUM_FOTO_SEM_DESCRICAO). */}
                        {errors.acao && (
                            <p
                                role="alert"
                                className="rounded-md border border-red-600/40 bg-red-600/10 px-3 py-2 text-sm"
                            >
                                {errors.acao}
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2">
                            {album.acoes.map((acao) => (
                                <Button
                                    key={acao}
                                    type="button"
                                    variant={
                                        acao === 'publicar'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    className="min-h-11"
                                    onClick={() =>
                                        router.post(
                                            acaoDoAlbum(album.id).url,
                                            { acao },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {ROTULO_ACAO[acao]}
                                </Button>
                            ))}
                        </div>
                    </section>
                )}

                {/* ---------- 1. Os dados do evento ---------- */}
                <Form {...destino} className="flex flex-col gap-6">
                    {({ processing, errors: erros }) => (
                        <>
                            <fieldset className="grid gap-4">
                                <legend className="mb-2 font-medium">
                                    O evento
                                </legend>
                                <div className="grid gap-2">
                                    <Label htmlFor="titulo">
                                        Título do álbum
                                    </Label>
                                    <Input
                                        id="titulo"
                                        name="titulo"
                                        maxLength={150}
                                        defaultValue={album?.titulo}
                                        placeholder="Ex.: Formação de Instrutores · estande de tiro (PE)"
                                    />
                                    <InputError message={erros.titulo} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="data_evento">
                                            Data do evento
                                        </Label>
                                        <Input
                                            id="data_evento"
                                            name="data_evento"
                                            type="date"
                                            defaultValue={
                                                album?.data_evento ?? ''
                                            }
                                        />
                                        <InputError
                                            message={erros.data_evento}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="local_evento">
                                            Local
                                        </Label>
                                        <Input
                                            id="local_evento"
                                            name="local_evento"
                                            maxLength={150}
                                            defaultValue={
                                                album?.local_evento ?? ''
                                            }
                                            placeholder="Ex.: Recife - PE"
                                        />
                                        <InputError
                                            message={erros.local_evento}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="descricao">
                                        Sobre o evento (opcional)
                                    </Label>
                                    <textarea
                                        id="descricao"
                                        name="descricao"
                                        rows={3}
                                        maxLength={2000}
                                        defaultValue={album?.descricao ?? ''}
                                        className={classeCampo}
                                    />
                                    <InputError message={erros.descricao} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="post_id">
                                            Post do blog ligado (opcional)
                                        </Label>
                                        <select
                                            id="post_id"
                                            name="post_id"
                                            defaultValue={album?.post_id ?? ''}
                                            className={classeCampo}
                                        >
                                            <option value="">Nenhum</option>
                                            {posts.map((post) => (
                                                <option
                                                    key={post.id}
                                                    value={post.id}
                                                >
                                                    {post.titulo}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={erros.post_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="credito_padrao">
                                            Crédito das fotos
                                        </Label>
                                        <Input
                                            id="credito_padrao"
                                            name="credito_padrao"
                                            maxLength={120}
                                            defaultValue={
                                                album?.credito_padrao ??
                                                'Divulgação / IBTO'
                                            }
                                        />
                                        <InputError
                                            message={erros.credito_padrao}
                                        />
                                    </div>
                                </div>
                            </fieldset>

                            {/* LGPD: fotos de evento mostram pessoas. */}
                            <fieldset className="grid gap-3 rounded-xl border border-border p-4">
                                <legend className="px-1 font-medium">
                                    Autorização de uso de imagem (LGPD)
                                </legend>
                                <Marcar
                                    nome="autorizacao_imagem"
                                    marcado={album?.autorizacao_imagem ?? false}
                                    texto="Tenho a autorização de uso de imagem das pessoas das fotos"
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="autorizacao_origem">
                                        Como a autorização foi obtida
                                    </Label>
                                    <Input
                                        id="autorizacao_origem"
                                        name="autorizacao_origem"
                                        maxLength={200}
                                        defaultValue={
                                            album?.autorizacao_origem ?? ''
                                        }
                                        placeholder="Ex.: Termo assinado na inscrição do curso"
                                    />
                                    <InputError
                                        message={erros.autorizacao_origem}
                                    />
                                </div>
                                <Marcar
                                    nome="tem_menores"
                                    marcado={album?.tem_menores ?? false}
                                    texto="Aparecem menores de idade"
                                />
                                <Marcar
                                    nome="autorizacao_responsaveis"
                                    marcado={
                                        album?.autorizacao_responsaveis ?? false
                                    }
                                    texto="Tenho a autorização dos pais ou responsáveis"
                                />
                            </fieldset>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-h-11"
                                >
                                    {editando
                                        ? 'Salvar os dados do álbum'
                                        : 'Criar o álbum e enviar as fotos'}
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11"
                                >
                                    <Link href={index()}>Voltar à lista</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {/* ---------- 2. Enviar fotos (só na edição) ---------- */}
                {editando && (
                    <section
                        aria-labelledby="t-enviar"
                        className="flex flex-col gap-3 rounded-xl border border-border p-4"
                    >
                        <h2 id="t-enviar" className="font-semibold">
                            Enviar fotos
                        </h2>
                        {/* resetOnSuccess: depois do envio, o campo fica vazio para o próximo lote. */}
                        <Form
                            {...AlbumController.enviarFotos.form(album.id)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="flex flex-col gap-2 sm:flex-row sm:items-end"
                        >
                            {({ processing, errors: erros }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label htmlFor="fotos">
                                            Até {fotos_por_envio} fotos por vez
                                            (JPG, PNG ou WEBP, até 4 MB cada)
                                        </Label>
                                        <Input
                                            id="fotos"
                                            name="fotos[]"
                                            type="file"
                                            multiple
                                            accept="image/jpeg,image/png,image/webp"
                                        />
                                        <InputError
                                            message={
                                                erros.fotos ??
                                                Object.entries(erros).find(
                                                    ([campo]) =>
                                                        campo.startsWith(
                                                            'fotos.',
                                                        ),
                                                )?.[1]
                                            }
                                        />
                                    </div>
                                    {/* processing: trava o botão durante o envio (as fotos podem demorar). */}
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-h-11"
                                    >
                                        {processing
                                            ? 'Enviando...'
                                            : 'Enviar as fotos'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                {/* ---------- 3. As fotos: descrição, crédito e ordem (só na edição) ---------- */}
                {editando && album.fotos.length > 0 && (
                    <section
                        aria-labelledby="t-fotos"
                        className="flex flex-col gap-3"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="t-fotos" className="font-semibold">
                                Fotos ({album.fotos.length})
                            </h2>
                            {/* Aviso escrito: quantas faltam descrever. */}
                            {semDescricao > 0 && (
                                <span className="text-sm font-medium text-amber-700 dark:text-amber-300">
                                    {semDescricao} foto(s) sem descrição: o
                                    álbum só é publicado com todas descritas.
                                </span>
                            )}
                        </div>
                        <Form
                            {...AlbumController.salvarFotos.form(album.id)}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3"
                        >
                            {({ processing }) => (
                                <>
                                    {/* Cartões de foto: 1 coluna no celular, 2 no tablet, 3 na tela larga. */}
                                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {album.fotos.map((foto) => (
                                            <li
                                                key={foto.id}
                                                className={`flex flex-col gap-2 rounded-md border p-3 text-sm ${foto.descrita ? 'border-border' : 'border-amber-600'}`}
                                            >
                                                <img
                                                    src={foto.url}
                                                    alt={
                                                        foto.descricao ??
                                                        'Foto ainda sem descrição'
                                                    }
                                                    className="aspect-[4/3] w-full rounded object-cover"
                                                    loading="lazy"
                                                />
                                                <Label
                                                    htmlFor={`descricao-${foto.id}`}
                                                >
                                                    Descrição
                                                    {!foto.descrita &&
                                                        ' (falta descrever)'}
                                                </Label>
                                                <Input
                                                    id={`descricao-${foto.id}`}
                                                    name={`fotos[${foto.id}][descricao]`}
                                                    maxLength={200}
                                                    defaultValue={
                                                        foto.descricao ?? ''
                                                    }
                                                    placeholder="Ex.: Instrutor orienta a pegada da arma"
                                                />
                                                <div className="grid grid-cols-[1fr_5rem] gap-2">
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`credito-${foto.id}`}
                                                        >
                                                            Crédito (opcional)
                                                        </Label>
                                                        <Input
                                                            id={`credito-${foto.id}`}
                                                            name={`fotos[${foto.id}][credito]`}
                                                            maxLength={120}
                                                            defaultValue={
                                                                foto.credito ??
                                                                ''
                                                            }
                                                            placeholder={
                                                                album.credito_padrao
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`ordem-${foto.id}`}
                                                        >
                                                            Ordem
                                                        </Label>
                                                        <Input
                                                            id={`ordem-${foto.id}`}
                                                            name={`fotos[${foto.id}][ordem]`}
                                                            type="number"
                                                            min={1}
                                                            defaultValue={
                                                                foto.ordem
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                                {/* Apagar: pergunta antes (não tem como desfazer). */}
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    className="min-h-11"
                                                    onClick={() =>
                                                        window.confirm(
                                                            'Apagar esta foto? Não dá para desfazer.',
                                                        ) &&
                                                        router.delete(
                                                            apagarFoto(foto.id)
                                                                .url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Apagar foto
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-h-11 w-full sm:w-fit"
                                    >
                                        Salvar as descrições e a ordem
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
AlbumFormulario.layout = {
    breadcrumbs: [{ title: 'Galeria de eventos', href: index() }],
};
