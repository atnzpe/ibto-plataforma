/**
 * CATÁLOGO de cursos (#38, bloco C4a), público: os cursos PUBLICADOS, com
 * filtros por área, modalidade e acesso e busca pelo nome. Cada cartão segue
 * o desenho aprovado (10/10/2026) e os cartazes do IBTO: capa, modalidade,
 * área, "Exclusivo para filiados", quando, onde, investimento e "Vagas limitadas".
 * Responsivo: grade de cartões que vira uma coluna no celular.
 */
import { Head, Link, router } from '@inertiajs/react';
import { formatarReais } from '@/lib/dinheiro';
import { curso as paginaDoCurso, index } from '@/routes/catalogo';

/** Um curso como o cartão mostra. */
export type CartaoCurso = {
    id: number;
    titulo: string;
    chamada: string | null;
    imagem_url: string | null;
    area: string | null;
    modalidade: string;
    presencial: boolean;
    restrito: boolean;
    tipo: string;
    valor_centavos: number;
    data_inicio: string | null;
    local: string | null;
    carga_horaria: number | null;
    vagas: number | null;
    instrutor: string | null;
};

type Filtros = {
    area: number | null;
    modalidade: string | null;
    acesso: string | null;
    busca: string;
};

// Classe comum dos "selos" (etiquetas arredondadas).
const selo = 'rounded-full px-3 py-0.5 text-xs';

/**
 * '2026-10-11' → '11/10/2026'.
 */
export function dataBr(data: string): string {
    const [ano, mes, dia] = data.split('-');

    return `${dia}/${mes}/${ano}`;
}

/**
 * Um cartão do catálogo (também usado nas trilhas).
 *
 * @param curso O curso. Ex.: { titulo: 'Combate Corpo a Corpo', ... }
 */
export function Cartao({ curso }: { curso: CartaoCurso }) {
    return (
        <article className="flex flex-col overflow-hidden rounded-2xl border border-[#e4dccb] bg-white">
            {/* Capa: a imagem INTEIRA (cartaz em pé ou deitado), sobre fundo escuro. */}
            <div className="flex aspect-[4/3] items-center justify-center bg-[#1c1b17]">
                {curso.imagem_url ? (
                    <img
                        src={curso.imagem_url}
                        alt={`Cartaz do curso ${curso.titulo}`}
                        className="h-full w-full object-contain"
                        loading="lazy"
                    />
                ) : (
                    <span className="font-[Audiowide,sans-serif] text-3xl text-[#c9bd94]">
                        IBTO
                    </span>
                )}
            </div>
            <div className="flex flex-1 flex-col gap-2 p-4">
                <div className="flex flex-wrap gap-1.5">
                    <span
                        className={`${selo} border border-[#6b5d3a] font-semibold text-[#6b5d3a]`}
                    >
                        {curso.modalidade}
                    </span>
                    {curso.area && (
                        <span className={`${selo} bg-[#efe8d6] text-[#3f3a2c]`}>
                            {curso.area}
                        </span>
                    )}
                    {curso.restrito && (
                        <span className={`${selo} bg-[#1c1b17] text-[#c9bd94]`}>
                            Exclusivo para filiados
                        </span>
                    )}
                </div>
                <h2 className="font-[Audiowide,sans-serif] text-lg leading-tight font-normal">
                    {curso.titulo}
                </h2>
                {curso.chamada && (
                    <p className="text-sm text-[#4a4536] italic">
                        {curso.chamada}
                    </p>
                )}
                <p className="text-sm text-[#4a4536]">
                    {curso.data_inicio
                        ? dataBr(curso.data_inicio)
                        : curso.presencial
                          ? 'Data a definir'
                          : 'No seu ritmo'}
                    {curso.local && ` · ${curso.local}`}
                    {curso.carga_horaria && ` · ${curso.carga_horaria} h`}
                </p>
                {curso.instrutor && (
                    <p className="text-sm">
                        Instrutor:{' '}
                        <strong className="font-medium">
                            {curso.instrutor}
                        </strong>
                    </p>
                )}
                <div className="mt-auto flex items-end justify-between gap-2 pt-2">
                    <strong className="text-xl font-semibold">
                        {curso.valor_centavos === 0
                            ? 'Gratuito'
                            : formatarReais(curso.valor_centavos)}
                    </strong>
                    {curso.vagas && (
                        <span className="text-xs font-semibold text-[#a33a00]">
                            Vagas limitadas
                        </span>
                    )}
                </div>
                <Link
                    href={paginaDoCurso(curso.id)}
                    className="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#1c1b17] text-sm font-medium text-white no-underline hover:bg-black"
                    aria-label={`Ver detalhes de ${curso.titulo}`}
                >
                    Ver detalhes
                </Link>
            </div>
        </article>
    );
}

/**
 * @param cursos Os cursos do filtro atual.
 * @param filtros Ex.: { area: 3, modalidade: 'presencial', acesso: null, busca: 'tiro' }
 * @param areas As áreas que têm curso publicado.
 * @param trilhas As trilhas ativas.
 * @returns A tela do catálogo.
 */
export default function Catalogo({
    cursos,
    filtros,
    areas,
}: {
    cursos: CartaoCurso[];
    filtros: Filtros;
    areas: { id: number; nome: string }[];
    trilhas: { id: number; nome: string }[];
}) {
    /**
     * Recarrega com um filtro trocado (só manda os que têm valor).
     *
     * @param novo Ex.: { modalidade: 'online' }
     */
    const filtrar = (novo: Partial<Filtros>) => {
        const tudo = { ...filtros, ...novo };
        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(tudo).filter(
                    ([, valor]) => valor !== null && valor !== '',
                ),
            ),
            { preserveScroll: true },
        );
    };

    // Classe comum das caixas de filtro (44 px de altura).
    const caixa =
        'h-11 w-full rounded-lg border border-[#b9ae8c] bg-white px-3 text-sm';

    return (
        <>
            <Head title="Cursos do IBTO" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="font-[Audiowide,sans-serif] text-3xl font-normal">
                        Cursos do IBTO
                    </h1>
                    <p className="text-sm text-[#4a4536]">
                        Treinamentos presenciais e online. Mais que treinamento:
                        preparação para a vida real.
                    </p>
                </div>

                {/* Filtros: 2 colunas no celular, 4 na tela larga. */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className="col-span-2 grid gap-1 lg:col-span-1">
                        <label htmlFor="busca" className="text-sm font-medium">
                            Buscar
                        </label>
                        <input
                            id="busca"
                            type="search"
                            defaultValue={filtros.busca}
                            placeholder="Nome do curso"
                            className={caixa}
                            onKeyDown={(evento) =>
                                evento.key === 'Enter' &&
                                filtrar({ busca: evento.currentTarget.value })
                            }
                            onBlur={(evento) =>
                                evento.target.value !== filtros.busca &&
                                filtrar({ busca: evento.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1">
                        <label htmlFor="area" className="text-sm font-medium">
                            Área
                        </label>
                        <select
                            id="area"
                            value={filtros.area ?? ''}
                            onChange={(evento) =>
                                filtrar({
                                    area: evento.target.value
                                        ? Number(evento.target.value)
                                        : null,
                                })
                            }
                            className={caixa}
                        >
                            <option value="">Todas</option>
                            {areas.map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.nome}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <label
                            htmlFor="modalidade"
                            className="text-sm font-medium"
                        >
                            Modalidade
                        </label>
                        <select
                            id="modalidade"
                            value={filtros.modalidade ?? ''}
                            onChange={(evento) =>
                                filtrar({
                                    modalidade: evento.target.value || null,
                                })
                            }
                            className={caixa}
                        >
                            <option value="">Todas</option>
                            <option value="presencial">Presencial</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div className="col-span-2 grid gap-1 lg:col-span-1">
                        <label htmlFor="acesso" className="text-sm font-medium">
                            Quem pode fazer
                        </label>
                        <select
                            id="acesso"
                            value={filtros.acesso ?? ''}
                            onChange={(evento) =>
                                filtrar({ acesso: evento.target.value || null })
                            }
                            className={caixa}
                        >
                            <option value="">Todos os cursos</option>
                            <option value="aberto">Aberto ao público</option>
                            <option value="restrito">
                                Exclusivo para filiados
                            </option>
                        </select>
                    </div>
                </div>

                <p className="text-sm text-[#4a4536]" role="status">
                    {cursos.length} curso(s) encontrado(s).
                </p>

                {cursos.length === 0 ? (
                    <p className="rounded-2xl border border-dashed border-[#b9ae8c] px-4 py-10 text-center text-sm text-[#4a4536]">
                        Nenhum curso neste filtro. Tente outra área ou
                        modalidade.
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {cursos.map((curso) => (
                            <Cartao key={curso.id} curso={curso} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
