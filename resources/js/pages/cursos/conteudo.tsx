/**
 * CONTEÚDO do curso no Comando Geral (#39, bloco C2): módulos e aulas, na
 * ordem (subir/descer), com nível mínimo no módulo, vídeo por LINK ou ENVIADO
 * (escolha de quem cadastra) e apostila em PDF. Mesma lógica de "vídeos e
 * programas" do Dojo Manager. Sem apagar: desativa.
 * Responsivo: tudo em lista que cabe no celular; botões em grade simétrica.
 */
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ConteudoController from '@/actions/App/Http/Controllers/Cursos/ConteudoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    edit as editarCurso,
    index as cursos,
} from '@/routes/comando-geral/cursos';
import { apostila } from '@/routes/cursos/aulas';

type Aula = {
    id: number;
    titulo: string;
    ordem: number;
    video_url: string | null;
    video_tipo: 'youtube' | 'arquivo' | 'enviado' | null;
    fonte_video: 'link' | 'arquivo';
    conteudo: string | null;
    duracao_minutos: number | null;
    // Encontro presencial (cronograma): dia, horário e quem aplica.
    data: string | null;
    hora_inicio: string | null;
    hora_fim: string | null;
    quem_aplica: string | null;
    // Sala do encontro online ao vivo (#71): Meet, Zoom, Teams ou YouTube.
    link_ao_vivo: string | null;
    // Introdução pública do encontro (janela do cronograma).
    introducao: string | null;
    tem_apostila: boolean;
    ativa: boolean;
};

type Modulo = {
    id: number;
    titulo: string;
    ordem: number;
    nivel_minimo_id: number | null;
    nivel_minimo: string | null;
    ativo: boolean;
    aulas: Aula[];
};

type Props = {
    curso: { id: number; titulo: string; situacao: string };
    // false = só olhar (ex.: curso do dono já em análise ou publicado).
    pode_editar: boolean;
    modulos: Modulo[];
    niveis: { id: number; nome: string }[];
    video_maximo_mb: number;
};

// Tipo do vídeo → texto curto para a lista.
const VIDEO: Record<string, string> = {
    youtube: 'Vídeo do YouTube',
    arquivo: 'Vídeo por link',
    enviado: 'Vídeo enviado',
};

// Visual comum das caixas de escolha (select).
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * Botões "Subir" e "Descer" de um módulo ou aula (formulários pequenos, POST).
 *
 * @param acao A rota de mover (do Wayfinder), já com o item.
 * @param primeiro true se já é o primeiro (não sobe).
 * @param ultimo true se já é o último (não desce).
 * @param nome O que está sendo movido, para o leitor de tela.
 */
function BotoesMover({
    acao,
    primeiro,
    ultimo,
    nome,
}: {
    acao: ReturnType<typeof ConteudoController.moverModulo.form>;
    primeiro: boolean;
    ultimo: boolean;
    nome: string;
}) {
    return (
        <>
            {(['subir', 'descer'] as const).map((direcao) => (
                <Form
                    key={direcao}
                    {...acao}
                    options={{ preserveScroll: true }}
                >
                    <input type="hidden" name="direcao" value={direcao} />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        className="w-full"
                        disabled={direcao === 'subir' ? primeiro : ultimo}
                        aria-label={`${direcao === 'subir' ? 'Subir' : 'Descer'} ${nome}`}
                    >
                        {direcao === 'subir' ? 'Subir' : 'Descer'}
                    </Button>
                </Form>
            ))}
        </>
    );
}

/**
 * Janela de MÓDULO: novo (modulo = null, precisa do curso) ou edição.
 */
function JanelaModulo({
    cursoId,
    modulo,
    niveis,
}: {
    cursoId: number;
    modulo: Modulo | null;
    niveis: Props['niveis'];
}) {
    const [aberta, setAberta] = useState(false);
    const destino = modulo
        ? ConteudoController.updateModulo.form(modulo.id)
        : ConteudoController.storeModulo.form(cursoId);

    return (
        <Dialog open={aberta} onOpenChange={setAberta}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant={modulo ? 'outline' : 'default'}
                    className="w-full sm:w-auto"
                >
                    {modulo ? 'Editar' : 'Novo módulo'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {modulo ? `Editar "${modulo.titulo}"` : 'Novo módulo'}
                </DialogTitle>
                <DialogDescription>
                    Um grupo de aulas. O nível mínimo libera o módulo para o
                    filiado do nível e do anterior a ele.
                </DialogDescription>
                <Form
                    {...destino}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setAberta(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`modulo-titulo-${modulo?.id ?? 'novo'}`}
                                >
                                    Nome do módulo
                                </Label>
                                <Input
                                    id={`modulo-titulo-${modulo?.id ?? 'novo'}`}
                                    name="titulo"
                                    maxLength={150}
                                    defaultValue={modulo?.titulo}
                                    placeholder="Ex.: Módulo 1 · Fundamentos"
                                />
                                <InputError message={errors.titulo} />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`modulo-nivel-${modulo?.id ?? 'novo'}`}
                                >
                                    Nível mínimo
                                </Label>
                                <select
                                    id={`modulo-nivel-${modulo?.id ?? 'novo'}`}
                                    name="nivel_minimo_id"
                                    defaultValue={modulo?.nivel_minimo_id ?? ''}
                                    className={classeSelect}
                                >
                                    <option value="">Todos os inscritos</option>
                                    {niveis.map((nivel) => (
                                        <option key={nivel.id} value={nivel.id}>
                                            {nivel.nome}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.nivel_minimo_id} />
                            </div>
                            <input type="hidden" name="ativo" value="0" />
                            <label className="flex min-h-11 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="ativo"
                                    value="1"
                                    defaultChecked={modulo?.ativo ?? true}
                                    className="size-4"
                                />
                                Ativo (aparece para os alunos)
                            </label>
                            <Button
                                disabled={processing}
                                className="w-full sm:w-auto"
                            >
                                Salvar
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Janela de AULA: nova (aula = null, precisa do módulo) ou edição. O vídeo
 * vem por LINK ou ENVIADO (até o limite em MB), a critério de quem cadastra.
 */
function JanelaAula({
    moduloId,
    aula,
    maximoMb,
}: {
    moduloId: number;
    aula: Aula | null;
    maximoMb: number;
}) {
    const [aberta, setAberta] = useState(false);
    // Escolha atual do vídeo: link (padrão sugerido) ou arquivo enviado.
    const [fonte, setFonte] = useState<'link' | 'arquivo'>(
        aula?.fonte_video ?? 'link',
    );
    const destino = aula
        ? ConteudoController.updateAula.form(aula.id)
        : ConteudoController.storeAula.form(moduloId);
    // Prefixo dos ids dos campos, para não repetir na página.
    const id = `aula-${aula?.id ?? `nova-${moduloId}`}`;

    return (
        <Dialog open={aberta} onOpenChange={setAberta}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    className="w-full sm:w-auto"
                >
                    {aula ? 'Editar' : 'Nova aula'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogTitle>
                    {aula ? `Editar "${aula.titulo}"` : 'Nova aula'}
                </DialogTitle>
                <DialogDescription>
                    Vídeo por link (recomendado) ou enviado, texto e apostila em
                    PDF.
                </DialogDescription>
                <Form
                    {...destino}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setAberta(false)}
                    className="space-y-4"
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-titulo`}>
                                    Nome da aula
                                </Label>
                                <Input
                                    id={`${id}-titulo`}
                                    name="titulo"
                                    maxLength={150}
                                    defaultValue={aula?.titulo}
                                    placeholder="Ex.: Aula 2 · Defesas e controle"
                                />
                                <InputError message={errors.titulo} />
                            </div>

                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Vídeo
                                </legend>
                                <div className="grid grid-cols-2 gap-2 text-sm">
                                    {(['link', 'arquivo'] as const).map(
                                        (opcao) => (
                                            <label
                                                key={opcao}
                                                className="flex min-h-11 items-center gap-2"
                                            >
                                                <input
                                                    type="radio"
                                                    name="fonte_video"
                                                    value={opcao}
                                                    checked={fonte === opcao}
                                                    onChange={() =>
                                                        setFonte(opcao)
                                                    }
                                                    className="size-4"
                                                />
                                                {opcao === 'link'
                                                    ? 'Link (YouTube ou .mp4)'
                                                    : 'Enviar vídeo'}
                                            </label>
                                        ),
                                    )}
                                </div>
                                {fonte === 'link' ? (
                                    <>
                                        <Input
                                            name="video_url"
                                            defaultValue={aula?.video_url ?? ''}
                                            placeholder="https://youtu.be/..."
                                            aria-label="Link do vídeo"
                                        />
                                        <InputError
                                            message={errors.video_url}
                                        />
                                    </>
                                ) : (
                                    <>
                                        <Input
                                            name="video_arquivo"
                                            type="file"
                                            accept="video/mp4,video/webm"
                                            aria-label="Arquivo do vídeo"
                                            aria-describedby={`${id}-video-dica`}
                                        />
                                        <p
                                            id={`${id}-video-dica`}
                                            className="text-xs text-muted-foreground"
                                        >
                                            .mp4 ou .webm, até {maximoMb} MB.
                                            Vídeos maiores: use um link do
                                            YouTube.
                                            {aula?.video_tipo === 'enviado' &&
                                                ' Esta aula já tem um vídeo enviado; envie outro só para trocar.'}
                                        </p>
                                        <InputError
                                            message={errors.video_arquivo}
                                        />
                                    </>
                                )}
                            </fieldset>

                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-conteudo`}>
                                    Texto da aula
                                </Label>
                                <textarea
                                    id={`${id}-conteudo`}
                                    name="conteudo"
                                    rows={4}
                                    maxLength={10000}
                                    defaultValue={aula?.conteudo ?? ''}
                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                />
                                <InputError message={errors.conteudo} />
                            </div>
                            {/* Encontro presencial: aparece no cronograma (vazio no online). */}
                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Encontro presencial (opcional)
                                </legend>
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="grid gap-1">
                                        <Label htmlFor={`${id}-data`}>
                                            Dia
                                        </Label>
                                        <Input
                                            id={`${id}-data`}
                                            name="data"
                                            type="date"
                                            defaultValue={aula?.data ?? ''}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor={`${id}-inicio`}>
                                            Das
                                        </Label>
                                        <Input
                                            id={`${id}-inicio`}
                                            name="hora_inicio"
                                            type="time"
                                            defaultValue={
                                                aula?.hora_inicio ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor={`${id}-fim`}>Às</Label>
                                        <Input
                                            id={`${id}-fim`}
                                            name="hora_fim"
                                            type="time"
                                            defaultValue={aula?.hora_fim ?? ''}
                                        />
                                    </div>
                                </div>
                                <InputError
                                    message={
                                        errors.data ??
                                        errors.hora_inicio ??
                                        errors.hora_fim
                                    }
                                />
                                <Input
                                    name="quem_aplica"
                                    maxLength={100}
                                    defaultValue={aula?.quem_aplica ?? ''}
                                    placeholder="Quem aplica. Ex.: Instrutor Trinca"
                                    aria-label="Quem aplica o encontro"
                                />
                                <InputError message={errors.quem_aplica} />
                                {/* Sala ao vivo (#71): só para encontro ONLINE ao vivo. O aluno confirmado
                                    vê o botão "Entrar na sala" de 30 minutos antes até o fim do encontro. */}
                                <Input
                                    name="link_ao_vivo"
                                    type="url"
                                    inputMode="url"
                                    maxLength={500}
                                    defaultValue={aula?.link_ao_vivo ?? ''}
                                    placeholder="Link da sala ao vivo (encontro online). Ex.: https://meet.google.com/abc-defg-hij"
                                    aria-label="Link da sala ao vivo (Google Meet, Zoom, Teams ou YouTube)"
                                />
                                <InputError message={errors.link_ao_vivo} />
                                {/* Introdução PÚBLICA: aparece na janela do cronograma para quem ainda não se inscreveu. */}
                                <textarea
                                    name="introducao"
                                    rows={2}
                                    maxLength={500}
                                    defaultValue={aula?.introducao ?? ''}
                                    placeholder="Introdução pública (aparece no cronograma). Ex.: Postura, guarda e deslocamento básico."
                                    aria-label="Introdução pública do encontro"
                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                />
                                <InputError message={errors.introducao} />
                            </fieldset>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-duracao`}>
                                        Duração (min)
                                    </Label>
                                    <Input
                                        id={`${id}-duracao`}
                                        name="duracao_minutos"
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        defaultValue={
                                            aula?.duracao_minutos ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.duracao_minutos}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-apostila`}>
                                        Apostila (PDF)
                                    </Label>
                                    <Input
                                        id={`${id}-apostila`}
                                        name="apostila"
                                        type="file"
                                        accept="application/pdf"
                                    />
                                    <InputError message={errors.apostila} />
                                </div>
                            </div>
                            <input type="hidden" name="ativa" value="0" />
                            <label className="flex min-h-11 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="ativa"
                                    value="1"
                                    defaultChecked={aula?.ativa ?? true}
                                    className="size-4"
                                />
                                Ativa (aparece para os alunos)
                            </label>
                            {/* Barra de envio: arquivos grandes demoram; a pessoa vê o andamento. */}
                            {progress && (
                                <progress
                                    value={progress.percentage}
                                    max={100}
                                    className="w-full"
                                    aria-label="Enviando os arquivos"
                                >
                                    {progress.percentage}%
                                </progress>
                            )}
                            <Button
                                disabled={processing}
                                className="w-full sm:w-auto"
                            >
                                Salvar
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * @param props O curso, os módulos com as aulas, os níveis e o limite do vídeo.
 * @returns A tela do conteúdo.
 */
export default function ConteudoCurso({
    curso,
    modulos,
    niveis,
    video_maximo_mb,
    pode_editar,
}: Props) {
    // Mensagem da última ação (ex.: 'Aula "X" criada.'), ou null.
    const { sucesso } = usePage().props;

    return (
        <>
            <Head title={`Conteúdo · ${curso.titulo}`} />

            <div className="flex max-w-4xl flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Conteúdo do curso
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {curso.titulo}
                        </h1>
                    </div>
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <Button
                            asChild
                            variant="outline"
                            className={pode_editar ? '' : 'col-span-2'}
                        >
                            <Link href={editarCurso(curso.id)}>
                                Dados do curso
                            </Link>
                        </Button>
                        {/* Só quem pode editar cria módulos. */}
                        {pode_editar && (
                            <JanelaModulo
                                cursoId={curso.id}
                                modulo={null}
                                niveis={niveis}
                            />
                        )}
                    </div>
                </div>

                {/* Só olhar: explica por quê (ex.: o curso foi para a chancela). */}
                {!pode_editar && (
                    <p className="rounded-md border border-amber-600/40 bg-amber-600/10 px-4 py-2 text-sm">
                        Situação: {curso.situacao}. Nesta situação o conteúdo
                        fica só para consulta.
                    </p>
                )}

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {modulos.length === 0 && (
                    <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Nenhum módulo ainda. Comece por "Novo módulo".
                    </p>
                )}

                <ol className="flex flex-col gap-4">
                    {modulos.map((modulo, posicaoModulo) => (
                        <li
                            key={modulo.id}
                            className="flex flex-col gap-3 rounded-md border border-border p-4"
                        >
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="font-semibold">
                                        {posicaoModulo + 1}. {modulo.titulo}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {modulo.nivel_minimo
                                            ? `A partir de ${modulo.nivel_minimo}`
                                            : 'Todos os inscritos'}
                                        {!modulo.ativo && ' · Desativado'}
                                    </p>
                                </div>
                                {/* 4 botões iguais: grade no celular, linha na tela larga (só para quem pode editar). */}
                                <div
                                    className={
                                        pode_editar
                                            ? 'grid grid-cols-2 gap-2 sm:flex'
                                            : 'hidden'
                                    }
                                >
                                    <BotoesMover
                                        acao={ConteudoController.moverModulo.form(
                                            modulo.id,
                                        )}
                                        primeiro={posicaoModulo === 0}
                                        ultimo={
                                            posicaoModulo === modulos.length - 1
                                        }
                                        nome={`o módulo ${modulo.titulo}`}
                                    />
                                    <JanelaModulo
                                        cursoId={curso.id}
                                        modulo={modulo}
                                        niveis={niveis}
                                    />
                                    <JanelaAula
                                        moduloId={modulo.id}
                                        aula={null}
                                        maximoMb={video_maximo_mb}
                                    />
                                </div>
                            </div>

                            <ol className="flex flex-col divide-y divide-border rounded-md border border-border">
                                {modulo.aulas.length === 0 && (
                                    <li className="px-3 py-3 text-sm text-muted-foreground">
                                        Nenhuma aula neste módulo.
                                    </li>
                                )}
                                {modulo.aulas.map((aula, posicaoAula) => (
                                    <li
                                        key={aula.id}
                                        className="flex flex-col gap-2 px-3 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <span className="flex flex-col">
                                            <span className="font-medium">
                                                {posicaoAula + 1}. {aula.titulo}
                                                {!aula.ativa && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        · Desativada
                                                    </span>
                                                )}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {aula.video_tipo
                                                    ? VIDEO[aula.video_tipo]
                                                    : 'Sem vídeo'}
                                                {aula.duracao_minutos &&
                                                    ` · ${aula.duracao_minutos} min`}
                                                {aula.tem_apostila && (
                                                    <>
                                                        {' · '}
                                                        <a
                                                            href={
                                                                apostila(
                                                                    aula.id,
                                                                ).url
                                                            }
                                                            className="underline underline-offset-4"
                                                        >
                                                            Apostila (PDF)
                                                        </a>
                                                    </>
                                                )}
                                            </span>
                                        </span>
                                        {/* Subir, Descer, Editar: só para quem pode editar. */}
                                        <span
                                            className={
                                                pode_editar
                                                    ? 'grid grid-cols-3 gap-2'
                                                    : 'hidden'
                                            }
                                        >
                                            <BotoesMover
                                                acao={ConteudoController.moverAula.form(
                                                    aula.id,
                                                )}
                                                primeiro={posicaoAula === 0}
                                                ultimo={
                                                    posicaoAula ===
                                                    modulo.aulas.length - 1
                                                }
                                                nome={`a aula ${aula.titulo}`}
                                            />
                                            <JanelaAula
                                                moduloId={modulo.id}
                                                aula={aula}
                                                maximoMb={video_maximo_mb}
                                            />
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </li>
                    ))}
                </ol>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
ConteudoCurso.layout = {
    breadcrumbs: [{ title: 'Cursos', href: cursos() }],
};
