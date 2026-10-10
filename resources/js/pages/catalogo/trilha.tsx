/**
 * TRILHA EM FLUXOGRAMA (#38, bloco C4b), pública. Referência: o fluxograma da
 * Solyd que o Gleyson mandou (10/10/2026): um ponto de partida, as etapas de
 * cima para baixo, ligadas por linhas, e em cada etapa os cursos LADO A LADO
 * (os "galhos"), cada cartão com as horas e o número de aulas. Fundo escuro
 * com as linhas douradas do IBTO. Curso ainda não publicado: "Em breve".
 * Responsivo: na tela larga os cursos da mesma etapa ficam lado a lado; no
 * celular, um embaixo do outro (sem rolagem lateral).
 */
import { Head, Link } from '@inertiajs/react';
import {
    curso as paginaDoCurso,
    index as catalogo,
    trilha as paginaDaTrilha,
} from '@/routes/catalogo';

/** Um curso dentro de uma etapa. */
type CursoDaEtapa = {
    id: number;
    titulo: string;
    imagem_url: string | null;
    carga_horaria: number | null;
    aulas: number;
    modalidade: string;
    disponivel: boolean;
};

/**
 * O cartão de um curso no fluxograma: capa, nome, horas e aulas.
 * Disponível vira link para a página do curso; senão, "Em breve".
 */
function CartaoEtapa({ curso }: { curso: CursoDaEtapa }) {
    // O miolo do cartão (igual para o link e para o "Em breve").
    const miolo = (
        <>
            <div className="flex h-24 items-center justify-center">
                {curso.imagem_url ? (
                    <img
                        src={curso.imagem_url}
                        alt=""
                        aria-hidden="true"
                        className="h-full w-full rounded-lg object-contain"
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
            <strong className="text-center text-sm font-semibold tracking-wide text-[#e9e2cc] uppercase">
                {curso.titulo}
            </strong>
            {/* Horas e aulas: escritos (não só ícone), como no exemplo. */}
            <span className="flex justify-center gap-2 text-xs">
                <span className="rounded-md bg-[#6b5d3a]/40 px-2 py-0.5 text-[#e9e2cc]">
                    {curso.carga_horaria ?? 0} h
                </span>
                <span className="rounded-md bg-[#6b5d3a]/40 px-2 py-0.5 text-[#e9e2cc]">
                    {curso.aulas} aula(s)
                </span>
            </span>
            {!curso.disponivel && (
                <span className="text-center text-xs font-semibold text-[#c9bd94]">
                    Em breve
                </span>
            )}
        </>
    );

    const classe =
        'relative flex flex-col gap-2 rounded-xl border border-[#6b5d3a] bg-gradient-to-br from-[#2a2721] to-[#14130f] p-4';

    return curso.disponivel ? (
        <Link
            href={paginaDoCurso(curso.id)}
            className={`${classe} no-underline transition hover:border-[#c9bd94]`}
            aria-label={`Ver o curso ${curso.titulo}`}
        >
            {miolo}
        </Link>
    ) : (
        <div className={`${classe} opacity-70`}>{miolo}</div>
    );
}

/**
 * @param trilha A trilha. Ex.: { nome: 'Formação do Praticante ao Instrutor Expert' }
 * @param etapas As etapas em ordem, cada uma com os seus cursos.
 * @param trilhas Todas as trilhas ativas (para trocar no topo).
 * @returns A tela do fluxograma.
 */
export default function TrilhaFluxograma({
    trilha,
    etapas,
    trilhas,
}: {
    trilha: { id: number; nome: string; descricao: string | null };
    etapas: { etapa: number; cursos: CursoDaEtapa[] }[];
    trilhas: { id: number; nome: string }[];
}) {
    return (
        <>
            <Head title={`Trilha: ${trilha.nome}`} />

            <div className="flex flex-col gap-6">
                <Link
                    href={catalogo()}
                    className="text-sm text-[#6b5d3a] underline-offset-4 hover:underline"
                >
                    ← Todos os cursos
                </Link>

                {/* Troca de trilha ("Todas as trilhas" do exemplo): quebra linha, sem rolagem lateral. */}
                {trilhas.length > 1 && (
                    <nav
                        aria-label="Trilhas de formação"
                        className="flex flex-wrap gap-2"
                    >
                        {trilhas.map((outra) => (
                            <Link
                                key={outra.id}
                                href={paginaDaTrilha(outra.id)}
                                aria-current={
                                    outra.id === trilha.id ? 'page' : undefined
                                }
                                className={`inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-medium no-underline ${
                                    outra.id === trilha.id
                                        ? 'bg-[#6b5d3a] text-white'
                                        : 'border border-[#6b5d3a] text-[#3f3a2c] hover:bg-[#efe8d6]'
                                }`}
                            >
                                {outra.nome}
                            </Link>
                        ))}
                    </nav>
                )}

                <section
                    aria-labelledby="t-trilha"
                    className="flex flex-col items-center gap-0 rounded-2xl bg-[#1c1b17] px-4 py-8 sm:px-8"
                >
                    <h1
                        id="t-trilha"
                        className="text-center font-[Audiowide,sans-serif] text-2xl font-normal tracking-wide text-[#f4f1e8] uppercase"
                    >
                        {trilha.nome}
                    </h1>
                    {trilha.descricao && (
                        <p className="mt-2 max-w-2xl text-center text-sm text-[#c9bd94]">
                            {trilha.descricao}
                        </p>
                    )}

                    {/* Ponto de partida e a primeira linha. */}
                    <span
                        aria-hidden="true"
                        className="mt-6 size-5 rounded-full bg-[#c9bd94]"
                    />

                    {etapas.length === 0 && (
                        <p className="mt-4 text-sm text-[#e9e2cc]">
                            Esta trilha ainda não tem cursos.
                        </p>
                    )}

                    <ol className="flex w-full flex-col items-center">
                        {etapas.map((etapa) => (
                            <li
                                key={etapa.etapa}
                                className="flex w-full flex-col items-center"
                            >
                                {/* Linha vertical que liga a etapa anterior a esta. */}
                                <span
                                    aria-hidden="true"
                                    className="h-8 w-0.5 bg-[#6b5d3a]"
                                />
                                <span className="sr-only">
                                    Etapa {etapa.etapa}
                                </span>

                                {/* Galhos: com mais de um curso, uma linha horizontal liga os cartões (só na tela larga). */}
                                <div
                                    className="relative grid w-full gap-4"
                                    style={{
                                        maxWidth: `${Math.min(etapa.cursos.length, 3) * 18}rem`,
                                    }}
                                >
                                    {etapa.cursos.length > 1 && (
                                        <span
                                            aria-hidden="true"
                                            className="absolute top-0 hidden h-0.5 bg-[#6b5d3a] sm:block"
                                            style={{
                                                left: `${50 / Math.min(etapa.cursos.length, 3)}%`,
                                                right: `${50 / Math.min(etapa.cursos.length, 3)}%`,
                                            }}
                                        />
                                    )}
                                    <div
                                        className={`grid gap-4 ${etapa.cursos.length > 1 ? 'sm:pt-4' : ''} ${
                                            etapa.cursos.length === 2
                                                ? 'sm:grid-cols-2'
                                                : etapa.cursos.length >= 3
                                                  ? 'sm:grid-cols-2 lg:grid-cols-3'
                                                  : ''
                                        }`}
                                    >
                                        {etapa.cursos.map((curso) => (
                                            <CartaoEtapa
                                                key={curso.id}
                                                curso={curso}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </>
    );
}
