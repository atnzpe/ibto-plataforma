/**
 * Tela "REDE NACIONAL" do Comando Geral (#11): a "lista de chamada" das fotos
 * que o Mestre Eduardo pediu para a página Rede Nacional do site.
 *
 * Mostra TODOS os monitores e instrutores e, para cada um, o que já cumpre
 * (ativo, anuidade, autorização de imagem, foto e UF) e se já aparece no site.
 * O nome leva à ficha, onde se envia a foto. Filtros: estado e "só pendentes".
 * Abaixo de 1024 px, a tabela vira cartões (regra tabela-cartoes).
 */
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { redeNacional } from '@/routes/cadastros';
import { edit } from '@/routes/cadastros/filiados';

/** Uma linha do quadro, como o servidor manda. */
type Linha = {
    id: number;
    // Nome completo (tela interna). Ex.: 'Ana Maria Souza'.
    nome: string;
    nivel: string | null;
    equipe: string | null;
    uf: string | null;
    // Em qual aba do site entra: 'Instrutores' ou 'Monitores'.
    lista: string;
    ativo: boolean;
    anuidade_em_dia: boolean;
    autorizou: boolean;
    tem_foto: boolean;
    uf_valida: boolean;
    // true = já aparece na página do site.
    aparece: boolean;
};

// Visual comum das caixas de escolha (select).
const classeSelect =
    'h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * Um ✅ ou ❌ com o texto escondido para o leitor de tela.
 *
 * @param ok true = cumpre; false = falta.
 * @param texto O que é conferido. Ex.: 'Foto enviada'.
 * @returns O símbolo com o texto acessível.
 */
function Marca({ ok, texto }: { ok: boolean; texto: string }) {
    return (
        <span>
            <span aria-hidden="true">{ok ? '✅' : '❌'}</span>
            <span className="sr-only">
                {texto}: {ok ? 'sim' : 'não'}
            </span>
        </span>
    );
}

/**
 * @param linhas Os monitores e instrutores do filtro.
 * @param resumo Quantos são e quantos já aparecem. Ex.: { total: 40, aparecem: 12 }
 * @param filtros O filtro atual. Ex.: { uf: 'PE', pendentes: true }
 * @param estados As 27 siglas e nomes. Ex.: { PE: 'Pernambuco' }
 * @param pagina_publica O endereço da página Rede Nacional do site.
 * @returns A tela.
 */
export default function RedeNacionalQuadro({
    linhas,
    resumo,
    filtros,
    estados,
    pagina_publica,
}: {
    linhas: Linha[];
    resumo: { total: number; aparecem: number };
    filtros: { uf: string | null; pendentes: boolean };
    estados: Record<string, string>;
    pagina_publica: string;
}) {
    /**
     * Aplica um filtro novo (troca o endereço, mantendo a posição da tela).
     *
     * @param novo O filtro novo. Ex.: { uf: 'PE', pendentes: false }
     */
    function filtrar(novo: { uf: string | null; pendentes: boolean }) {
        router.get(
            redeNacional.url(),
            {
                // Vazio não vai no endereço (fica mais limpo).
                ...(novo.uf ? { uf: novo.uf } : {}),
                ...(novo.pendentes ? { pendentes: 1 } : {}),
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <>
            <Head title="Rede Nacional" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Rede Nacional
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Monitores e instrutores na página Rede Nacional do
                            site. Para aparecer: ativo, anuidade em dia, UF na
                            ficha, autorização de imagem e a foto da Rede
                            Nacional. Clique no nome para abrir a ficha e enviar
                            a foto.
                        </p>
                    </div>
                    {/* Conferir a página pública (abre em outra aba: é o site). */}
                    <Button asChild variant="outline" className="min-h-11">
                        <a href={pagina_publica} target="_blank" rel="noopener">
                            Ver a página no site
                        </a>
                    </Button>
                </div>

                {/* Resumo: anunciado pelo leitor de tela quando o filtro muda. */}
                <p role="status" className="text-sm font-medium">
                    {resumo.aparecem} de {resumo.total} já aparecem no site
                    {filtros.uf ? ` (${estados[filtros.uf]})` : ''}.
                </p>

                {/* Filtros: 2 colunas no celular, em linha no computador. */}
                <div className="grid grid-cols-2 items-end gap-4 lg:flex">
                    <div className="grid gap-2 lg:w-64">
                        <Label htmlFor="filtro-uf">Estado</Label>
                        <select
                            id="filtro-uf"
                            value={filtros.uf ?? ''}
                            onChange={(evento) =>
                                filtrar({
                                    uf: evento.target.value || null,
                                    pendentes: filtros.pendentes,
                                })
                            }
                            className={classeSelect}
                        >
                            <option value="">Todos os estados</option>
                            {Object.entries(estados).map(([uf, nome]) => (
                                <option key={uf} value={uf}>
                                    {nome} ({uf})
                                </option>
                            ))}
                        </select>
                    </div>
                    <label className="flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-5"
                            checked={filtros.pendentes}
                            onChange={(evento) =>
                                filtrar({
                                    uf: filtros.uf,
                                    pendentes: evento.target.checked,
                                })
                            }
                        />
                        Só quem ainda não aparece
                    </label>
                </div>

                {linhas.length === 0 ? (
                    // Nada no filtro: explica em vez de mostrar uma tabela vazia.
                    <p className="text-sm text-muted-foreground">
                        {filtros.pendentes
                            ? 'Ninguém pendente neste filtro: todos já aparecem no site.'
                            : 'Nenhum monitor ou instrutor neste filtro.'}
                    </p>
                ) : (
                    <div className="caixa-tabela-cartoes overflow-x-auto rounded-md border border-border">
                        <table className="tabela-cartoes w-full text-left text-sm">
                            <caption className="sr-only">
                                Monitores e instrutores e o que falta para
                                aparecer na Rede Nacional
                            </caption>
                            <thead className="bg-muted/50">
                                <tr>
                                    {[
                                        'Nome',
                                        'Nível',
                                        'Equipe',
                                        'UF',
                                        'Ativo',
                                        'Anuidade',
                                        'Autorização',
                                        'Foto',
                                        'No site',
                                    ].map((coluna) => (
                                        <th
                                            key={coluna}
                                            scope="col"
                                            className="px-4 py-2 font-medium"
                                        >
                                            {coluna}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {linhas.map((linha) => (
                                    <tr
                                        key={linha.id}
                                        className="border-t border-border"
                                    >
                                        {/* O nome é o título do cartão no celular e leva à ficha. */}
                                        <th
                                            scope="row"
                                            className="px-4 py-2 font-medium"
                                        >
                                            <Link
                                                href={edit(linha.id)}
                                                className="underline underline-offset-4"
                                            >
                                                {linha.nome}
                                            </Link>
                                        </th>
                                        <td
                                            data-rotulo="Nível"
                                            className="px-4 py-2"
                                        >
                                            {linha.nivel ?? '—'}{' '}
                                            <span className="text-muted-foreground">
                                                ({linha.lista})
                                            </span>
                                        </td>
                                        <td
                                            data-rotulo="Equipe"
                                            className="px-4 py-2"
                                        >
                                            {linha.equipe ?? '—'}
                                        </td>
                                        <td
                                            data-rotulo="UF"
                                            className="px-4 py-2"
                                        >
                                            {/* UF vazia ou inválida: ❌ (precisa corrigir na ficha). */}
                                            {linha.uf_valida ? (
                                                linha.uf
                                            ) : (
                                                <Marca
                                                    ok={false}
                                                    texto="UF válida"
                                                />
                                            )}
                                        </td>
                                        <td
                                            data-rotulo="Ativo"
                                            className="px-4 py-2"
                                        >
                                            <Marca
                                                ok={linha.ativo}
                                                texto="Ativo"
                                            />
                                        </td>
                                        <td
                                            data-rotulo="Anuidade"
                                            className="px-4 py-2"
                                        >
                                            <Marca
                                                ok={linha.anuidade_em_dia}
                                                texto="Anuidade em dia"
                                            />
                                        </td>
                                        <td
                                            data-rotulo="Autorização"
                                            className="px-4 py-2"
                                        >
                                            <Marca
                                                ok={linha.autorizou}
                                                texto="Autorização de imagem"
                                            />
                                        </td>
                                        <td
                                            data-rotulo="Foto"
                                            className="px-4 py-2"
                                        >
                                            <Marca
                                                ok={linha.tem_foto}
                                                texto="Foto da Rede Nacional"
                                            />
                                        </td>
                                        <td
                                            data-rotulo="No site"
                                            className="px-4 py-2 font-medium"
                                        >
                                            {linha.aparece
                                                ? 'Aparece'
                                                : 'Ainda não'}
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

// Caminho mostrado no topo da tela.
RedeNacionalQuadro.layout = {
    breadcrumbs: [{ title: 'Rede Nacional', href: redeNacional() }],
};
