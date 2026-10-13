/**
 * DETALHES DO CURSO no Comando Geral (tela 3 do desenho aprovado pelo
 * Gleyson em 10/10/2026): o resumo em passos (vagas → pagamento →
 * presença → certificado) e os INSCRITOS com o próximo passo de cada um.
 * LGPD: só o registro (ou o nº da matrícula) e a equipe; o nome fica na ficha.
 * Presença (#44): os encontros com o botão da chamada e o "x de y" de cada
 * inscrito. O certificado chega com a #44 ("em breve").
 * Sem rolagem lateral: resumo em grade e inscritos em cartões.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dataBr } from '@/pages/catalogo/index';
import { chamada, edit, index } from '@/routes/comando-geral/cursos';
import { ficha, index as matriculas } from '@/routes/comando-geral/matriculas';

/** Um inscrito na lista. */
type Inscrito = {
    id: number;
    // null = não filiado (aparece o nº da matrícula).
    registro: string | null;
    equipe: string;
    situacao: 'aguardando_pagamento' | 'confirmada' | 'cancelada';
    situacao_rotulo: string;
    tem_comprovante: boolean;
    // Presenças: veio em X de Y encontros com chamada.
    presencas: number;
    encontros_com_chamada: number;
};

// Cor de cada situação (o texto também aparece escrito).
const SELO: Record<Inscrito['situacao'], string> = {
    aguardando_pagamento:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    confirmada:
        'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
    cancelada: 'bg-muted text-muted-foreground',
};

/**
 * @param curso O curso (título, modalidade, data, local).
 * @param resumo Quantos em cada passo.
 * @param inscritos Todas as matrículas do curso (também as canceladas).
 * @param pode_confirmar true = pode abrir a ficha e confirmar o PIX.
 * @param encontros Os encontros (aulas com data), com a chamada feita ou não.
 * @param pode_chamada true = pode fazer a chamada (dono, admin da equipe, Comando Geral).
 * @returns A tela.
 */
export default function DetalhesCurso({
    curso,
    resumo,
    inscritos,
    pode_confirmar,
    encontros,
    pode_chamada,
}: {
    curso: {
        id: number;
        titulo: string;
        modalidade: string;
        data_inicio: string | null;
        local: string | null;
        situacao_rotulo: string;
        pode_editar: boolean;
    };
    resumo: {
        vagas: number | null;
        ocupadas: number;
        aguardando: number;
        confirmadas: number;
        canceladas: number;
        // Presentes na última chamada (null = nenhuma) e o título do encontro.
        presentes_ultima: number | null;
        ultima_chamada: string | null;
    };
    inscritos: Inscrito[];
    pode_confirmar: boolean;
    encontros: {
        id: number;
        titulo: string;
        data: string | null;
        hora_inicio: string | null;
        chamada_feita: boolean;
    }[];
    pode_chamada: boolean;
}) {
    // Mensagem da última ação (ex.: "Chamada registrada: 9 de 12 presentes").
    const { sucesso } = usePage().props;
    // Os cartões do resumo, na ordem dos passos do desenho.
    const passos: { passo: string; numero: string; texto: string }[] = [
        {
            passo: 'Vagas',
            numero:
                resumo.vagas === null
                    ? String(resumo.ocupadas)
                    : `${resumo.ocupadas} / ${resumo.vagas}`,
            texto: resumo.vagas === null ? 'ocupadas (sem limite)' : 'ocupadas',
        },
        {
            passo: '1 · Pagamento',
            numero: String(resumo.aguardando),
            texto: 'aguardando',
        },
        {
            passo: '2 · Vaga confirmada',
            numero: String(resumo.confirmadas),
            texto: 'pagamento confirmado',
        },
        {
            passo: '3 · Presença',
            // Presentes na última chamada (— = nenhuma chamada ainda).
            numero:
                resumo.presentes_ultima === null
                    ? '—'
                    : String(resumo.presentes_ultima),
            texto:
                resumo.ultima_chamada !== null
                    ? `presentes em "${resumo.ultima_chamada}"`
                    : 'nenhuma chamada ainda',
        },
        { passo: '4 · Certificado', numero: '—', texto: 'em breve (#44)' },
    ];

    return (
        <>
            <Head title={`Inscritos · ${curso.titulo}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {[
                                curso.modalidade,
                                curso.data_inicio && dataBr(curso.data_inicio),
                                curso.local,
                                curso.situacao_rotulo,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {curso.titulo}
                        </h1>
                    </div>
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href={edit(curso.id)}>
                            {curso.pode_editar ? 'Editar curso' : 'Ver o curso'}
                        </Link>
                    </Button>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* Resumo em passos: 2 colunas no celular, 5 na tela larga. */}
                <section
                    aria-label="Resumo do curso"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                >
                    {passos.map((passo) => (
                        <div
                            key={passo.passo}
                            className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4"
                        >
                            <span className="text-xs text-muted-foreground">
                                {passo.passo}
                            </span>
                            <strong className="text-2xl font-semibold">
                                {passo.numero}
                            </strong>
                            <span className="text-xs text-muted-foreground">
                                {passo.texto}
                            </span>
                        </div>
                    ))}
                </section>

                {/* Encontros e chamada (#44): cada encontro com o botão da chamada. */}
                {encontros.length > 0 && (
                    <section
                        aria-labelledby="t-encontros"
                        className="flex flex-col gap-3"
                    >
                        <h2 id="t-encontros" className="font-semibold">
                            Encontros e chamada
                        </h2>
                        {/* 1 coluna no celular, 2 no tablet, 3 na tela larga. */}
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {encontros.map((encontro) => (
                                <li
                                    key={encontro.id}
                                    className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                                >
                                    <strong>{encontro.titulo}</strong>
                                    <span className="text-muted-foreground">
                                        {encontro.data && dataBr(encontro.data)}
                                        {encontro.hora_inicio &&
                                            ` às ${encontro.hora_inicio}`}
                                    </span>
                                    {/* Estado escrito (não só cor). */}
                                    <span className="text-xs font-medium">
                                        {encontro.chamada_feita
                                            ? '✓ Chamada feita'
                                            : 'Chamada ainda não feita'}
                                    </span>
                                    {pode_chamada && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant={
                                                encontro.chamada_feita
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            className="mt-auto min-h-11"
                                        >
                                            <Link
                                                href={chamada({
                                                    curso: curso.id,
                                                    aula: encontro.id,
                                                })}
                                            >
                                                {encontro.chamada_feita
                                                    ? 'Refazer a chamada'
                                                    : 'Fazer a chamada'}
                                            </Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                <section
                    aria-labelledby="t-inscritos"
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="t-inscritos" className="font-semibold">
                            Inscritos ({inscritos.length})
                        </h2>
                        <span className="text-xs text-muted-foreground">
                            Só o registro (LGPD).{' '}
                            {resumo.canceladas > 0 &&
                                `${resumo.canceladas} cancelada(s) no histórico.`}
                        </span>
                    </div>

                    {inscritos.length === 0 && (
                        <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                            Ninguém se inscreveu ainda.
                        </p>
                    )}

                    {/* Um cartão por inscrito: 1 coluna no celular, 2 no tablet, 3 na tela larga. */}
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {inscritos.map((inscrito) => (
                            <li
                                key={inscrito.id}
                                className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    <strong className="font-mono">
                                        {inscrito.registro ??
                                            `Matrícula nº ${inscrito.id}`}
                                    </strong>
                                    <span
                                        className={`rounded px-1.5 py-0.5 text-xs font-medium ${SELO[inscrito.situacao]}`}
                                    >
                                        {inscrito.situacao_rotulo}
                                    </span>
                                </span>
                                <span className="text-muted-foreground">
                                    {inscrito.equipe}
                                </span>

                                {/* O próximo passo de cada um. */}
                                {inscrito.situacao ===
                                    'aguardando_pagamento' && (
                                    <span className="text-xs">
                                        Próximo passo:{' '}
                                        {inscrito.tem_comprovante
                                            ? 'conferir o comprovante e confirmar o pagamento'
                                            : 'aguardar o comprovante do PIX'}
                                    </span>
                                )}
                                {inscrito.situacao === 'confirmada' && (
                                    <span className="text-xs">
                                        {/* Presença escrita: veio em X de Y encontros com chamada. */}
                                        {inscrito.encontros_com_chamada > 0
                                            ? `Presença: ${inscrito.presencas} de ${inscrito.encontros_com_chamada} encontro(s)`
                                            : 'Próximo passo: presença nos encontros'}
                                    </span>
                                )}

                                {/* Ficha (nome) e confirmação: só para quem confirma pagamentos. */}
                                {pode_confirmar && (
                                    <div className="mt-auto grid grid-cols-2 gap-2 pt-1">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="min-h-11"
                                        >
                                            <Link href={ficha(inscrito.id)}>
                                                Abrir ficha
                                            </Link>
                                        </Button>
                                        {inscrito.situacao ===
                                        'aguardando_pagamento' ? (
                                            <Button
                                                asChild
                                                size="sm"
                                                className="min-h-11"
                                            >
                                                <Link
                                                    href={matriculas({
                                                        query: {
                                                            curso: curso.id,
                                                            situacao:
                                                                'aguardando_pagamento',
                                                        },
                                                    })}
                                                >
                                                    Confirmar pagamento
                                                </Link>
                                            </Button>
                                        ) : (
                                            <span aria-hidden="true" />
                                        )}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
DetalhesCurso.layout = {
    breadcrumbs: [{ title: 'Cursos', href: index() }],
};
