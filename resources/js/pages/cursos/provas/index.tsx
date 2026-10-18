/**
 * PROVAS DO CURSO (#43), tela do instrutor: cada prova com o resumo (quantos
 * fizeram, aprovados e a média) e a situação de cada aluno, só com o registro
 * do filiado ou o nº da matrícula (LGPD). "Nova prova" e "Editar" levam ao
 * formulário. Desativar é pela edição (sem apagar: as notas ficam).
 */
import { Head, Link, usePage } from '@inertiajs/react';
import TabelaRelatorio from '@/components/relatorios/tabela-relatorio';
import type { ColunaRelatorio } from '@/components/relatorios/tabela-relatorio';
import { Button } from '@/components/ui/button';

/** A situação de um aluno numa prova. */
type AlunoNaProva = {
    quem: string;
    melhor_nota: number | null;
    aprovada: boolean;
    usadas: number;
    restantes: number;
};

/** Uma prova com o resumo e os alunos. */
type ProvaResumo = {
    id: number;
    titulo: string;
    ativa: boolean;
    questoes: number;
    nota_minima: number;
    tentativas_max: number;
    fizeram: number;
    aprovados: number;
    media: number | null;
    alunos: AlunoNaProva[];
};

// As colunas da tabela de alunos de cada prova.
const COLUNAS: ColunaRelatorio[] = [
    { chave: 'quem', rotulo: 'Aluno (registro)', tipo: 'texto' },
    { chave: 'nota', rotulo: 'Melhor nota', tipo: 'texto' },
    { chave: 'resultado', rotulo: 'Resultado', tipo: 'texto' },
    { chave: 'usadas', rotulo: 'Tentativas usadas', tipo: 'numero' },
];

/**
 * @param curso O curso. Ex.: { id: 7, titulo: 'Inteligência Operacional' }
 * @param provas As provas, com o resumo e os alunos.
 * @returns A tela das provas do curso.
 */
export default function ProvasDoCurso({
    curso,
    provas,
}: {
    curso: { id: number; titulo: string };
    provas: ProvaResumo[];
}) {
    // Mensagem da última ação (ex.: 'Prova salva.').
    const { sucesso } = usePage<{ sucesso?: string | null }>().props;

    return (
        <>
            <Head title={`Provas · ${curso.titulo}`} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {curso.titulo}
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Provas do curso
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Com prova ativa, o certificado só sai com a
                            aprovação em todas.
                        </p>
                    </div>
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <Button asChild variant="outline">
                            <Link
                                href={`/comando-geral/cursos/${curso.id}/conteudo`}
                            >
                                Conteúdo
                            </Link>
                        </Button>
                        <Button asChild data-tour="nova-prova">
                            <Link
                                href={`/comando-geral/cursos/${curso.id}/provas/nova`}
                            >
                                Nova prova
                            </Link>
                        </Button>
                    </div>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {provas.length === 0 && (
                    <p className="rounded-md border border-border p-6 text-center text-sm text-muted-foreground">
                        Este curso ainda não tem prova.
                    </p>
                )}

                {provas.map((prova) => (
                    <section
                        key={prova.id}
                        aria-labelledby={`prova-${prova.id}`}
                        className="flex flex-col gap-3 rounded-md border border-border p-4"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2
                                    id={`prova-${prova.id}`}
                                    className="text-base font-semibold"
                                >
                                    {prova.titulo}{' '}
                                    {/* Situação escrita (não só cor). */}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        · {prova.ativa ? 'Ativa' : 'Desativada'}
                                    </span>
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {prova.questoes} pergunta(s) · mínima{' '}
                                    {prova.nota_minima} · {prova.tentativas_max}{' '}
                                    tentativa(s)
                                </p>
                                <p className="text-sm">
                                    {prova.fizeram} fizeram · {prova.aprovados}{' '}
                                    aprovados
                                    {prova.media !== null &&
                                        ` · média ${prova.media}`}
                                </p>
                            </div>
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11"
                            >
                                <Link
                                    href={`/comando-geral/cursos/${curso.id}/provas/${prova.id}/editar`}
                                    aria-label={`Editar a prova ${prova.titulo}`}
                                >
                                    Editar
                                </Link>
                            </Button>
                        </div>
                        <TabelaRelatorio
                            colunas={COLUNAS}
                            linhas={prova.alunos.map((aluno) => ({
                                quem: aluno.quem,
                                nota:
                                    aluno.melhor_nota === null
                                        ? 'Não fez'
                                        : String(aluno.melhor_nota),
                                resultado: aluno.aprovada
                                    ? 'Aprovado'
                                    : aluno.melhor_nota === null
                                      ? '—'
                                      : aluno.restantes > 0
                                        ? 'Ainda pode tentar'
                                        : 'Reprovado (sem tentativas)',
                                usadas: aluno.usadas,
                            }))}
                            totais={{
                                quem: null,
                                nota: null,
                                resultado: null,
                                usadas: prova.alunos.reduce(
                                    (soma, aluno) => soma + aluno.usadas,
                                    0,
                                ),
                            }}
                            titulo={`Alunos na prova ${prova.titulo}`}
                        />
                    </section>
                ))}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
ProvasDoCurso.layout = {
    breadcrumbs: [{ title: 'Cursos', href: '/comando-geral/cursos' }],
};
