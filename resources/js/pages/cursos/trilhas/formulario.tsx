/**
 * FORMULÁRIO da trilha (#39): nome, descrição, ativa e os cursos EM ORDEM.
 * A pessoa adiciona cursos pela caixa de escolha e arruma a ordem com
 * "Subir", "Descer" e "Tirar". A ordem vai para o servidor na ordem da lista
 * (campos escondidos cursos[]); quem confere é o TrilhaRequest.
 */
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import TrilhaController from '@/actions/App/Http/Controllers/Cursos/TrilhaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as cursosIndex } from '@/routes/comando-geral/cursos';
import { index } from '@/routes/comando-geral/trilhas';

type CursoOpcao = { id: number; titulo: string; situacao: string };
type TrilhaEdicao = {
    id: number;
    nome: string;
    descricao: string | null;
    ativa: boolean;
    cursos: number[];
    // Etapa de cada curso (id → etapa). Mesma etapa = lado a lado no fluxograma.
    etapas: Record<number, number>;
};

/**
 * Troca dois itens de lugar numa lista (sem mexer na original).
 *
 * @param lista Ex.: [7, 3, 12]
 * @param a Posição de um. Ex.: 0
 * @param b Posição do outro. Ex.: 1
 * @returns Ex.: [3, 7, 12]
 */
function trocar(lista: number[], a: number, b: number): number[] {
    const nova = [...lista];
    [nova[a], nova[b]] = [nova[b], nova[a]];

    return nova;
}

/**
 * @param trilha A trilha a editar, ou null para cadastrar uma nova.
 * @param cursos Os cursos que podem entrar (menos os encerrados).
 * @returns A tela do formulário.
 */
export default function TrilhaFormulario({
    trilha,
    cursos,
}: {
    trilha: TrilhaEdicao | null;
    cursos: CursoOpcao[];
}) {
    const editando = trilha !== null;
    const titulo = editando ? `Editar "${trilha.nome}"` : 'Nova trilha';

    // Os cursos escolhidos, na ordem das etapas (ids).
    const [escolhidos, setEscolhidos] = useState<number[]>(
        trilha?.cursos ?? [],
    );
    // Etapa escolhida para cada curso (id → número). Sem escolha, vale a posição na lista.
    const [etapas, setEtapas] = useState<Record<number, number>>(
        trilha?.etapas ?? {},
    );
    // Busca rápida do curso pelo id, para mostrar o nome.
    const porId = new Map(cursos.map((curso) => [curso.id, curso]));
    // Os que ainda podem ser adicionados.
    const disponiveis = cursos.filter(
        (curso) => !escolhidos.includes(curso.id),
    );

    const destino = editando
        ? TrilhaController.update.form(trilha.id)
        : TrilhaController.store.form();

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {titulo}
                </h1>

                <Form {...destino} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nome">Nome da trilha</Label>
                                <Input
                                    id="nome"
                                    name="nome"
                                    maxLength={120}
                                    defaultValue={trilha?.nome}
                                    placeholder="Ex.: Formação do Praticante ao Instrutor Expert"
                                />
                                <InputError message={errors.nome} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="descricao">Descrição</Label>
                                <textarea
                                    id="descricao"
                                    name="descricao"
                                    rows={3}
                                    maxLength={2000}
                                    defaultValue={trilha?.descricao ?? ''}
                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring"
                                />
                                <InputError message={errors.descricao} />
                            </div>

                            <fieldset className="grid gap-3">
                                <legend className="mb-1 font-medium">
                                    Cursos, na ordem das etapas
                                </legend>
                                <p className="text-xs text-muted-foreground">
                                    Dois cursos com a MESMA etapa aparecem lado
                                    a lado no fluxograma do aluno (caminhos à
                                    escolha).
                                </p>
                                {/* Os ids vão escondidos, NA ORDEM da lista. */}
                                {escolhidos.map((id) => (
                                    <input
                                        key={id}
                                        type="hidden"
                                        name="cursos[]"
                                        value={id}
                                    />
                                ))}
                                <ol className="flex flex-col divide-y divide-border rounded-md border border-border">
                                    {escolhidos.length === 0 && (
                                        <li className="px-4 py-4 text-sm text-muted-foreground">
                                            Nenhum curso ainda: adicione abaixo.
                                        </li>
                                    )}
                                    {escolhidos.map((id, posicao) => (
                                        <li
                                            key={id}
                                            className="flex flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <span className="flex items-center gap-3">
                                                {/* Etapa: número (repetir = lado a lado). */}
                                                <label className="flex items-center gap-1 text-muted-foreground">
                                                    Etapa
                                                    <Input
                                                        type="number"
                                                        name={`etapas[${id}]`}
                                                        min={1}
                                                        max={50}
                                                        value={
                                                            etapas[id] ??
                                                            posicao + 1
                                                        }
                                                        onChange={(evento) =>
                                                            setEtapas({
                                                                ...etapas,
                                                                [id]: Number(
                                                                    evento
                                                                        .target
                                                                        .value,
                                                                ),
                                                            })
                                                        }
                                                        className="h-9 w-16"
                                                        aria-label={`Etapa de ${porId.get(id)?.titulo ?? `curso nº ${id}`}`}
                                                    />
                                                </label>
                                                <span className="font-medium">
                                                    {porId.get(id)?.titulo ??
                                                        `Curso nº ${id}`}
                                                </span>
                                            </span>
                                            {/* 3 botões iguais, numa grade (celular) ou lado a lado. */}
                                            <span className="grid grid-cols-3 gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={posicao === 0}
                                                    onClick={() =>
                                                        setEscolhidos(
                                                            trocar(
                                                                escolhidos,
                                                                posicao,
                                                                posicao - 1,
                                                            ),
                                                        )
                                                    }
                                                    aria-label={`Subir a etapa ${posicao + 1}`}
                                                >
                                                    Subir
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        posicao ===
                                                        escolhidos.length - 1
                                                    }
                                                    onClick={() =>
                                                        setEscolhidos(
                                                            trocar(
                                                                escolhidos,
                                                                posicao,
                                                                posicao + 1,
                                                            ),
                                                        )
                                                    }
                                                    aria-label={`Descer a etapa ${posicao + 1}`}
                                                >
                                                    Descer
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setEscolhidos(
                                                            escolhidos.filter(
                                                                (outro) =>
                                                                    outro !==
                                                                    id,
                                                            ),
                                                        )
                                                    }
                                                    aria-label={`Tirar a etapa ${posicao + 1}`}
                                                >
                                                    Tirar
                                                </Button>
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                                <div className="grid gap-2">
                                    <Label htmlFor="adicionar">
                                        Adicionar curso
                                    </Label>
                                    <select
                                        id="adicionar"
                                        value=""
                                        // Escolher um curso o coloca no fim da trilha.
                                        onChange={(evento) =>
                                            evento.target.value &&
                                            setEscolhidos([
                                                ...escolhidos,
                                                Number(evento.target.value),
                                            ])
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                    >
                                        <option value="">
                                            Escolha um curso
                                        </option>
                                        {disponiveis.map((curso) => (
                                            <option
                                                key={curso.id}
                                                value={curso.id}
                                            >
                                                {curso.titulo} ({curso.situacao}
                                                )
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <InputError message={errors.cursos} />
                            </fieldset>

                            <div className="grid gap-2">
                                {/* "0" escondido: caixa desmarcada não é enviada pelo navegador. */}
                                <input type="hidden" name="ativa" value="0" />
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="ativa"
                                        value="1"
                                        defaultChecked={trilha?.ativa ?? true}
                                        className="size-4"
                                    />
                                    Ativa (aparece para os alunos)
                                </label>
                            </div>

                            <div className="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                                <Button disabled={processing}>Salvar</Button>
                                <Button asChild variant="outline">
                                    <Link href={index()}>Cancelar</Link>
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
TrilhaFormulario.layout = {
    breadcrumbs: [
        { title: 'Cursos', href: cursosIndex() },
        { title: 'Trilhas', href: index() },
    ],
};
