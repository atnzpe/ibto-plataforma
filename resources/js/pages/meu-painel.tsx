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
}: {
    periodo: { de: string; ate: string };
    cursos: LinhaCurso[];
    totais: Totais;
}) {
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
                            Os números dos seus cursos (ou da sua equipe) no
                            período. Sem dados pessoais dos alunos.
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
