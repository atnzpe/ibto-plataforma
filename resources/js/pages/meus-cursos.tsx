/**
 * MEUS CURSOS (desenho aprovado pelo Gleyson em 10/10/2026): a página inicial
 * do aluno, filiado, instrutor e admin de equipe.
 * - Cabeçalho: logo da equipe (ou o emblema do IBTO), nome, registro, categoria;
 * - Trilha de formação: Praticante → Monitor → Instrutor → Instrutor Expert,
 *   com "feita" (✓), "atual" e "a seguir" ESCRITOS (não só cor);
 * - Cartões dos cursos: situação e o próximo passo (pagar ou assistir).
 * Sem rolagem lateral: as etapas quebram em grade e os cartões empilham.
 */
import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dataBr } from '@/pages/catalogo/index';
import { meusCursos } from '@/routes';
import { curso as paginaDoCurso, index as catalogo } from '@/routes/catalogo';
import { ver as verCertificado } from '@/routes/certificados';
import { assistir } from '@/routes/cursos';

/** Um curso da pessoa (uma matrícula que vale). */
type MeuCurso = {
    matricula_id: number;
    curso_id: number;
    titulo: string;
    imagem_url: string | null;
    modalidade: string;
    area: string | null;
    data_inicio: string | null;
    situacao: 'aguardando_pagamento' | 'confirmada';
    situacao_rotulo: string;
    // Primeira aula liberada (só na confirmada com aulas), ou null.
    primeira_aula_id: number | null;
    // Código do certificado válido (#44), ou null.
    certificado: string | null;
};

// Texto e visual de cada estado da etapa (escrito + cor).
const ETAPA: Record<string, { texto: string; classe: string }> = {
    feita: {
        texto: '✓ Feita',
        classe: 'border-[#6b5d3a] bg-[#6b5d3a] text-white',
    },
    atual: {
        texto: 'Você está aqui',
        classe: 'border-[#c9bd94] bg-[#1c1b17] text-[#f4f1e8] ring-2 ring-[#c9bd94]',
    },
    a_seguir: {
        texto: 'A seguir',
        classe: 'border-border bg-muted text-muted-foreground',
    },
};

/**
 * @param pessoa Quem está logado (com a equipe, se filiado).
 * @param trilha Os níveis da trilha de formação, em ordem, com o estado de cada um.
 * @param cursos Os cursos da pessoa.
 * @returns A página.
 */
export default function MeusCursos({
    pessoa,
    trilha,
    cursos,
}: {
    pessoa: {
        nome: string;
        filiado: boolean;
        registro: string | null;
        categoria: string | null;
        equipe: string | null;
        logo_url: string | null;
        // O nível atual escrito. Ex.: "Monitor II" (null = sem nível).
        nivel: string | null;
    };
    trilha: { nome: string; estado: 'feita' | 'atual' | 'a_seguir' }[];
    cursos: MeuCurso[];
}) {
    // Quantos níveis já alcançou (os feitos + o atual). Ex.: 3.
    const feitas = trilha.filter((etapa) => etapa.estado !== 'a_seguir').length;

    return (
        <>
            <Head title="Meus cursos" />

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

                {/* Trilha de formação pelos NÍVEIS (decisão de 10/10/2026): a grade se ajusta à quantidade de níveis. */}
                <section
                    aria-labelledby="t-trilha"
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="t-trilha" className="font-semibold">
                            Trilha de formação
                        </h2>
                        {/* Progresso escrito: quantos níveis já passou, contando o atual. */}
                        {feitas > 0 && (
                            <span className="text-sm text-muted-foreground">
                                {pessoa.nivel} · {feitas} de {trilha.length}{' '}
                                níveis
                            </span>
                        )}
                    </div>
                    {trilha.length > 0 && (
                        <div
                            role="img"
                            aria-label={`${feitas} de ${trilha.length} níveis alcançados`}
                            className="h-3 overflow-hidden rounded-full border border-border bg-muted"
                        >
                            <div
                                className="h-full bg-[#6b5d3a]"
                                style={{
                                    width: `${(feitas / trilha.length) * 100}%`,
                                }}
                            />
                        </div>
                    )}
                    {!pessoa.filiado && (
                        <p className="text-sm text-muted-foreground">
                            A trilha começa quando você se filia ao IBTO.
                        </p>
                    )}
                    {pessoa.filiado && pessoa.nivel === null && (
                        <p className="text-sm text-muted-foreground">
                            Seu nível ainda não foi registrado. Fale com o
                            administrador da sua equipe.
                        </p>
                    )}
                    <ol className="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-2">
                        {trilha.map((etapa, indice) => (
                            <li
                                key={etapa.nome}
                                aria-current={
                                    etapa.estado === 'atual'
                                        ? 'step'
                                        : undefined
                                }
                                className={`flex flex-col gap-1 rounded-lg border p-3 ${ETAPA[etapa.estado].classe}`}
                            >
                                <span className="text-xs opacity-80">
                                    Etapa {indice + 1}
                                </span>
                                <strong className="text-sm">
                                    {etapa.nome}
                                </strong>
                                <span className="text-xs">
                                    {ETAPA[etapa.estado].texto}
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>

                {/* Os cursos da pessoa. */}
                <section
                    aria-labelledby="t-cursos"
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="t-cursos" className="font-semibold">
                            Meus cursos
                        </h2>
                        <Link
                            href={catalogo()}
                            className="text-sm underline underline-offset-4"
                        >
                            Ver o catálogo de cursos
                        </Link>
                    </div>

                    {cursos.length === 0 && (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-border p-6 text-center">
                            <p className="text-sm text-muted-foreground">
                                Você ainda não está matriculado em nenhum curso.
                            </p>
                            <Button asChild className="min-h-11">
                                <Link href={catalogo()}>Escolher um curso</Link>
                            </Button>
                        </div>
                    )}

                    {/* 1 coluna no celular, 2 no tablet, 3 na tela larga. */}
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {cursos.map((meu) => (
                            <li
                                key={meu.matricula_id}
                                className="flex flex-col overflow-hidden rounded-xl border border-border bg-card"
                            >
                                <div className="flex h-28 items-center justify-center bg-[#1c1b17]">
                                    {meu.imagem_url ? (
                                        <img
                                            src={meu.imagem_url}
                                            alt=""
                                            aria-hidden="true"
                                            className="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <span
                                            aria-hidden="true"
                                            className="font-[Audiowide,sans-serif] text-2xl text-[#c9bd94]"
                                        >
                                            IBTO
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-1 flex-col gap-2 p-4 text-sm">
                                    <strong className="text-base">
                                        {meu.titulo}
                                    </strong>
                                    <span className="text-muted-foreground">
                                        {[
                                            meu.modalidade,
                                            meu.area,
                                            meu.data_inicio &&
                                                dataBr(meu.data_inicio),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                    {/* Situação escrita (não só cor). */}
                                    <span
                                        className={`w-fit rounded px-1.5 py-0.5 text-xs font-medium ${
                                            meu.situacao === 'confirmada'
                                                ? 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100'
                                                : 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'
                                        }`}
                                    >
                                        {meu.situacao_rotulo}
                                    </span>

                                    {/* O próximo passo: certificado (concluído), assistir (confirmada com aulas) ou ver o curso. */}
                                    <div className="mt-auto pt-2">
                                        {meu.certificado !== null ? (
                                            <Button
                                                asChild
                                                className="min-h-11 w-full"
                                            >
                                                {/* Link comum: abre a página limpa do certificado (imprimir / PDF). */}
                                                <a
                                                    href={
                                                        verCertificado(
                                                            meu.certificado,
                                                        ).url
                                                    }
                                                >
                                                    Ver certificado
                                                </a>
                                            </Button>
                                        ) : meu.primeira_aula_id !== null ? (
                                            <Button
                                                asChild
                                                className="min-h-11 w-full"
                                            >
                                                <Link
                                                    href={assistir({
                                                        curso: meu.curso_id,
                                                        aula: meu.primeira_aula_id,
                                                    })}
                                                >
                                                    Assistir
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="min-h-11 w-full"
                                            >
                                                <Link
                                                    href={paginaDoCurso(
                                                        meu.curso_id,
                                                    )}
                                                >
                                                    {meu.situacao ===
                                                    'aguardando_pagamento'
                                                        ? 'Pagar e enviar o comprovante'
                                                        : 'Ver o curso'}
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
MeusCursos.layout = {
    breadcrumbs: [{ title: 'Meus cursos', href: meusCursos() }],
};
