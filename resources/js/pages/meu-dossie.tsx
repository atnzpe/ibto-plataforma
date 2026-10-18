/**
 * MEU DOSSIÊ (esboço de 08/10/2026: "todo filiado tem um dossiê"; desenho
 * aprovado em 10/10/2026): o histórico da pessoa no IBTO, num lugar só.
 * - Cabeçalho: logo da equipe (ou o emblema do IBTO), nome, registro;
 * - Resumo: cursos concluídos, horas certificadas e presenças;
 * - Graduação: o nível atual;
 * - Certificados: válidos e cancelados, com "Ver" e "Validar";
 * - Cursos: os encontros (dia, presente/falta ESCRITO, não só cor) e o que foi
 *   trabalhado em cada um; e o progresso das aulas online.
 * Sem rolagem lateral: tudo em cartões que empilham no celular.
 */
import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dataBr } from '@/pages/catalogo/index';
import { meuDossie } from '@/routes';
import { index as catalogo } from '@/routes/catalogo';
import {
    validar as validarCertificado,
    ver as verCertificado,
} from '@/routes/certificados';

/** Um encontro do curso, com a presença da pessoa. */
type Encontro = {
    id: number;
    titulo: string;
    data: string | null;
    // 'sem_chamada' = a chamada ainda não foi feita (encontro futuro ou pendente).
    presenca: 'presente' | 'falta' | 'sem_chamada';
    // O que foi trabalhado (escrito na chamada); null = nada registrado.
    trabalhado: string | null;
};

/** Um curso do dossiê (matrícula confirmada). */
type CursoDossie = {
    titulo: string;
    modalidade: string;
    carga_horaria: number | null;
    // Código do certificado válido, ou null.
    certificado: string | null;
    presencas: number;
    encontros_com_chamada: number;
    encontros: Encontro[];
    // Aulas online: null = o curso não tem aulas online para a pessoa.
    progresso: { assistidas: number; total: number; percentual: number } | null;
};

// Texto e cor de cada presença (o texto vai escrito; a cor só ajuda).
const PRESENCA: Record<
    Encontro['presenca'],
    { texto: string; classe: string }
> = {
    presente: {
        texto: '✓ Presente',
        classe: 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
    },
    falta: {
        texto: '✗ Falta',
        classe: 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
    },
    sem_chamada: {
        texto: 'Sem chamada ainda',
        classe: 'bg-muted text-muted-foreground',
    },
};

/**
 * @param pessoa Quem é: nome, registro, equipe, logo e nível.
 * @param resumo Os três números do topo. Ex.: { concluidos: 2, horas: 24, presencas: 7 }
 * @param certificados Todos os certificados (válidos e cancelados).
 * @param cursos Os cursos confirmados, com os encontros e o progresso.
 * @returns A tela do dossiê.
 */
export default function MeuDossie({
    pessoa,
    resumo,
    certificados,
    cursos,
    aulas_regulares = [],
}: {
    pessoa: {
        nome: string;
        filiado: boolean;
        registro: string | null;
        categoria: string | null;
        equipe: string | null;
        logo_url: string | null;
        nivel: string | null;
    };
    resumo: { concluidos: number; horas: number; presencas: number };
    certificados: {
        codigo: string;
        curso: string;
        carga_horaria: number | null;
        concluido_em: string;
        valido: boolean;
    }[];
    cursos: CursoDossie[];
    // Aulas regulares (#80): por programa, a situação e a frequência dos últimos meses.
    aulas_regulares?: {
        programa: string;
        situacao: string;
        meses: { mes: string; presencas: number; aulas: number }[];
    }[];
}) {
    // Os três números do resumo, na ordem em que aparecem.
    const numeros = [
        { rotulo: 'Cursos concluídos', valor: resumo.concluidos },
        { rotulo: 'Horas certificadas', valor: resumo.horas },
        { rotulo: 'Presenças em encontros', valor: resumo.presencas },
    ];

    return (
        <>
            <Head title="Meu dossiê" />

            <div className="flex flex-col gap-6 p-4">
                {/* Cabeçalho: logo da equipe (ou emblema do IBTO) + quem é. */}
                <section className="flex items-center gap-4 rounded-xl bg-[#1c1b17] p-4 text-[#f4f1e8]">
                    {pessoa.logo_url ? (
                        <img
                            src={pessoa.logo_url}
                            alt={`Logo da equipe ${pessoa.equipe ?? ''}`}
                            className="size-16 shrink-0 rounded-full bg-white object-contain"
                        />
                    ) : (
                        <AppLogoIcon className="size-16 shrink-0" />
                    )}
                    <div className="min-w-0">
                        <p className="text-sm text-[#c9bd94]">Meu dossiê</p>
                        <h1 className="truncate font-[Audiowide,sans-serif] text-xl">
                            {pessoa.nome}
                        </h1>
                        <p className="text-sm text-[#c9bd94]">
                            {pessoa.filiado
                                ? [
                                      pessoa.categoria,
                                      pessoa.equipe ?? 'IBTO',
                                      pessoa.registro,
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')
                                : 'Aluno do IBTO'}
                        </p>
                    </div>
                </section>

                {/* Resumo: 3 números, lado a lado (empilham só em tela muito estreita). */}
                <section
                    aria-label="Resumo do dossiê"
                    className="grid grid-cols-3 gap-2"
                >
                    {numeros.map((numero) => (
                        <div
                            key={numero.rotulo}
                            className="flex flex-col gap-1 rounded-lg border border-border p-3"
                        >
                            <strong className="text-2xl">{numero.valor}</strong>
                            <span className="text-xs text-muted-foreground">
                                {numero.rotulo}
                            </span>
                        </div>
                    ))}
                </section>

                {/* Graduação: o nível atual (o histórico chega com a #46). */}
                <section
                    aria-labelledby="t-graduacao"
                    className="flex flex-col gap-2"
                >
                    <h2 id="t-graduacao" className="font-semibold">
                        Graduação
                    </h2>
                    <p className="rounded-lg border border-border p-3 text-sm">
                        {/* Três casos: não filiado, filiado sem nível e filiado com nível. */}
                        {!pessoa.filiado
                            ? 'A graduação começa quando você se filia ao IBTO.'
                            : pessoa.nivel === null
                              ? 'Seu nível ainda não foi registrado. Fale com o administrador da sua equipe.'
                              : `Nível atual: ${pessoa.nivel}`}
                    </p>
                </section>

                {/* Certificados: válidos e cancelados (o cancelado aparece riscado e escrito). */}
                <section
                    aria-labelledby="t-certificados"
                    className="flex flex-col gap-2"
                >
                    <h2 id="t-certificados" className="font-semibold">
                        Certificados ({certificados.length})
                    </h2>
                    {certificados.length === 0 && (
                        <p className="rounded-lg border border-border p-3 text-sm text-muted-foreground">
                            Nenhum certificado ainda. Ele chega quando você
                            conclui um curso com a presença mínima.
                        </p>
                    )}
                    <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {certificados.map((certificado) => (
                            <li
                                key={certificado.codigo}
                                className="flex flex-col gap-2 rounded-lg border border-border p-3 text-sm"
                            >
                                <strong
                                    className={
                                        certificado.valido ? '' : 'line-through'
                                    }
                                >
                                    {certificado.curso}
                                </strong>
                                <span className="text-muted-foreground">
                                    {[
                                        certificado.carga_horaria &&
                                            `${certificado.carga_horaria} h`,
                                        `concluído em ${dataBr(certificado.concluido_em)}`,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                                <span className="font-mono text-xs">
                                    {certificado.codigo}
                                    {/* Cancelado: escrito, não só riscado. */}
                                    {!certificado.valido && ' · CANCELADO'}
                                </span>
                                {/* Só o válido tem os botões. Ver = a página para imprimir; Validar = a pública. */}
                                {certificado.valido && (
                                    <span className="grid grid-cols-2 gap-2">
                                        <Button asChild className="min-h-11">
                                            {/* Link comum: abre a página limpa do certificado (imprimir / PDF). */}
                                            <a
                                                href={
                                                    verCertificado(
                                                        certificado.codigo,
                                                    ).url
                                                }
                                            >
                                                Ver
                                            </a>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="min-h-11"
                                        >
                                            <a
                                                href={
                                                    validarCertificado(
                                                        certificado.codigo,
                                                    ).url
                                                }
                                            >
                                                Validar
                                            </a>
                                        </Button>
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>

                {/* Cursos: encontros (presença + o que foi trabalhado) e progresso online. */}
                <section
                    aria-labelledby="t-cursos"
                    className="flex flex-col gap-3"
                >
                    <h2 id="t-cursos" className="font-semibold">
                        Cursos ({cursos.length})
                    </h2>
                    {cursos.length === 0 && (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-border p-6 text-center">
                            <p className="text-sm text-muted-foreground">
                                Nenhum curso confirmado ainda.
                            </p>
                            <Button asChild className="min-h-11">
                                <Link href={catalogo()}>Escolher um curso</Link>
                            </Button>
                        </div>
                    )}
                    {cursos.map((curso) => (
                        <article
                            key={curso.titulo + curso.modalidade}
                            className="flex flex-col gap-3 rounded-xl border border-border p-4 text-sm"
                        >
                            <header className="flex flex-wrap items-baseline justify-between gap-2">
                                <h3 className="text-base font-semibold">
                                    {curso.titulo}
                                </h3>
                                <span className="text-muted-foreground">
                                    {[
                                        curso.modalidade,
                                        curso.carga_horaria &&
                                            `${curso.carga_horaria} h`,
                                        curso.certificado
                                            ? '✓ Certificado'
                                            : 'Sem certificado ainda',
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </header>

                            {/* Progresso das aulas online (só quando o curso tem aulas para a pessoa). */}
                            {curso.progresso && (
                                <p className="text-muted-foreground">
                                    Aulas online: {curso.progresso.assistidas}{' '}
                                    de {curso.progresso.total} assistidas (
                                    {curso.progresso.percentual}%)
                                </p>
                            )}

                            {/* Encontros: só quando o curso tem encontros com data. */}
                            {curso.encontros.length > 0 && (
                                <>
                                    <p className="text-muted-foreground">
                                        Presença: {curso.presencas} de{' '}
                                        {curso.encontros_com_chamada} encontros
                                        com chamada
                                    </p>
                                    <ol className="flex flex-col divide-y divide-border rounded-md border border-border">
                                        {curso.encontros.map((encontro) => (
                                            <li
                                                key={encontro.id}
                                                className="flex flex-col gap-1 p-3"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span>
                                                        {encontro.data &&
                                                            `${dataBr(encontro.data)} · `}
                                                        {encontro.titulo}
                                                    </span>
                                                    <span
                                                        className={`rounded px-1.5 py-0.5 text-xs font-medium ${PRESENCA[encontro.presenca].classe}`}
                                                    >
                                                        {
                                                            PRESENCA[
                                                                encontro
                                                                    .presenca
                                                            ].texto
                                                        }
                                                    </span>
                                                </div>
                                                {/* O que foi trabalhado: as quebras de linha digitadas na chamada são mantidas. */}
                                                {encontro.trabalhado && (
                                                    <p className="whitespace-pre-line text-muted-foreground">
                                                        <strong>
                                                            Trabalhado:
                                                        </strong>{' '}
                                                        {encontro.trabalhado}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                </>
                            )}
                        </article>
                    ))}
                </section>

                {/* Aulas regulares (#80): a frequência mês a mês (sem certificado). Só aparece para quem assina. */}
                {aulas_regulares.length > 0 && (
                    <section
                        aria-labelledby="t-aulas-regulares"
                        className="flex flex-col gap-3"
                    >
                        <h2
                            id="t-aulas-regulares"
                            className="text-lg font-semibold"
                        >
                            Aulas regulares
                        </h2>
                        {aulas_regulares.map((a) => (
                            <article
                                key={a.programa}
                                className="flex flex-col gap-2 rounded-md border p-4 text-sm"
                            >
                                <p className="font-medium">
                                    {a.programa}{' '}
                                    <span className="font-normal text-muted-foreground">
                                        · {a.situacao}
                                    </span>
                                </p>
                                {a.meses.length === 0 ? (
                                    <p className="text-muted-foreground">
                                        Nenhuma chamada registrada ainda.
                                    </p>
                                ) : (
                                    <ul className="flex flex-wrap gap-2">
                                        {a.meses.map((m) => (
                                            <li
                                                key={m.mes}
                                                className="rounded-md border px-3 py-2"
                                            >
                                                {/* Ex.: "2026-10: 7 de 8 aulas". */}
                                                {m.mes}: {m.presencas} de{' '}
                                                {m.aulas} aulas
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </article>
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}

// O menu lateral e o "caminho" no topo da página (breadcrumbs).
MeuDossie.layout = {
    breadcrumbs: [{ title: 'Meu dossiê', href: meuDossie() }],
};
