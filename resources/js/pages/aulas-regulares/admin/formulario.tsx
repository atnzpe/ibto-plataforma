/**
 * PROGRAMA DE AULAS REGULARES — FORMULÁRIO (#80): título, descrição, modalidade,
 * instrutor, mensalidade (em reais), divisão IBTO/instrutor, vagas, "só
 * filiados", dia de vencimento e ativo. Na edição, as TURMAS do programa (dias,
 * horário, local ou link da sala), com cadastro e edição.
 */
import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Uma opção de escolha. */
type Opcao = { id: number; nome?: string; name?: string };
/** Uma turma do programa. */
type Turma = {
    id: number;
    nome: string;
    dias_semana: string;
    hora_inicio: string;
    hora_fim: string | null;
    local: string | null;
    link_sala: string | null;
    ativa: boolean;
    horario: string;
};
/** O programa em edição (null = novo). */
type ProgramaEdicao = {
    id: number;
    titulo: string;
    descricao: string | null;
    area_atuacao_id: number | null;
    modalidade: string;
    instrutor_id: number | null;
    instrutor_nome: string | null;
    valor_mensal_centavos: number;
    percentual_ibto: number;
    vagas: number | null;
    so_filiados: boolean;
    dia_vencimento: number;
    ativo: boolean;
    turmas: Turma[];
};

// Os dias da semana, na ordem (o valor guardado e o rótulo da tela).
const DIAS: [string, string][] = [
    ['seg', 'Seg'],
    ['ter', 'Ter'],
    ['qua', 'Qua'],
    ['qui', 'Qui'],
    ['sex', 'Sex'],
    ['sab', 'Sáb'],
    ['dom', 'Dom'],
];

/** Centavos → texto em reais para o campo. Ex.: 12000 → '120,00'. */
const emReais = (centavos: number) =>
    (centavos / 100).toFixed(2).replace('.', ',');

/**
 * Os campos de uma turma (usados no cadastro e na edição).
 *
 * @param turma A turma em edição, ou undefined (nova).
 * @returns Os campos.
 */
function CamposTurma({ turma }: { turma?: Turma }) {
    const escolhidos = turma?.dias_semana.split(',') ?? [];
    const id = turma?.id ?? 'nova';

    return (
        <>
            <div className="grid gap-1">
                <Label htmlFor={`t-nome-${id}`}>Nome da turma</Label>
                <Input
                    id={`t-nome-${id}`}
                    name="nome"
                    defaultValue={turma?.nome ?? ''}
                    placeholder="Ex.: Noite"
                    required
                    maxLength={100}
                />
            </div>
            {/* Dias: caixas que viram "ter,qui" no campo escondido (o formulário junta ao enviar). */}
            <fieldset className="grid gap-1">
                <legend className="text-sm font-medium">Dias da semana</legend>
                <div className="flex flex-wrap gap-2">
                    {DIAS.map(([valor, rotulo]) => (
                        <label
                            key={valor}
                            className="flex min-h-11 items-center gap-1 rounded-md border px-3"
                        >
                            <input
                                type="checkbox"
                                name="dias[]"
                                value={valor}
                                defaultChecked={escolhidos.includes(valor)}
                            />
                            {rotulo}
                        </label>
                    ))}
                </div>
            </fieldset>
            <div className="grid grid-cols-2 gap-2">
                <div className="grid gap-1">
                    <Label htmlFor={`t-ini-${id}`}>Início</Label>
                    <Input
                        id={`t-ini-${id}`}
                        name="hora_inicio"
                        type="time"
                        defaultValue={turma?.hora_inicio ?? ''}
                        required
                    />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`t-fim-${id}`}>Fim</Label>
                    <Input
                        id={`t-fim-${id}`}
                        name="hora_fim"
                        type="time"
                        defaultValue={turma?.hora_fim ?? ''}
                    />
                </div>
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`t-local-${id}`}>Local (presencial)</Label>
                <Input
                    id={`t-local-${id}`}
                    name="local"
                    defaultValue={turma?.local ?? ''}
                    placeholder="Ex.: Sede da equipe, Recife-PE"
                    maxLength={200}
                />
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`t-link-${id}`}>
                    Link da sala (online: Meet, Zoom, Teams ou YouTube)
                </Label>
                <Input
                    id={`t-link-${id}`}
                    name="link_sala"
                    type="url"
                    defaultValue={turma?.link_sala ?? ''}
                    placeholder="https://meet.google.com/..."
                    maxLength={500}
                />
            </div>
        </>
    );
}

/**
 * @param programa O programa em edição, ou null (novo).
 * @param areas As áreas de atuação.
 * @param instrutores Quem tem o papel Instrutor.
 * @param modalidades As modalidades e os rótulos.
 * @returns O formulário.
 */
export default function ProgramaFormulario({
    programa,
    areas,
    instrutores,
    modalidades,
}: {
    programa: ProgramaEdicao | null;
    areas: Opcao[];
    instrutores: Opcao[];
    modalidades: { valor: string; rotulo: string }[];
}) {
    const { sucesso } = usePage<{ sucesso?: string | null }>().props;
    const editando = programa !== null;

    /**
     * Junta as caixas dos dias ("dias[]") no campo "dias_semana" antes de enviar.
     *
     * @param dados Os campos do formulário.
     * @returns Os campos prontos para o servidor.
     */
    const juntarDias = (dados: Record<string, unknown>) => {
        const dias = (dados.dias as string[] | undefined) ?? [];

        return { ...dados, dias_semana: dias.join(',') };
    };
    // Turma nova: nasce ativa.
    const turmaNova = (dados: Record<string, unknown>) => ({
        ...juntarDias(dados),
        ativa: true,
    });
    // Edição: ativa só se a caixa "Turma ativa" estiver marcada (desmarcada = desativar).
    const turmaEditada = (dados: Record<string, unknown>) => ({
        ...juntarDias(dados),
        ativa: dados.ativa === '1',
    });

    return (
        <>
            <Head
                title={
                    editando ? `Programa: ${programa.titulo}` : 'Novo programa'
                }
            />
            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {editando ? programa.titulo : 'Novo programa'}
                </h1>
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                <Form
                    action={
                        editando
                            ? `/comando-geral/programas/${programa.id}`
                            : '/comando-geral/programas'
                    }
                    method={editando ? 'put' : 'post'}
                    transform={(dados) => ({
                        ...dados,
                        so_filiados: dados.so_filiados === '1',
                        ativo: dados.ativo === '1',
                    })}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="titulo">Título</Label>
                                <Input
                                    id="titulo"
                                    name="titulo"
                                    defaultValue={programa?.titulo ?? ''}
                                    placeholder="Ex.: Krav Maga online"
                                    required
                                    maxLength={150}
                                />
                                <InputError message={errors.titulo} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="descricao">Descrição</Label>
                                <textarea
                                    id="descricao"
                                    name="descricao"
                                    defaultValue={programa?.descricao ?? ''}
                                    rows={4}
                                    maxLength={5000}
                                    className="rounded-md border bg-transparent p-2 text-sm"
                                />
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-1">
                                    <Label htmlFor="modalidade">
                                        Modalidade
                                    </Label>
                                    <select
                                        id="modalidade"
                                        name="modalidade"
                                        defaultValue={
                                            programa?.modalidade ?? 'presencial'
                                        }
                                        className="min-h-11 rounded-md border bg-transparent px-2"
                                    >
                                        {modalidades.map((m) => (
                                            <option
                                                key={m.valor}
                                                value={m.valor}
                                            >
                                                {m.rotulo}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="area_atuacao_id">
                                        Área de atuação
                                    </Label>
                                    <select
                                        id="area_atuacao_id"
                                        name="area_atuacao_id"
                                        defaultValue={
                                            programa?.area_atuacao_id ?? ''
                                        }
                                        className="min-h-11 rounded-md border bg-transparent px-2"
                                    >
                                        <option value="">—</option>
                                        {areas.map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.nome}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="instrutor_id">
                                        Instrutor responsável (faz a chamada)
                                    </Label>
                                    <select
                                        id="instrutor_id"
                                        name="instrutor_id"
                                        defaultValue={
                                            programa?.instrutor_id ?? ''
                                        }
                                        className="min-h-11 rounded-md border bg-transparent px-2"
                                    >
                                        <option value="">—</option>
                                        {instrutores.map((i) => (
                                            <option key={i.id} value={i.id}>
                                                {i.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="instrutor_nome">
                                        Nome na vitrine
                                    </Label>
                                    <Input
                                        id="instrutor_nome"
                                        name="instrutor_nome"
                                        defaultValue={
                                            programa?.instrutor_nome ?? ''
                                        }
                                        maxLength={150}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="valor">
                                        Mensalidade (R$)
                                    </Label>
                                    <Input
                                        id="valor"
                                        name="valor"
                                        inputMode="decimal"
                                        defaultValue={
                                            programa
                                                ? emReais(
                                                      programa.valor_mensal_centavos,
                                                  )
                                                : ''
                                        }
                                        placeholder="120,00"
                                        required
                                    />
                                    <InputError
                                        message={errors.valor_mensal_centavos}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="percentual_ibto">
                                        Parte do IBTO (%)
                                    </Label>
                                    <Input
                                        id="percentual_ibto"
                                        name="percentual_ibto"
                                        type="number"
                                        min={0}
                                        max={100}
                                        defaultValue={
                                            programa?.percentual_ibto ?? 20
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="vagas">
                                        Vagas (vazio = sem limite)
                                    </Label>
                                    <Input
                                        id="vagas"
                                        name="vagas"
                                        type="number"
                                        min={1}
                                        defaultValue={programa?.vagas ?? ''}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="dia_vencimento">
                                        Dia do vencimento (1 a 28)
                                    </Label>
                                    <Input
                                        id="dia_vencimento"
                                        name="dia_vencimento"
                                        type="number"
                                        min={1}
                                        max={28}
                                        defaultValue={
                                            programa?.dia_vencimento ?? 10
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors.dia_vencimento}
                                    />
                                </div>
                            </div>
                            <label className="flex min-h-11 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="so_filiados"
                                    value="1"
                                    defaultChecked={
                                        programa?.so_filiados ?? false
                                    }
                                />
                                Só filiados (aluno indicado não assina)
                            </label>
                            <label className="flex min-h-11 items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="ativo"
                                    value="1"
                                    defaultChecked={programa?.ativo ?? true}
                                />
                                Aberto para assinaturas
                            </label>
                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    className="min-h-11"
                                >
                                    Salvar
                                </Button>
                                <Link
                                    href="/comando-geral/programas"
                                    className="text-sm underline underline-offset-4"
                                >
                                    Voltar
                                </Link>
                            </div>
                        </>
                    )}
                </Form>

                {/* As turmas: só depois de salvar o programa. */}
                {editando && (
                    <section
                        aria-labelledby="t-turmas"
                        className="flex flex-col gap-4"
                    >
                        <h2 id="t-turmas" className="text-lg font-semibold">
                            Turmas
                        </h2>
                        {programa.turmas.map((turma) => (
                            <details
                                key={turma.id}
                                className="rounded-md border p-3 text-sm"
                            >
                                <summary className="min-h-11 cursor-pointer content-center">
                                    {turma.nome}: {turma.horario}{' '}
                                    {turma.ativa ? '' : '(desativada)'}
                                </summary>
                                <Form
                                    action={`/comando-geral/turmas-regulares/${turma.id}`}
                                    method="put"
                                    transform={turmaEditada}
                                    options={{ preserveScroll: true }}
                                    className="mt-3 flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <CamposTurma turma={turma} />
                                            <InputError
                                                message={
                                                    errors.dias_semana ??
                                                    errors.link_sala ??
                                                    errors.hora_fim
                                                }
                                            />
                                            <label className="flex min-h-11 items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    name="ativa"
                                                    value="1"
                                                    defaultChecked={turma.ativa}
                                                />
                                                Turma ativa
                                            </label>
                                            <Button
                                                disabled={processing}
                                                variant="secondary"
                                                className="min-h-11 self-start"
                                            >
                                                Salvar turma
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </details>
                        ))}
                        <div className="rounded-md border p-3 text-sm">
                            <h3 className="mb-3 font-medium">Nova turma</h3>
                            <Form
                                action={`/comando-geral/programas/${programa.id}/turmas`}
                                method="post"
                                transform={turmaNova}
                                resetOnSuccess
                                options={{ preserveScroll: true }}
                                className="flex flex-col gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <CamposTurma />
                                        <InputError
                                            message={
                                                errors.dias_semana ??
                                                errors.link_sala ??
                                                errors.hora_fim
                                            }
                                        />
                                        <Button
                                            disabled={processing}
                                            className="min-h-11 self-start"
                                        >
                                            Cadastrar turma
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

ProgramaFormulario.layout = {
    breadcrumbs: [
        {
            title: 'Programas (aulas regulares)',
            href: '/comando-geral/programas',
        },
    ],
};
