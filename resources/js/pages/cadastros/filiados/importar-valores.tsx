/**
 * Importação de filiados, TELA 3 de 4 (#37, etapa B; pedido do Gleyson em
 * 08/10/2026): relacionar os VALORES que não batem com o cadastro.
 *
 * Cada valor diferente aparece UMA vez (ex.: "Expert Nível 3"), com a sugestão
 * já marcada quando a plataforma achou um parecido (80% ou mais). A pessoa
 * escolhe: um item do cadastro, "Abrangência: Nacional" (no nível), "sem
 * nível/equipe", "ignorar só esta área" ou "recusar a linha".
 */
import { Form, Head } from '@inertiajs/react';
import ImportacaoFiliadosController from '@/actions/App/Http/Controllers/Cadastros/ImportacaoFiliadosController';
import { Button } from '@/components/ui/button';
import { colunas, create } from '@/routes/cadastros/filiados/importar';

/** Um valor da planilha que precisa ser relacionado. */
type ValorParaRelacionar = {
    campo: string;
    valor: string;
    sugestao: string | null;
};

/** Uma opção da caixa de escolha. */
type Opcao = { valor: string; rotulo: string };

// Visual das caixas de escolha (igual aos campos de texto).
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param codigo O código desta importação.
 * @param valores Os valores fora do padrão, cada um uma vez, com a sugestão.
 * @param opcoes As opções de cada campo. Ex.: { nivel: [{ valor: 'nivel:12', rotulo: 'Instrutor Expert III' }] }
 * @param campos Os nomes dos campos para a tela. Ex.: { nivel: 'Nível (...)' }
 * @returns A tela de relacionar os valores.
 */
export default function ImportarValores({
    codigo,
    valores,
    opcoes,
    campos,
}: {
    codigo: string;
    valores: ValorParaRelacionar[];
    opcoes: Record<string, Opcao[]>;
    campos: Record<string, string>;
}) {
    // Quantos já vieram com sugestão (para o resumo do topo).
    const comSugestao = valores.filter((v) => v.sugestao !== null).length;

    return (
        <>
            <Head title="Relacionar valores" />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Relacionar os valores
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Passo 3 de 4: {valores.length} valor(es) da planilha não
                        batem com o cadastro. {comSugestao} já vêm com sugestão;
                        confira e escolha os outros. Cada escolha vale para
                        todas as linhas com aquele valor.
                    </p>
                </div>

                <Form
                    {...ImportacaoFiliadosController.salvarValores.form(codigo)}
                    className="space-y-6"
                >
                    {({ processing }) => (
                        <>
                            {/* Tabela na tela larga; cartões empilhados abaixo de 1024 px (classe tabela-cartoes, app.css). */}
                            <div className="caixa-tabela-cartoes overflow-x-auto rounded-md border border-border">
                                <table className="tabela-cartoes w-full text-left text-sm">
                                    <caption className="sr-only">
                                        Valores da planilha e o item do cadastro
                                    </caption>
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 font-medium"
                                            >
                                                Campo
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 font-medium"
                                            >
                                                Na planilha
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 font-medium"
                                            >
                                                Vai virar
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {valores.map((valor, posicao) => (
                                            <tr
                                                key={`${valor.campo}-${posicao}`}
                                                className="border-t border-border"
                                            >
                                                <td
                                                    data-rotulo="Campo"
                                                    className="px-4 py-2 text-muted-foreground"
                                                >
                                                    {/* Só a primeira palavra do nome do campo (ex.: "Nível"). */}
                                                    {
                                                        campos[
                                                            valor.campo
                                                        ]?.split(' (')[0]
                                                    }
                                                </td>
                                                <th
                                                    scope="row"
                                                    className="px-4 py-2 font-normal"
                                                >
                                                    <label
                                                        htmlFor={`escolha-${posicao}`}
                                                    >
                                                        {valor.valor}
                                                    </label>
                                                </th>
                                                <td
                                                    data-rotulo="Vai virar"
                                                    className="px-4 py-2"
                                                >
                                                    {/* name="escolhas[posição]": o servidor junta pela ordem da lista. */}
                                                    <select
                                                        id={`escolha-${posicao}`}
                                                        name={`escolhas[${posicao}]`}
                                                        defaultValue={
                                                            valor.sugestao ?? ''
                                                        }
                                                        required
                                                        className={classeSelect}
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            Escolha…
                                                        </option>
                                                        {(
                                                            opcoes[
                                                                valor.campo
                                                            ] ?? []
                                                        ).map((opcao) => (
                                                            <option
                                                                key={
                                                                    opcao.valor
                                                                }
                                                                value={
                                                                    opcao.valor
                                                                }
                                                            >
                                                                {opcao.rotulo}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    {valor.sugestao !==
                                                        null && (
                                                        <span className="mt-1 block text-xs text-muted-foreground">
                                                            Sugestão da
                                                            plataforma
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex flex-wrap items-center gap-4">
                                <Button disabled={processing}>
                                    Ver a prévia
                                </Button>
                                <a
                                    href={colunas(codigo).url}
                                    className="text-sm underline underline-offset-4"
                                >
                                    Voltar às colunas
                                </a>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho no topo: volta para o passo 1.
ImportarValores.layout = {
    breadcrumbs: [{ title: 'Importar filiados', href: create() }],
};
