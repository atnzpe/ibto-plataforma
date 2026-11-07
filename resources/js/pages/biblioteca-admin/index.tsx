/**
 * BIBLIOTECA DE TÉCNICAS no Comando Geral (#107): a lista de todos os materiais
 * (ativos e retirados), com a busca pelo nome e o botão "Novo material".
 * Abaixo de 1024 px a tabela vira cartões (tabela-cartoes): nenhuma tela rola de lado.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, edit, index } from '@/routes/comando-geral/biblioteca';

/** Um material como a lista mostra. */
type MaterialLista = {
    id: number;
    titulo: string;
    // Nome da área; null = vale para todas as áreas.
    area: string | null;
    // Nome do nível mínimo; null = todos que abrem a biblioteca.
    nivel: string | null;
    tem_video: boolean;
    tem_apostila: boolean;
    // false = retirado (não aparece para quem estuda).
    ativo: boolean;
    // Ex.: '07/11/2026'.
    atualizado_em: string | null;
};

/**
 * A tela da lista.
 *
 * @param filtros A busca atual (vazia = sem busca). Ex.: { busca: 'faca' }
 * @param materiais Os materiais, os mais novos primeiro.
 * @returns A tela.
 */
export default function BibliotecaAdmin({
    filtros,
    materiais,
}: {
    filtros: { busca: string };
    materiais: MaterialLista[];
}) {
    // Mensagem da última ação (ex.: "Material salvo na biblioteca").
    const { sucesso } = usePage().props;

    /**
     * Refaz a lista com a busca nova (busca vazia sai do endereço).
     *
     * @param busca O texto digitado. Ex.: 'faca'.
     */
    const buscar = (busca: string) => {
        router.get(index().url, busca !== '' ? { busca } : {}, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Biblioteca (materiais)" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Biblioteca de técnicas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Vídeos e apostilas para monitores e instrutores
                            revisarem antes de ensinar. Retirar tira da
                            biblioteca sem apagar.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={create()}>Novo material</Link>
                    </Button>
                </div>

                {/* Aviso verde depois de salvar. */}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* Busca pelo nome da técnica. */}
                <div className="grid w-full gap-1 sm:w-72">
                    <Label htmlFor="busca">Buscar pelo nome</Label>
                    <Input
                        id="busca"
                        type="search"
                        defaultValue={filtros.busca}
                        // Busca ao apertar Enter ou sair do campo (não a cada letra).
                        onKeyDown={(evento) =>
                            evento.key === 'Enter' &&
                            buscar(evento.currentTarget.value)
                        }
                        onBlur={(evento) =>
                            evento.target.value !== filtros.busca &&
                            buscar(evento.target.value)
                        }
                    />
                </div>

                {/* Lista vazia: frase em vez de uma tabela vazia. */}
                {materiais.length === 0 ? (
                    <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Nenhum material ainda. Clique em "Novo material" para
                        enviar o primeiro.
                    </p>
                ) : (
                    <div className="caixa-tabela-cartoes overflow-x-auto rounded-md border border-border">
                        <table className="tabela-cartoes w-full text-left text-sm">
                            <caption className="sr-only">
                                Materiais da biblioteca
                            </caption>
                            <thead className="bg-muted/50">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Técnica
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Área
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Nível mínimo
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Tem
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
                                {materiais.map((material) => (
                                    <tr
                                        key={material.id}
                                        className="border-t border-border"
                                    >
                                        <td
                                            data-rotulo="Técnica"
                                            className="px-4 py-2 font-medium"
                                        >
                                            {material.titulo}
                                        </td>
                                        <td
                                            data-rotulo="Área"
                                            className="px-4 py-2"
                                        >
                                            {/* Sem área: vale para todas. */}
                                            {material.area ?? 'Todas'}
                                        </td>
                                        <td
                                            data-rotulo="Nível mínimo"
                                            className="px-4 py-2"
                                        >
                                            {/* Sem nível: todos que abrem a biblioteca veem. */}
                                            {material.nivel ?? 'Todos'}
                                        </td>
                                        <td
                                            data-rotulo="Tem"
                                            className="px-4 py-2"
                                        >
                                            {/* O que o material tem, escrito (sem depender de ícone). */}
                                            {[
                                                material.tem_video
                                                    ? 'Vídeo'
                                                    : null,
                                                material.tem_apostila
                                                    ? 'Apostila'
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' e ') || 'Só o texto'}
                                        </td>
                                        <td
                                            data-rotulo="Situação"
                                            className={`px-4 py-2 ${material.ativo ? 'text-green-700 dark:text-green-300' : 'text-muted-foreground line-through'}`}
                                        >
                                            {material.ativo
                                                ? 'Na biblioteca'
                                                : 'Retirado'}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="min-h-11"
                                            >
                                                <Link href={edit(material.id)}>
                                                    Editar
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
BibliotecaAdmin.layout = {
    breadcrumbs: [{ title: 'Biblioteca (materiais)', href: index() }],
};
