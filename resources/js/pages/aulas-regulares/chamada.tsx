/**
 * CHAMADA DE UMA TURMA DE AULA REGULAR (#80): escolha a data (padrão: hoje),
 * marque quem estava e salve. Aparecem só os alunos em dia ou na carência.
 * Uma caixa grande (44 px) por aluno; funciona no celular e no teclado.
 */
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

/** Um aluno na lista da chamada. */
type AlunoChamada = {
    assinatura_id: number;
    nome: string;
    acesso: string;
    presente: boolean;
};

/**
 * @param turma A turma (nome, horário e programa).
 * @param data A data da aula. Ex.: '2026-10-21'.
 * @param feita Se a chamada desta data já foi salva.
 * @param alunos Os alunos liberados, com quem já foi marcado.
 * @returns A tela.
 */
export default function ChamadaRegular({
    turma,
    data,
    feita,
    alunos,
}: {
    turma: { id: number; nome: string; horario: string; programa: string };
    data: string;
    feita: boolean;
    alunos: AlunoChamada[];
}) {
    const { sucesso } = usePage<{ sucesso?: string | null }>().props;
    // Hoje no formato do campo de data (a chamada não pode ser de um dia futuro).
    const hoje = new Date().toISOString().slice(0, 10);

    return (
        <>
            <Head title={`Chamada · ${turma.programa}`} />
            <div className="flex max-w-2xl flex-col gap-5 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Chamada: {turma.programa}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {turma.nome} · {turma.horario}
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

                {/* Trocar a data recarrega a lista daquele dia. */}
                <label className="flex flex-wrap items-center gap-2 text-sm">
                    Data da aula:
                    <input
                        type="date"
                        value={data}
                        max={hoje}
                        onChange={(e) =>
                            router.get(
                                `/minhas-turmas/${turma.id}/chamada`,
                                { data: e.target.value },
                                { preserveScroll: true },
                            )
                        }
                        className="min-h-11 rounded-md border bg-transparent px-2"
                    />
                    {feita && (
                        <span className="text-green-800 dark:text-green-300">
                            (chamada já registrada; pode corrigir)
                        </span>
                    )}
                </label>

                {alunos.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nenhum aluno em dia nesta turma.
                    </p>
                ) : (
                    <Form
                        action={`/minhas-turmas/${turma.id}/chamada`}
                        method="post"
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-3"
                    >
                        {({ processing }) => (
                            <>
                                <input type="hidden" name="data" value={data} />
                                <fieldset className="flex flex-col gap-2">
                                    <legend className="mb-1 text-sm font-medium">
                                        Marque quem estava
                                    </legend>
                                    {alunos.map((a) => (
                                        <label
                                            key={a.assinatura_id}
                                            className="flex min-h-11 items-center gap-3 rounded-md border px-3 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="presentes[]"
                                                value={a.assinatura_id}
                                                defaultChecked={a.presente}
                                                className="size-5"
                                            />
                                            <span className="flex-1">
                                                {a.nome}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {a.acesso}
                                            </span>
                                        </label>
                                    ))}
                                </fieldset>
                                <Button
                                    disabled={processing}
                                    data-tour="salvar-chamada"
                                    className="min-h-11 self-start"
                                >
                                    Salvar chamada
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                <Link
                    href="/minhas-turmas"
                    className="text-sm underline underline-offset-4"
                >
                    Voltar às turmas
                </Link>
            </div>
        </>
    );
}

ChamadaRegular.layout = {
    breadcrumbs: [{ title: 'Minhas turmas', href: '/minhas-turmas' }],
};
