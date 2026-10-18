/**
 * MEU PAINEL (#79): números dos cursos do instrutor (os dele) ou do admin de
 * equipe (os da equipe). Por curso: inscritos, comparecimento, a receber,
 * recebido e certificados. Filtro De/Até (regra da casa) e exportar CSV.
 * Nenhum dado pessoal de aluno: só contagens, percentuais e valores.
 * Cartões em qualquer largura (cabe no celular sem rolagem lateral).
 */
import { Head, router } from '@inertiajs/react';
import FiltroPeriodo from '@/components/filtro-periodo';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';

/** Os números de um curso. */
type LinhaCurso = {
    id: number;
    titulo: string;
    data_inicio: string | null;
    situacao_rotulo: string;
    aguardando: number;
    confirmados: number;
    cancelados: number;
    encontros_com_chamada: number;
    // Percentual inteiro, ou null sem chamada ainda.
    comparecimento: number | null;
    a_receber_centavos: number;
    recebido_centavos: number;
    certificados: number;
    aptos_sem_certificado: number;
};

/** Os totais do período. */
type Totais = {
    inscritos: number;
    confirmados: number;
    a_receber_centavos: number;
    recebido_centavos: number;
    certificados: number;
    comparecimento: number | null;
};

/** Os números de um programa de aulas regulares que a pessoa ministra (centavos). */
type LinhaPrograma = {
    nome: string;
    alunos: number;
    previsto: number;
    recebido: number;
    a_receber: number;
    atrasado: number;
    instrutor_previsto: number;
    instrutor_recebido: number;
    repassado: number;
    a_repassar: number;
};

/** A saúde financeira da pessoa: cursos + aulas regulares, só a parte dela (centavos). */
type Saude = {
    recebido_centavos: number;
    pendente_centavos: number;
    a_vencer_centavos: number;
    total_a_receber_centavos: number;
};

/**
 * @param periodo O período do filtro. Ex.: { de: '2026-01-01', ate: '2026-12-31' }
 * @param cursos Os números de cada curso.
 * @param totais As somas do período.
 * @returns A tela "Meu painel".
 */
export default function MeuPainel({
    periodo,
    cursos,
    totais,
    aulas,
    saude,
}: {
    periodo: { de: string; ate: string };
    cursos: LinhaCurso[];
    totais: Totais;
    aulas: {
        programas: LinhaPrograma[];
        alunos: number;
        alunos_em_dia: number;
    };
    saude: Saude;
}) {
    // A saúde financeira em 4 cartões: o que entrou, o que falta e o total.
    const cartoesSaude = [
        {
            rotulo: 'Já recebi',
            valor: formatarReais(saude.recebido_centavos),
            dica: 'repassado pelo IBTO (cursos e aulas)',
        },
        {
            rotulo: 'Pendente de repasse',
            valor: formatarReais(saude.pendente_centavos),
            dica: 'o aluno já pagou; falta o IBTO repassar',
        },
        {
            rotulo: 'A vencer',
            valor: formatarReais(saude.a_vencer_centavos),
            dica: 'sua parte nas mensalidades ainda não pagas',
        },
        {
            rotulo: 'Total a receber',
            valor: formatarReais(saude.total_a_receber_centavos),
            dica: 'pendente + a vencer',
        },
    ];
    // As linhas de cada cartão de programa: rótulo e valor (centavos).
    const linhasPrograma = (programa: LinhaPrograma) =>
        [
            ['Mensalidades previstas', programa.previsto],
            ['Já pagas pelos alunos', programa.recebido],
            ['Em atraso', programa.atrasado],
            ['Minha parte prevista', programa.instrutor_previsto],
            ['Já repassado a mim', programa.repassado],
            ['Falta repassar', programa.a_repassar],
        ] as const;
    // Os cartões do topo: o que importa primeiro.
    const destaques = [
        {
            rotulo: 'Inscritos',
            valor: String(totais.inscritos),
            dica: `${totais.confirmados} confirmados`,
        },
        {
            rotulo: 'Comparecimento',
            valor:
                totais.comparecimento === null
                    ? '—'
                    : `${totais.comparecimento}%`,
            dica: 'presenças nos encontros com chamada',
        },
        {
            rotulo: 'A receber',
            valor: formatarReais(totais.a_receber_centavos),
            dica: 'sua parte ainda não repassada',
        },
        {
            rotulo: 'Recebido',
            valor: formatarReais(totais.recebido_centavos),
            dica: 'já repassado pelo IBTO',
        },
        {
            rotulo: 'Certificados',
            valor: String(totais.certificados),
            dica: 'emitidos e válidos',
        },
    ];
    // Endereço do CSV com o mesmo período da tela.
    const csv = `/meu-painel/csv?de=${periodo.de}&ate=${periodo.ate}`;

    return (
        <>
            <Head title="Meu painel" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Meu painel
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Os seus cursos e aulas regulares (ou os da sua
                            equipe) no período, e quanto você já recebeu e ainda
                            tem a receber. Sem dados pessoais dos alunos.
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="w-full sm:w-auto"
                    >
                        <a href={csv}>Exportar CSV</a>
                    </Button>
                </div>

                <FiltroPeriodo
                    de={periodo.de}
                    ate={periodo.ate}
                    prefixo="painel"
                    aoMudar={(novo) =>
                        router.get('/meu-painel', novo, {
                            preserveState: true,
                            preserveScroll: true,
                        })
                    }
                />

                {/* Saúde financeira (17/10/2026): a visão do dinheiro da pessoa. */}
                <section
                    aria-labelledby="titulo-saude"
                    data-tour="saude-financeira"
                >
                    <h2
                        id="titulo-saude"
                        className="mb-2 text-base font-semibold"
                    >
                        Minha saúde financeira
                    </h2>
                    <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {cartoesSaude.map((cartao) => (
                            <li
                                key={cartao.rotulo}
                                className="flex flex-col gap-1 rounded-md border border-border p-4"
                            >
                                <span className="text-xs text-muted-foreground">
                                    {cartao.rotulo}
                                </span>
                                <span className="text-xl font-semibold [overflow-wrap:anywhere]">
                                    {cartao.valor}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {cartao.dica}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                {/* Aulas regulares que a pessoa ministra (só aparece para quem tem programa). */}
                {aulas.programas.length > 0 && (
                    <section aria-labelledby="titulo-aulas">
                        <h2
                            id="titulo-aulas"
                            className="mb-2 text-base font-semibold"
                        >
                            Aulas regulares que ministro
                        </h2>
                        <p className="mb-2 text-sm text-muted-foreground">
                            {aulas.alunos} alunos · {aulas.alunos_em_dia} em dia
                        </p>
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {aulas.programas.map((programa) => (
                                <li
                                    key={programa.nome}
                                    className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                                >
                                    <strong>{programa.nome}</strong>
                                    <span className="text-muted-foreground">
                                        {programa.alunos} alunos pagantes no
                                        período
                                    </span>
                                    <dl className="grid grid-cols-2 gap-x-3 gap-y-1">
                                        {linhasPrograma(programa).map(
                                            ([rotulo, valor]) => (
                                                <div
                                                    key={rotulo}
                                                    className="contents"
                                                >
                                                    <dt className="text-muted-foreground">
                                                        {rotulo}
                                                    </dt>
                                                    <dd>
                                                        {formatarReais(valor)}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <h2 className="text-base font-semibold">Cursos</h2>
                <ul
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                    aria-label="Totais do período"
                >
                    {destaques.map((destaque) => (
                        <li
                            key={destaque.rotulo}
                            className="flex flex-col gap-1 rounded-md border border-border p-4"
                        >
                            <span className="text-xs text-muted-foreground">
                                {destaque.rotulo}
                            </span>
                            <span className="text-xl font-semibold">
                                {destaque.valor}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {destaque.dica}
                            </span>
                        </li>
                    ))}
                </ul>

                {cursos.length === 0 && (
                    <p className="rounded-md border border-border p-6 text-center text-sm text-muted-foreground">
                        Nenhum curso no período.
                    </p>
                )}

                <ul
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    aria-label="Seus cursos"
                >
                    {cursos.map((curso) => (
                        <li
                            key={curso.id}
                            className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                        >
                            <strong>{curso.titulo}</strong>
                            <span className="text-muted-foreground">
                                {curso.data_inicio ?? 'Sem data (gravado)'} ·{' '}
                                {curso.situacao_rotulo}
                            </span>
                            <dl className="grid grid-cols-2 gap-x-3 gap-y-1">
                                <dt className="text-muted-foreground">
                                    Confirmados
                                </dt>
                                <dd>{curso.confirmados}</dd>
                                <dt className="text-muted-foreground">
                                    Aguardando Pix
                                </dt>
                                <dd>{curso.aguardando}</dd>
                                <dt className="text-muted-foreground">
                                    Cancelados
                                </dt>
                                <dd>{curso.cancelados}</dd>
                                <dt className="text-muted-foreground">
                                    Comparecimento
                                </dt>
                                <dd>
                                    {curso.comparecimento === null
                                        ? 'sem chamada ainda'
                                        : `${curso.comparecimento}% (${curso.encontros_com_chamada} encontros)`}
                                </dd>
                                <dt className="text-muted-foreground">
                                    A receber
                                </dt>
                                <dd>
                                    {formatarReais(curso.a_receber_centavos)}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Recebido
                                </dt>
                                <dd>
                                    {formatarReais(curso.recebido_centavos)}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Certificados
                                </dt>
                                <dd>
                                    {curso.certificados}
                                    {curso.aptos_sem_certificado > 0
                                        ? ` (+${curso.aptos_sem_certificado} aptos)`
                                        : ''}
                                </dd>
                            </dl>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
MeuPainel.layout = {
    breadcrumbs: [{ title: 'Meu painel', href: '/meu-painel' }],
};
