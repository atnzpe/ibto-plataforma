/**
 * POST DO BLOG no Comando Geral (#61): cadastro e edição. Campos: título,
 * categoria (Notícias ou Eventos), resumo, texto, capa (com descrição e
 * crédito), vídeo, curso ligado e a AUTORIZAÇÃO DE USO DE IMAGEM (LGPD).
 * Na edição, o painel da situação mostra os botões do caminho: enviar para
 * revisão, publicar (agora ou agendado), arquivar, voltar para rascunho.
 * Salvar aceita o post incompleto; o que falta é cobrado na hora de publicar.
 */
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PostController from '@/actions/App/Http/Controllers/Blog/PostController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { post as paginaDoPost } from '@/routes/blog';
import { index, situacao as acaoDoPost } from '@/routes/comando-geral/blog';

/** Uma opção de lista fixa. Ex.: { valor: 'evento', rotulo: 'Eventos' } */
type Opcao = { valor: string; rotulo: string };

/** O post aberto para edição. */
type PostEdicao = {
    id: number;
    titulo: string;
    slug: string;
    categoria: string;
    resumo: string | null;
    texto: string | null;
    capa_url: string | null;
    capa_descricao: string | null;
    credito: string | null;
    video_url: string | null;
    curso_id: number | null;
    tem_pessoas: boolean;
    autorizacao_imagem: boolean;
    autorizacao_origem: string | null;
    tem_menores: boolean;
    autorizacao_responsaveis: boolean;
    situacao: string;
    situacao_rotulo: string;
    publicar_em: string | null;
    // true = aparece no site agora.
    no_site: boolean;
    // As ações que fazem sentido agora. Ex.: ['enviar_revisao', 'publicar'].
    acoes: string[];
};

// O texto de cada botão do caminho.
const ROTULO_ACAO: Record<string, string> = {
    enviar_revisao: 'Enviar para revisão',
    publicar: 'Publicar',
    arquivar: 'Arquivar (tirar do site)',
    voltar_rascunho: 'Voltar para rascunho',
};

// Visual comum das caixas de escolha e de texto longo, igual ao dos campos.
const classeCampo =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * Painel da situação (só na edição): onde o post está e os botões do caminho.
 * No "Publicar", a data opcional AGENDA o post (ele aparece sozinho no dia).
 *
 * @param post O post aberto.
 */
function PainelSituacao({ post }: { post: PostEdicao }) {
    // O erro da última ação (ex.: "Envie a imagem de capa... (POST_SEM_CAPA)").
    const { errors } = usePage().props;
    // A data escolhida para agendar (vazia = publicar agora).
    const [agendarPara, setAgendarPara] = useState('');

    /**
     * Faz uma ação do caminho.
     *
     * @param acao Ex.: 'publicar'
     */
    const fazer = (acao: string) =>
        router.post(
            acaoDoPost(post.id).url,
            { acao, publicar_em: acao === 'publicar' ? agendarPara : '' },
            { preserveScroll: true },
        );

    return (
        <section
            aria-labelledby="t-situacao"
            className="flex flex-col gap-3 rounded-xl border border-border p-4"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="t-situacao" className="font-semibold">
                    Situação: {post.situacao_rotulo}
                    {/* Publicado com data no futuro: escrito que está agendado. */}
                    {post.situacao === 'publicado' &&
                        !post.no_site &&
                        ` · agendado para ${post.publicar_em}`}
                </h2>
                {/* No site: o link para ver como o público vê. */}
                {post.no_site && (
                    <Link
                        href={paginaDoPost(post.slug)}
                        className="text-sm underline underline-offset-4"
                    >
                        Ver no site
                    </Link>
                )}
            </div>

            {/* O motivo da recusa, com o código (ex.: POST_SEM_AUTORIZACAO_IMAGEM). */}
            {errors.acao && (
                <p
                    role="alert"
                    className="rounded-md border border-red-600/40 bg-red-600/10 px-3 py-2 text-sm"
                >
                    {errors.acao}
                </p>
            )}

            {/* Agendar: só quando "Publicar" é uma das ações possíveis. */}
            {post.acoes.includes('publicar') && (
                <div className="grid gap-1 sm:max-w-xs">
                    <Label htmlFor="publicar_em">Agendar para (opcional)</Label>
                    <Input
                        id="publicar_em"
                        type="datetime-local"
                        value={agendarPara}
                        onChange={(evento) =>
                            setAgendarPara(evento.target.value)
                        }
                        aria-describedby="publicar_em-dica"
                    />
                    <p
                        id="publicar_em-dica"
                        className="text-xs text-muted-foreground"
                    >
                        Vazio = aparece no site na hora.
                    </p>
                </div>
            )}

            {/* Os botões do caminho: lado a lado, quebrando linha no celular. */}
            <div className="flex flex-wrap gap-2">
                {post.acoes.map((acao) => (
                    <Button
                        key={acao}
                        type="button"
                        variant={acao === 'publicar' ? 'default' : 'outline'}
                        className="min-h-11"
                        onClick={() => fazer(acao)}
                    >
                        {acao === 'publicar' && agendarPara !== ''
                            ? 'Agendar'
                            : ROTULO_ACAO[acao]}
                    </Button>
                ))}
            </div>
        </section>
    );
}

/**
 * Uma caixa de marcar com o texto ao lado.
 *
 * @param nome O name e o id do campo. Ex.: 'tem_pessoas'
 * @param marcado Como vem do banco (false no post novo).
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
 * @param post O post a editar, ou null para escrever um novo.
 * @param categorias Notícias e Eventos.
 * @param cursos Os cursos publicados (para ligar o post a um curso).
 * @returns A tela do formulário.
 */
export default function PostFormulario({
    post,
    categorias,
    cursos,
}: {
    post: PostEdicao | null;
    categorias: Opcao[];
    cursos: { id: number; titulo: string }[];
}) {
    const editando = post !== null;
    const titulo = editando ? `Editar "${post.titulo}"` : 'Novo post';
    // Mensagem da última ação (ex.: "Post salvo.").
    const { sucesso } = usePage().props;

    // Para onde o formulário envia: atualizar (edição) ou gravar (novo).
    const destino = editando
        ? PostController.update.form(post.id)
        : PostController.store.form();

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
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

                {/* Situação e botões do caminho (só na edição). */}
                {editando && <PainelSituacao post={post} />}

                <Form {...destino} className="flex flex-col gap-8">
                    {({ processing, errors }) => (
                        <>
                            {/* ---------- O post ---------- */}
                            <fieldset className="grid gap-4">
                                <legend className="mb-2 font-medium">
                                    O post
                                </legend>
                                <div className="grid gap-2">
                                    <Label htmlFor="titulo">Título</Label>
                                    <Input
                                        id="titulo"
                                        name="titulo"
                                        maxLength={150}
                                        defaultValue={post?.titulo}
                                        placeholder="Ex.: Formação de Instrutores em Pernambuco"
                                    />
                                    <InputError message={errors.titulo} />
                                </div>
                                <div className="grid gap-2 sm:max-w-xs">
                                    <Label htmlFor="categoria">Categoria</Label>
                                    <select
                                        id="categoria"
                                        name="categoria"
                                        defaultValue={
                                            post?.categoria ?? 'noticia'
                                        }
                                        className={classeCampo}
                                    >
                                        {categorias.map((opcao) => (
                                            <option
                                                key={opcao.valor}
                                                value={opcao.valor}
                                            >
                                                {opcao.rotulo}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.categoria} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="resumo">
                                        Resumo (aparece no cartão da lista)
                                    </Label>
                                    <textarea
                                        id="resumo"
                                        name="resumo"
                                        rows={2}
                                        maxLength={300}
                                        defaultValue={post?.resumo ?? ''}
                                        className={classeCampo}
                                    />
                                    <InputError message={errors.resumo} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="texto">Texto</Label>
                                    <textarea
                                        id="texto"
                                        name="texto"
                                        rows={10}
                                        maxLength={20000}
                                        defaultValue={post?.texto ?? ''}
                                        aria-describedby="texto-dica"
                                        className={classeCampo}
                                    />
                                    <p
                                        id="texto-dica"
                                        className="text-xs text-muted-foreground"
                                    >
                                        As quebras de linha que você digitar
                                        aparecem no site.
                                    </p>
                                    <InputError message={errors.texto} />
                                </div>
                            </fieldset>

                            {/* ---------- Capa e vídeo ---------- */}
                            <fieldset className="grid gap-4">
                                <legend className="mb-2 font-medium">
                                    Capa e vídeo
                                </legend>
                                {/* Capa atual (na edição), para ver o que vai trocar. */}
                                {post?.capa_url && (
                                    <img
                                        src={post.capa_url}
                                        alt={`Capa atual: ${post.capa_descricao ?? ''}`}
                                        className="h-40 w-full max-w-sm rounded-md border border-border object-cover"
                                    />
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="capa">
                                        Imagem de capa (JPG, PNG ou WEBP, até 2
                                        MB)
                                    </Label>
                                    <Input
                                        id="capa"
                                        name="capa"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                    />
                                    <InputError message={errors.capa} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="capa_descricao">
                                            Descrição da capa
                                        </Label>
                                        <Input
                                            id="capa_descricao"
                                            name="capa_descricao"
                                            maxLength={200}
                                            defaultValue={
                                                post?.capa_descricao ?? ''
                                            }
                                            placeholder="Ex.: Turma no estande de tiro"
                                            aria-describedby="capa_descricao-dica"
                                        />
                                        <p
                                            id="capa_descricao-dica"
                                            className="text-xs text-muted-foreground"
                                        >
                                            Quem usa leitor de tela "ouve" esta
                                            frase no lugar da foto.
                                        </p>
                                        <InputError
                                            message={errors.capa_descricao}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="credito">
                                            Crédito da foto
                                        </Label>
                                        <Input
                                            id="credito"
                                            name="credito"
                                            maxLength={120}
                                            defaultValue={
                                                post?.credito ??
                                                'Divulgação / IBTO'
                                            }
                                        />
                                        <InputError message={errors.credito} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="video_url">
                                        Vídeo (link do YouTube ou .mp4)
                                    </Label>
                                    <Input
                                        id="video_url"
                                        name="video_url"
                                        maxLength={500}
                                        defaultValue={post?.video_url ?? ''}
                                        placeholder="Ex.: https://youtu.be/..."
                                    />
                                    <InputError message={errors.video_url} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="curso_id">
                                        Curso ligado (opcional)
                                    </Label>
                                    <select
                                        id="curso_id"
                                        name="curso_id"
                                        defaultValue={post?.curso_id ?? ''}
                                        className={classeCampo}
                                    >
                                        <option value="">Nenhum</option>
                                        {cursos.map((curso) => (
                                            <option
                                                key={curso.id}
                                                value={curso.id}
                                            >
                                                {curso.titulo}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.curso_id} />
                                </div>
                            </fieldset>

                            {/* ---------- Autorização de uso de imagem (LGPD) ---------- */}
                            <fieldset className="grid gap-3 rounded-xl border border-border p-4">
                                <legend className="px-1 font-medium">
                                    Autorização de uso de imagem (LGPD)
                                </legend>
                                <p className="text-sm text-muted-foreground">
                                    Com pessoas nas fotos ou no vídeo, o post só
                                    é publicado com a autorização delas (e a dos
                                    responsáveis, se houver menores de idade).
                                </p>
                                <Marcar
                                    nome="tem_pessoas"
                                    marcado={post?.tem_pessoas ?? false}
                                    texto="As fotos ou o vídeo mostram pessoas"
                                />
                                <Marcar
                                    nome="autorizacao_imagem"
                                    marcado={post?.autorizacao_imagem ?? false}
                                    texto="Tenho a autorização de uso de imagem dessas pessoas"
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
                                            post?.autorizacao_origem ?? ''
                                        }
                                        placeholder="Ex.: Termo assinado na inscrição do curso"
                                    />
                                    <InputError
                                        message={errors.autorizacao_origem}
                                    />
                                </div>
                                <Marcar
                                    nome="tem_menores"
                                    marcado={post?.tem_menores ?? false}
                                    texto="Aparecem menores de idade"
                                />
                                <Marcar
                                    nome="autorizacao_responsaveis"
                                    marcado={
                                        post?.autorizacao_responsaveis ?? false
                                    }
                                    texto="Tenho a autorização dos pais ou responsáveis"
                                />
                            </fieldset>

                            {/* processing: trava o botão enquanto o servidor grava (evita clique duplo). */}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-h-11"
                                >
                                    {editando ? 'Salvar' : 'Salvar rascunho'}
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
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
PostFormulario.layout = {
    breadcrumbs: [{ title: 'Blog', href: index() }],
};
