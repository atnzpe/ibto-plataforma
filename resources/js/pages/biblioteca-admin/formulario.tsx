/**
 * MATERIAL DA BIBLIOTECA no Comando Geral (#107): cadastro (material = null) e
 * edição. Campos: nome da técnica, explicação, área, nível mínimo, vídeo (link
 * do YouTube não listado OU arquivo enviado) e apostila em PDF. "Na biblioteca"
 * desmarcado = retirado (some para quem estuda, sem apagar nada).
 */
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import MaterialAdminController from '@/actions/App/Http/Controllers/Biblioteca/MaterialAdminController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as verNaBiblioteca } from '@/routes/biblioteca';
import { index } from '@/routes/comando-geral/biblioteca';

/** Uma opção de lista (área ou nível). Ex.: { id: 2, nome: 'Krav Maga' } */
type Opcao = { id: number; nome: string };

/** O material em edição, como o servidor manda. */
type Material = {
    id: number;
    titulo: string;
    descricao: string | null;
    area_atuacao_id: number | null;
    nivel_minimo_id: number | null;
    fonte_video: 'link' | 'arquivo';
    video_url: string | null;
    tem_video_arquivo: boolean;
    tem_apostila: boolean;
    ativo: boolean;
};

// Visual comum das caixas de escolha (select) e do texto longo, igual aos campos de texto.
const classeCampo =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * A tela do formulário.
 *
 * @param material O material em edição; null = material novo.
 * @param areas As áreas de atuação ativas. Ex.: [{ id: 2, nome: 'Krav Maga' }]
 * @param niveis Os níveis ativos, do mais baixo ao mais alto.
 * @param video_maximo_mb O limite do vídeo enviado, em MB. Ex.: 200.
 * @returns A tela.
 */
export default function FormularioMaterial({
    material,
    areas,
    niveis,
    video_maximo_mb,
}: {
    material: Material | null;
    areas: Opcao[];
    niveis: Opcao[];
    video_maximo_mb: number;
}) {
    // Escolha atual do vídeo: link (recomendado, não pesa no servidor) ou arquivo enviado.
    const [fonte, setFonte] = useState<'link' | 'arquivo'>(
        material?.fonte_video ?? 'link',
    );
    // Para onde o formulário vai: gravar um novo ou salvar a edição.
    const destino = material
        ? MaterialAdminController.update.form(material.id)
        : MaterialAdminController.store.form();
    // Título da tela: "Novo material" ou o nome da técnica.
    const titulo = material ? `Editar "${material.titulo}"` : 'Novo material';

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        O vídeo por link do YouTube (não listado) é o
                        recomendado: não ocupa o espaço do servidor.
                    </p>
                </div>

                {/* Material já salvo: atalho para ver como o instrutor vê. */}
                {material && (
                    <Link
                        href={verNaBiblioteca(material.id)}
                        className="text-sm underline underline-offset-4"
                    >
                        Ver como aparece na biblioteca
                    </Link>
                )}

                <Form
                    {...destino}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="titulo">Nome da técnica</Label>
                                <Input
                                    id="titulo"
                                    name="titulo"
                                    maxLength={150}
                                    required
                                    defaultValue={material?.titulo}
                                    placeholder="Ex.: Defesa contra faca: ataque de cima"
                                />
                                <InputError message={errors.titulo} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="descricao">
                                    Explicação (o que observar, erros comuns)
                                </Label>
                                <textarea
                                    id="descricao"
                                    name="descricao"
                                    rows={5}
                                    maxLength={2000}
                                    defaultValue={material?.descricao ?? ''}
                                    className={classeCampo}
                                />
                                <InputError message={errors.descricao} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="area">
                                        Área de atuação
                                    </Label>
                                    <select
                                        id="area"
                                        name="area_atuacao_id"
                                        defaultValue={
                                            material?.area_atuacao_id ?? ''
                                        }
                                        className={`h-11 ${classeCampo}`}
                                    >
                                        {/* Vazio = o material vale para todas as áreas. */}
                                        <option value="">Todas as áreas</option>
                                        {areas.map((area) => (
                                            <option
                                                key={area.id}
                                                value={area.id}
                                            >
                                                {area.nome}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.area_atuacao_id}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="nivel">Nível mínimo</Label>
                                    <select
                                        id="nivel"
                                        name="nivel_minimo_id"
                                        defaultValue={
                                            material?.nivel_minimo_id ?? ''
                                        }
                                        aria-describedby="nivel-dica"
                                        className={`h-11 ${classeCampo}`}
                                    >
                                        {/* Vazio = todos que abrem a biblioteca veem. */}
                                        <option value="">
                                            Todos os níveis
                                        </option>
                                        {niveis.map((nivel) => (
                                            <option
                                                key={nivel.id}
                                                value={nivel.id}
                                            >
                                                {nivel.nome}
                                            </option>
                                        ))}
                                    </select>
                                    <p
                                        id="nivel-dica"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Só vê quem tem este nível ou um acima.
                                    </p>
                                    <InputError
                                        message={errors.nivel_minimo_id}
                                    />
                                </div>
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
                                {/* Mostra só o campo da opção escolhida. */}
                                {fonte === 'link' ? (
                                    <>
                                        <Input
                                            name="video_url"
                                            defaultValue={
                                                material?.video_url ?? ''
                                            }
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
                                            aria-describedby="video-dica"
                                        />
                                        <p
                                            id="video-dica"
                                            className="text-xs text-muted-foreground"
                                        >
                                            .mp4 ou .webm, até {video_maximo_mb}{' '}
                                            MB. Vídeos maiores: use um link do
                                            YouTube não listado.
                                            {material?.tem_video_arquivo &&
                                                ' Este material já tem um vídeo enviado; envie outro só para trocar.'}
                                        </p>
                                        <InputError
                                            message={errors.video_arquivo}
                                        />
                                    </>
                                )}
                            </fieldset>

                            <div className="grid gap-2">
                                <Label htmlFor="apostila">
                                    Apostila (PDF, até 10 MB)
                                </Label>
                                <Input
                                    id="apostila"
                                    name="apostila"
                                    type="file"
                                    accept="application/pdf"
                                />
                                <InputError message={errors.apostila} />
                                {/* Já tem apostila: dá para tirar sem enviar outra. */}
                                {material?.tem_apostila && (
                                    <label className="flex min-h-11 items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="remover_apostila"
                                            value="1"
                                            className="size-4"
                                        />
                                        Tirar a apostila atual
                                    </label>
                                )}
                            </div>

                            {/* O "0" escondido vai quando a caixa fica desmarcada (senão o campo nem iria). */}
                            <input type="hidden" name="ativo" value="0" />
                            <label className="flex min-h-11 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="ativo"
                                    value="1"
                                    defaultChecked={material?.ativo ?? true}
                                    className="size-4"
                                />
                                Na biblioteca (desmarque para retirar)
                            </label>

                            {/* Barra de envio: vídeo grande demora; a pessoa vê o andamento. */}
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

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Button
                                    disabled={processing}
                                    className="min-h-11"
                                >
                                    Salvar
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11"
                                >
                                    <Link href={index()}>
                                        Voltar para a lista
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

// Caminho mostrado no topo da tela ("migalhas de pão").
FormularioMaterial.layout = {
    breadcrumbs: [
        { title: 'Biblioteca (materiais)', href: index() },
        { title: 'Material', href: '#' },
    ],
};
