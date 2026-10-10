/**
 * CURSOS no Comando Geral (#39 / #38): lista com busca pelo nome, situação e
 * período (pela data de início). Não existe apagar: o curso é encerrado.
 * Responsiva: tabela na tela larga, cartões no celular (sem rolagem lateral).
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
// Filtro "De / Até" (o mesmo em todas as telas com data).
import FiltroPeriodo from '@/components/filtro-periodo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatarReais } from '@/lib/dinheiro';
import { create, edit, index } from '@/routes/comando-geral/cursos';
import { index as trilhas } from '@/routes/comando-geral/trilhas';

/** Um curso como a lista mostra. */
type CursoLista = {
    id: number;
    titulo: string;
    area: string | null;
    modalidade: string;
    tipo: string;
    valor_centavos: number;
    data_inicio: string | null;
    vagas: number | null;
    situacao: 'rascunho' | 'publicado' | 'encerrado';
    situacao_rotulo: string;
};

type Filtros = { busca: string; situacao: string; de: string; ate: string };

// Situação → classe de cor (o texto escrito é que informa; a cor só reforça).
const CORES: Record<CursoLista['situacao'], string> = {
    rascunho: 'text-muted-foreground',
    publicado: 'text-green-700 dark:text-green-400',
    encerrado: 'text-muted-foreground line-through',
};

/**
 * '2026-10-15' → '15/10/2026'; vazio → 'Sem data'.
 */
function dataBr(data: string | null): string {
    if (!data) {
        return 'Sem data';
    }
    const [ano, mes, dia] = data.split('-');

    return `${dia}/${mes}/${ano}`;
}

/**
 * @param cursos Os cursos do filtro atual.
 * @param filtros Ex.: { busca: 'combate', situacao: 'publicado', de: '', ate: '' }
 * @param situacoes As opções de situação. Ex.: [{ valor: 'publicado', rotulo: 'Publicado' }]
 * @returns A tela da lista de cursos.
 */
export default function CursosIndex({
    cursos,
    filtros,
    situacoes,
}: {
    cursos: CursoLista[];
    filtros: Filtros;
    situacoes: { valor: string; rotulo: string }[];
}) {
    // Mensagem da última ação (ex.: 'Curso "X" cadastrado.'), ou null.
    const { sucesso } = usePage().props;

    /**
     * Recarrega com um filtro trocado, mantendo os outros (só os que têm valor).
     *
     * @param novo Ex.: { situacao: 'publicado' }
     */
    const filtrar = (novo: Partial<Filtros>) => {
        const tudo = { ...filtros, ...novo };
        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(tudo).filter(([, valor]) => valor !== ''),
            ),
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Cursos" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Cursos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Presenciais e online. Só os publicados aparecem para
                            os alunos; encerrar tira do catálogo sem apagar.
                        </p>
                    </div>
                    {/* No celular, 2 colunas iguais; na tela larga, lado a lado. */}
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <Button asChild variant="outline">
                            <Link href={trilhas()}>Trilhas</Link>
                        </Button>
                        <Button asChild>
                            <Link href={create()}>Novo curso</Link>
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

                {/* Filtros: busca, situação e período. */}
                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid w-full gap-1 sm:w-64">
                        <Label htmlFor="busca">Buscar pelo nome</Label>
                        <Input
                            id="busca"
                            type="search"
                            defaultValue={filtros.busca}
                            // Busca ao apertar Enter ou sair do campo (não a cada letra).
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
                    <div className="grid w-full gap-1 sm:w-48">
                        <Label htmlFor="situacao">Situação</Label>
                        <select
                            id="situacao"
                            value={filtros.situacao}
                            onChange={(evento) =>
                                filtrar({ situacao: evento.target.value })
                            }
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="">Todas</option>
                            {situacoes.map((opcao) => (
                                <option key={opcao.valor} value={opcao.valor}>
                                    {opcao.rotulo}
                                </option>
                            ))}
                        </select>
                    </div>
                    <FiltroPeriodo
                        prefixo="cursos"
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(periodo) => filtrar(periodo)}
                    />
                </div>

                {/* TABELA: só em tela larga. */}
                <div className="hidden overflow-x-auto rounded-md border border-border md:block">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Lista dos cursos</caption>
                        <thead className="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Curso
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Início
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Tipo
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 text-right font-medium"
                                >
                                    Valor
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    Situação
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 font-medium"
                                >
                                    <span className="sr-only">Ações</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {cursos.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        Nenhum curso neste filtro.
                                    </td>
                                </tr>
                            )}
                            {cursos.map((curso) => (
                                <tr
                                    key={curso.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2">
                                        <span className="font-medium">
                                            {curso.titulo}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {curso.modalidade}
                                            {curso.area && ` · ${curso.area}`}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        {dataBr(curso.data_inicio)}
                                    </td>
                                    <td className="px-4 py-2">{curso.tipo}</td>
                                    <td className="px-4 py-2 text-right whitespace-nowrap">
                                        {curso.valor_centavos === 0
                                            ? 'Gratuito'
                                            : formatarReais(
                                                  curso.valor_centavos,
                                              )}
                                    </td>
                                    <td
                                        className={`px-4 py-2 ${CORES[curso.situacao]}`}
                                    >
                                        {curso.situacao_rotulo}
                                    </td>
                                    <td className="w-px px-4 py-2 text-right whitespace-nowrap">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={edit(curso.id)}
                                                aria-label={`Editar ${curso.titulo}`}
                                            >
                                                Editar
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* CARTÕES: só no celular. */}
                <ul
                    aria-label="Lista dos cursos"
                    className="flex flex-col gap-3 md:hidden"
                >
                    {cursos.length === 0 && (
                        <li className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                            Nenhum curso neste filtro.
                        </li>
                    )}
                    {cursos.map((curso) => (
                        <li
                            key={curso.id}
                            className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                        >
                            <div className="flex items-baseline justify-between gap-3">
                                <span className="font-medium">
                                    {curso.titulo}
                                </span>
                                <span className={CORES[curso.situacao]}>
                                    {curso.situacao_rotulo}
                                </span>
                            </div>
                            <span className="text-muted-foreground">
                                {curso.modalidade} · {dataBr(curso.data_inicio)}{' '}
                                ·{' '}
                                {curso.valor_centavos === 0
                                    ? 'Gratuito'
                                    : formatarReais(curso.valor_centavos)}
                            </span>
                            <span className="text-muted-foreground">
                                {curso.tipo}
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11 w-full"
                            >
                                <Link
                                    href={edit(curso.id)}
                                    aria-label={`Editar ${curso.titulo}`}
                                >
                                    Editar
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
CursosIndex.layout = {
    breadcrumbs: [{ title: 'Cursos', href: index() }],
};
