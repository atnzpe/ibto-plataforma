/**
 * AULAS REGULARES — PÁGINA DO PROGRAMA (#80): descrição, turmas, mensalidade, o
 * dia do vencimento e as regras (carência de 5 dias; vencida trava só as aulas).
 * "Assinar" com a escolha da turma; quem já assina vê "Ver em Minhas aulas".
 */
import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';
import type { ProgramaCartao } from '@/pages/aulas-regulares/index';

/**
 * @param programa O programa (com descrição e dia de vencimento).
 * @param ja_assina Se a pessoa já assina (aguardando ou ativa).
 * @returns A página.
 */
export default function ProgramaAulas({
    programa,
    ja_assina,
}: {
    programa: ProgramaCartao & {
        descricao: string | null;
        dia_vencimento: number;
    };
    ja_assina: boolean;
}) {
    // Sem vagas: o botão de assinar some.
    const esgotado =
        programa.vagas_restantes !== null && programa.vagas_restantes <= 0;

    return (
        <>
            <Head title={programa.titulo} />
            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {programa.titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {programa.modalidade}
                        {programa.instrutor
                            ? ` · Instrutor: ${programa.instrutor}`
                            : ''}
                    </p>
                </div>
                {programa.descricao && (
                    <p className="text-sm whitespace-pre-line">
                        {programa.descricao}
                    </p>
                )}

                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt className="text-muted-foreground">Mensalidade</dt>
                    <dd className="font-medium">
                        {formatarReais(programa.valor_mensal_centavos)}
                    </dd>
                    <dt className="text-muted-foreground">Vencimento</dt>
                    <dd>Todo dia {programa.dia_vencimento}</dd>
                    <dt className="text-muted-foreground">Vagas</dt>
                    <dd>
                        {programa.vagas_restantes === null
                            ? 'Sem limite'
                            : programa.vagas_restantes}
                    </dd>
                </dl>
                <p className="text-sm text-muted-foreground">
                    Depois do vencimento, o acesso às aulas continua por mais 5
                    dias. Passado esse prazo, só as aulas deste programa ficam
                    travadas até o pagamento; a sua carteirinha e o resto da
                    plataforma não mudam.
                </p>

                {ja_assina ? (
                    <Button asChild className="min-h-11 self-start">
                        <Link href="/minhas-aulas">
                            Você já assina: ver em Minhas aulas
                        </Link>
                    </Button>
                ) : esgotado ? (
                    <p className="text-sm font-medium">Vagas esgotadas.</p>
                ) : (
                    <Form
                        action={`/aulas/${programa.id}/assinar`}
                        method="post"
                        className="flex flex-col gap-3 rounded-md border p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {/* A turma (opcional quando há só uma ou nenhuma). */}
                                {programa.turmas.length > 0 && (
                                    <fieldset className="grid gap-2 text-sm">
                                        <legend className="font-medium">
                                            Escolha a turma
                                        </legend>
                                        {programa.turmas.map((t, i) => (
                                            <label
                                                key={t.id}
                                                className="flex min-h-11 items-center gap-2 rounded-md border px-3"
                                            >
                                                <input
                                                    type="radio"
                                                    name="turma_id"
                                                    value={t.id}
                                                    defaultChecked={i === 0}
                                                />
                                                {t.nome}: {t.horario}
                                                {t.local ? ` · ${t.local}` : ''}
                                            </label>
                                        ))}
                                    </fieldset>
                                )}
                                <InputError message={errors.assinar} />
                                <Button
                                    disabled={processing}
                                    className="min-h-11 self-start"
                                >
                                    Assinar (
                                    {formatarReais(
                                        programa.valor_mensal_centavos,
                                    )}{' '}
                                    por mês)
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

ProgramaAulas.layout = {
    breadcrumbs: [{ title: 'Aulas regulares', href: '/aulas' }],
};
