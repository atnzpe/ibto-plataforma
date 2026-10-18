/**
 * MONTAR A PROVA (#43), tela do instrutor: título, instruções, nota mínima,
 * tentativas, ativa ou não, e as perguntas. Cada pergunta tem de 2 a 8
 * alternativas e exatamente UMA marcada como certa (o servidor confere de novo).
 * O gabarito aparece só aqui, para quem edita o curso.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/** Uma alternativa no formulário. */
type Alternativa = { texto: string; correta: boolean };

/** Uma pergunta no formulário. */
type Questao = { enunciado: string; alternativas: Alternativa[] };

/** Os dados da prova no formulário. */
type ProvaFormulario = {
    titulo: string;
    instrucoes: string;
    nota_minima: number;
    tentativas_max: number;
    ativa: boolean;
    questoes: Questao[];
};

/**
 * Uma pergunta em branco, com duas alternativas (a primeira marcada como certa).
 *
 * @returns Ex.: { enunciado: '', alternativas: [{ texto: '', correta: true }, { texto: '', correta: false }] }
 */
function perguntaVazia(): Questao {
    return {
        enunciado: '',
        alternativas: [
            { texto: '', correta: true },
            { texto: '', correta: false },
        ],
    };
}

/**
 * @param curso O curso. Ex.: { id: 7, titulo: 'Inteligência Operacional' }
 * @param prova A prova para editar (com o gabarito), ou null para criar.
 * @returns O formulário da prova.
 */
export default function FormularioProva({
    curso,
    prova,
}: {
    curso: { id: number; titulo: string };
    prova: (ProvaFormulario & { id: number }) | null;
}) {
    // Erros que o servidor devolveu (ex.: pergunta sem resposta certa).
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    // O formulário inteiro num estado só (a prova é uma árvore: perguntas → alternativas).
    const [dados, setDados] = useState<ProvaFormulario>(
        prova ?? {
            titulo: '',
            instrucoes: '',
            nota_minima: 70,
            tentativas_max: 3,
            ativa: true,
            questoes: [perguntaVazia()],
        },
    );
    // Enviando: trava o botão.
    const [enviando, setEnviando] = useState(false);

    /**
     * Troca uma pergunta (pela posição), mantendo as outras.
     *
     * @param posicao Qual pergunta. Ex.: 0
     * @param nova A pergunta nova.
     */
    const trocarQuestao = (posicao: number, nova: Questao) =>
        setDados((atual) => ({
            ...atual,
            questoes: atual.questoes.map((questao, indice) =>
                indice === posicao ? nova : questao,
            ),
        }));

    /**
     * Envia a prova para o servidor (criar ou atualizar).
     *
     * @param evento O envio do formulário.
     */
    const salvar = (evento: React.FormEvent) => {
        evento.preventDefault();
        setEnviando(true);
        const opcoes = { onFinish: () => setEnviando(false) };
        // Editar: PUT na prova; criar: POST na lista do curso.
        if (prova) {
            router.put(
                `/comando-geral/cursos/${curso.id}/provas/${prova.id}`,
                dados,
                opcoes,
            );
        } else {
            router.post(
                `/comando-geral/cursos/${curso.id}/provas`,
                dados,
                opcoes,
            );
        }
    };

    // Visual dos campos (44 px de altura).
    const campo =
        'h-11 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm';
    // A primeira mensagem de erro do servidor (as das perguntas vêm com chaves como "questoes.0.enunciado").
    const primeiroErro = Object.values(errors)[0];

    return (
        <>
            <Head title={prova ? 'Editar prova' : 'Nova prova'} />
            <form
                onSubmit={salvar}
                className="flex max-w-3xl flex-col gap-5 p-4"
            >
                <div>
                    <p className="text-sm text-muted-foreground">
                        {curso.titulo}
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {prova ? 'Editar prova' : 'Nova prova'}
                    </h1>
                </div>

                {primeiroErro && (
                    <p
                        role="alert"
                        className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                    >
                        {primeiroErro}
                    </p>
                )}

                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="grid gap-1 text-sm sm:col-span-2">
                        Título
                        <input
                            required
                            maxLength={150}
                            value={dados.titulo}
                            onChange={(evento) =>
                                setDados({
                                    ...dados,
                                    titulo: evento.target.value,
                                })
                            }
                            placeholder="Ex.: Prova final"
                            className={campo}
                        />
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">
                        Instruções para o aluno (opcional)
                        <textarea
                            rows={3}
                            maxLength={2000}
                            value={dados.instrucoes}
                            onChange={(evento) =>
                                setDados({
                                    ...dados,
                                    instrucoes: evento.target.value,
                                })
                            }
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Nota mínima (0 a 100)
                        <input
                            type="number"
                            min={0}
                            max={100}
                            required
                            value={dados.nota_minima}
                            onChange={(evento) =>
                                setDados({
                                    ...dados,
                                    nota_minima: Number(evento.target.value),
                                })
                            }
                            className={campo}
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Tentativas por aluno
                        <input
                            type="number"
                            min={1}
                            max={20}
                            required
                            value={dados.tentativas_max}
                            onChange={(evento) =>
                                setDados({
                                    ...dados,
                                    tentativas_max: Number(evento.target.value),
                                })
                            }
                            className={campo}
                        />
                    </label>
                    <label className="flex min-h-11 items-center gap-3 text-sm sm:col-span-2">
                        <input
                            type="checkbox"
                            checked={dados.ativa}
                            onChange={(evento) =>
                                setDados({
                                    ...dados,
                                    ativa: evento.target.checked,
                                })
                            }
                            className="size-5"
                        />
                        Prova ativa (aparece para o aluno e conta para o
                        certificado)
                    </label>
                </div>

                {/* As perguntas. */}
                {dados.questoes.map((questao, posicao) => (
                    <fieldset
                        key={posicao}
                        className="flex flex-col gap-3 rounded-md border border-border p-4"
                        data-tour={posicao === 0 ? 'pergunta-prova' : undefined}
                    >
                        <legend className="px-1 text-sm font-semibold">
                            Pergunta {posicao + 1}
                        </legend>
                        <label className="grid gap-1 text-sm">
                            Enunciado
                            <textarea
                                required
                                rows={2}
                                maxLength={2000}
                                value={questao.enunciado}
                                onChange={(evento) =>
                                    trocarQuestao(posicao, {
                                        ...questao,
                                        enunciado: evento.target.value,
                                    })
                                }
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                        </label>
                        <p className="text-xs text-muted-foreground">
                            Marque a bolinha da alternativa CERTA.
                        </p>
                        {questao.alternativas.map((alternativa, ordem) => (
                            <div
                                key={ordem}
                                className="flex items-center gap-2"
                            >
                                <input
                                    type="radio"
                                    name={`certa-${posicao}`}
                                    checked={alternativa.correta}
                                    aria-label={`Alternativa ${ordem + 1} é a certa`}
                                    onChange={() =>
                                        trocarQuestao(posicao, {
                                            ...questao,
                                            alternativas:
                                                questao.alternativas.map(
                                                    (outra, indice) => ({
                                                        ...outra,
                                                        correta:
                                                            indice === ordem,
                                                    }),
                                                ),
                                        })
                                    }
                                    className="size-5 shrink-0"
                                />
                                <input
                                    required
                                    maxLength={500}
                                    value={alternativa.texto}
                                    aria-label={`Texto da alternativa ${ordem + 1}`}
                                    onChange={(evento) =>
                                        trocarQuestao(posicao, {
                                            ...questao,
                                            alternativas:
                                                questao.alternativas.map(
                                                    (outra, indice) =>
                                                        indice === ordem
                                                            ? {
                                                                  ...outra,
                                                                  texto: evento
                                                                      .target
                                                                      .value,
                                                              }
                                                            : outra,
                                                ),
                                        })
                                    }
                                    className={campo}
                                />
                                {/* Tirar a alternativa (mínimo de 2). */}
                                {questao.alternativas.length > 2 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11 shrink-0"
                                        aria-label={`Tirar a alternativa ${ordem + 1}`}
                                        onClick={() => {
                                            const restantes =
                                                questao.alternativas.filter(
                                                    (_, indice) =>
                                                        indice !== ordem,
                                                );
                                            // Se tirou a certa, a primeira passa a ser a certa.
                                            if (
                                                !restantes.some(
                                                    (outra) => outra.correta,
                                                )
                                            ) {
                                                restantes[0] = {
                                                    ...restantes[0],
                                                    correta: true,
                                                };
                                            }
                                            trocarQuestao(posicao, {
                                                ...questao,
                                                alternativas: restantes,
                                            });
                                        }}
                                    >
                                        ×
                                    </Button>
                                )}
                            </div>
                        ))}
                        <div className="flex flex-wrap gap-2">
                            {questao.alternativas.length < 8 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() =>
                                        trocarQuestao(posicao, {
                                            ...questao,
                                            alternativas: [
                                                ...questao.alternativas,
                                                { texto: '', correta: false },
                                            ],
                                        })
                                    }
                                >
                                    + Alternativa
                                </Button>
                            )}
                            {dados.questoes.length > 1 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() =>
                                        setDados({
                                            ...dados,
                                            questoes: dados.questoes.filter(
                                                (_, indice) =>
                                                    indice !== posicao,
                                            ),
                                        })
                                    }
                                >
                                    Tirar a pergunta {posicao + 1}
                                </Button>
                            )}
                        </div>
                    </fieldset>
                ))}

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={() =>
                            setDados({
                                ...dados,
                                questoes: [...dados.questoes, perguntaVazia()],
                            })
                        }
                    >
                        + Pergunta
                    </Button>
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={enviando}
                    >
                        Salvar prova
                    </Button>
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href={`/comando-geral/cursos/${curso.id}/provas`}>
                            Cancelar
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

// Caminho mostrado no topo da tela.
FormularioProva.layout = {
    breadcrumbs: [{ title: 'Cursos', href: '/comando-geral/cursos' }],
};
