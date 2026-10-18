/**
 * MINHAS TURMAS (#80): as turmas das aulas regulares que o instrutor conduz (o
 * Comando Geral vê todas), cada uma com o botão "Fazer chamada".
 */
import { Head, Link } from '@inertiajs/react';

/** Um programa com as turmas. */
type ProgramaTurmas = {
    id: number;
    titulo: string;
    turmas: { id: number; nome: string; horario: string }[];
};

/**
 * @param programas Os programas com as turmas ativas.
 * @returns A lista.
 */
export default function MinhasTurmas({
    programas,
}: {
    programas: ProgramaTurmas[];
}) {
    return (
        <>
            <Head title="Minhas turmas" />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Minhas turmas (aulas regulares)
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Faça a chamada de cada aula. A frequência vai para o
                        dossiê dos alunos.
                    </p>
                </div>
                {programas.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Você ainda não é o instrutor de nenhum programa.
                    </p>
                )}
                {programas.map((p) => (
                    <section
                        key={p.id}
                        aria-labelledby={`t-prog-${p.id}`}
                        className="flex flex-col gap-2"
                    >
                        <h2 id={`t-prog-${p.id}`} className="font-medium">
                            {p.titulo}
                        </h2>
                        {p.turmas.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sem turma cadastrada.
                            </p>
                        ) : (
                            <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {p.turmas.map((t) => (
                                    <li
                                        key={t.id}
                                        className="flex flex-col gap-2 rounded-md border p-3 text-sm"
                                    >
                                        <p className="font-medium">{t.nome}</p>
                                        <p className="text-muted-foreground">
                                            {t.horario}
                                        </p>
                                        <Link
                                            href={`/minhas-turmas/${t.id}/chamada`}
                                            className="inline-flex min-h-11 items-center self-start rounded-md border px-4 font-medium"
                                        >
                                            Fazer chamada
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                ))}
            </div>
        </>
    );
}

MinhasTurmas.layout = {
    breadcrumbs: [{ title: 'Minhas turmas', href: '/minhas-turmas' }],
};
