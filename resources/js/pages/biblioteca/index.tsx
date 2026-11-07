/**
 * BIBLIOTECA DE TÉCNICAS (#107): monitores e instrutores revisam as técnicas
 * (vídeo e apostila) antes de passar ao aluno. A lista já vem do servidor só com
 * o que a pessoa pode ver (ativo e liberado para o nível dela). Filtros: área,
 * nível e busca pelo nome. Cada cartão abre a ficha com o vídeo e a apostila.
 */
import { Head, Link, router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/biblioteca';

/** Uma opção de filtro (área ou nível). Ex.: { id: 2, nome: 'Krav Maga' } */
type Opcao = { id: number; nome: string };

/** Um material como o cartão mostra. */
type MaterialCartao = {
    id: number;
    titulo: string;
    // O começo da explicação (até 160 letras); null = sem explicação.
    resumo: string | null;
    // null = vale para todas as áreas.
    area: string | null;
    // null = todos os níveis.
    nivel: string | null;
    tem_video: boolean;
    tem_apostila: boolean;
};

/** Os filtros atuais (null ou vazio = sem filtro). */
type Filtros = { busca: string; area: number | null; nivel: number | null };

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm';

/**
 * A tela da biblioteca.
 *
 * @param filtros Os filtros escolhidos. Ex.: { busca: 'faca', area: 2, nivel: null }
 * @param materiais Os materiais que a pessoa pode ver, em ordem de nome.
 * @param areas As áreas para o filtro.
 * @param niveis Os níveis para o filtro, do mais baixo ao mais alto.
 * @param meu_nivel O nível da pessoa (ex.: 'Monitor I'); null = sem nível de filiado.
 * @returns A tela.
 */
export default function Biblioteca({
    filtros,
    materiais,
    areas,
    niveis,
    meu_nivel,
}: {
    filtros: Filtros;
    materiais: MaterialCartao[];
    areas: Opcao[];
    niveis: Opcao[];
    meu_nivel: string | null;
}) {
    /**
     * Refaz a lista com um filtro trocado (os vazios saem do endereço).
     *
     * @param novo O filtro que mudou. Ex.: { area: 2 }
     */
    const filtrar = (novo: Partial<Filtros>) => {
        const tudo = { ...filtros, ...novo };
        router.get(
            index().url,
            Object.fromEntries(
                // Tira do endereço o que está vazio (ex.: ?busca=&area=).
                Object.entries(tudo).filter(
                    ([, valor]) => valor !== '' && valor !== null,
                ),
            ),
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Biblioteca de técnicas" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Biblioteca de técnicas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Vídeos e apostilas para revisar antes de ensinar.
                        {/* Com nível: explica por que algumas técnicas não aparecem. */}
                        {meu_nivel
                            ? ` Você vê as técnicas até o seu nível (${meu_nivel}).`
                            : ' Você vê as técnicas liberadas para todos os níveis.'}
                    </p>
                </div>

                {/* Filtros: busca, área e nível. */}
                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid w-full gap-1 sm:w-64">
                        <Label htmlFor="busca">Buscar técnica</Label>
                        <Input
                            id="busca"
                            type="search"
                            defaultValue={filtros.busca}
                            placeholder="Ex.: faca"
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
                        <Label htmlFor="area">Área</Label>
                        <select
                            id="area"
                            value={filtros.area ?? ''}
                            onChange={(evento) =>
                                filtrar({
                                    // "Todas" = sem filtro (null).
                                    area: evento.target.value
                                        ? Number(evento.target.value)
                                        : null,
                                })
                            }
                            className={classeSelect}
                        >
                            <option value="">Todas</option>
                            {areas.map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.nome}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid w-full gap-1 sm:w-48">
                        <Label htmlFor="nivel">Nível</Label>
                        <select
                            id="nivel"
                            value={filtros.nivel ?? ''}
                            onChange={(evento) =>
                                filtrar({
                                    // "Todos" = sem filtro (null).
                                    nivel: evento.target.value
                                        ? Number(evento.target.value)
                                        : null,
                                })
                            }
                            className={classeSelect}
                        >
                            <option value="">Todos</option>
                            {niveis.map((nivel) => (
                                <option key={nivel.id} value={nivel.id}>
                                    {nivel.nome}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Nada com estes filtros: frase em vez de uma grade vazia. */}
                {materiais.length === 0 ? (
                    <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Nenhuma técnica com estes filtros por enquanto.
                    </p>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {materiais.map((material) => (
                            <li key={material.id}>
                                {/* O cartão inteiro é o link (alvo grande, fácil no celular). */}
                                <Link
                                    href={show(material.id)}
                                    className="flex h-full flex-col gap-2 rounded-lg border border-border p-4 transition-colors hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    <h2 className="font-medium">
                                        {material.titulo}
                                    </h2>
                                    {material.resumo && (
                                        <p className="text-sm text-muted-foreground">
                                            {material.resumo}
                                        </p>
                                    )}
                                    {/* Etiquetas escritas: área, nível e o que o material tem. */}
                                    <p className="mt-auto flex flex-wrap gap-2 pt-2 text-xs">
                                        <span className="rounded bg-muted px-2 py-1">
                                            {material.area ?? 'Todas as áreas'}
                                        </span>
                                        <span className="rounded bg-muted px-2 py-1">
                                            {material.nivel ??
                                                'Todos os níveis'}
                                        </span>
                                        {material.tem_video && (
                                            <span className="rounded bg-muted px-2 py-1">
                                                Vídeo
                                            </span>
                                        )}
                                        {material.tem_apostila && (
                                            <span className="rounded bg-muted px-2 py-1">
                                                Apostila
                                            </span>
                                        )}
                                    </p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
Biblioteca.layout = {
    breadcrumbs: [{ title: 'Biblioteca de técnicas', href: index() }],
};
