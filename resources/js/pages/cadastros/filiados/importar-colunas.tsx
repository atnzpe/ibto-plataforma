/**
 * Importação de filiados, TELA 2 de 3 (#37, F4b): relacionar as colunas.
 *
 * Para cada coluna da planilha, uma caixa para escolher o campo do banco. A
 * sugestão (pelo nome da coluna) já vem marcada; "Ignorar" deixa a coluna de fora.
 * O mesmo campo não pode ir para duas colunas (a tela avisa, e o servidor confere).
 */
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ImportacaoFiliadosController from '@/actions/App/Http/Controllers/Cadastros/ImportacaoFiliadosController';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/cadastros/filiados/importar';

// Visual das caixas de escolha (igual aos campos de texto).
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param codigo O código desta importação.
 * @param colunas Os nomes das colunas da planilha, na ordem.
 * @param total_linhas Quantas linhas (filiados) a planilha tem.
 * @param sugestao A sugestão de campo para cada coluna. Ex.: { CPF: 'cpf' }
 * @param campos Os campos possíveis. Ex.: { cpf: 'CPF', ignorar: '— Ignorar —' }
 * @returns A tela de relacionar as colunas.
 */
export default function ImportarColunas({
    codigo,
    colunas,
    total_linhas,
    sugestao,
    campos,
}: {
    codigo: string;
    colunas: string[];
    total_linhas: number;
    sugestao: Record<string, string>;
    campos: Record<string, string>;
}) {
    // Escolha atual de cada coluna, pela posição (começa com a sugestão).
    const [mapa, setMapa] = useState<string[]>(
        colunas.map((coluna) => sugestao[coluna] ?? 'ignorar'),
    );

    // Campos escolhidos em mais de uma coluna (fora o "ignorar"): a tela avisa.
    const repetidos = mapa.filter(
        (campo, posicao) =>
            campo !== 'ignorar' && mapa.indexOf(campo) !== posicao,
    );

    return (
        <>
            <Head title="Relacionar colunas" />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Relacionar as colunas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Passo 2 de 4: a planilha tem {total_linhas} linha(s).
                        Confira o campo de cada coluna; a sugestão já vem
                        marcada.
                    </p>
                </div>

                <Form
                    {...ImportacaoFiliadosController.salvarColunas.form(codigo)}
                    className="space-y-6"
                >
                    {({ processing }) => (
                        <>
                            <div className="overflow-x-auto rounded-md border border-border">
                                <table className="w-full text-left text-sm">
                                    <caption className="sr-only">
                                        Colunas da planilha e campos do banco
                                    </caption>
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 font-medium"
                                            >
                                                Coluna da planilha
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 font-medium"
                                            >
                                                Vai para o campo
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {colunas.map((coluna, posicao) => (
                                            <tr
                                                key={posicao}
                                                className="border-t border-border"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-4 py-2 font-normal"
                                                >
                                                    <label
                                                        htmlFor={`mapa-${posicao}`}
                                                    >
                                                        {coluna ||
                                                            `(coluna ${posicao + 1} sem nome)`}
                                                    </label>
                                                </th>
                                                <td className="px-4 py-2">
                                                    {/* name="mapa[posição]": o servidor recebe a escolha pela posição da coluna. */}
                                                    <select
                                                        id={`mapa-${posicao}`}
                                                        name={`mapa[${posicao}]`}
                                                        value={mapa[posicao]}
                                                        onChange={(evento) =>
                                                            setMapa(
                                                                mapa.map(
                                                                    (
                                                                        campo,
                                                                        p,
                                                                    ) =>
                                                                        p ===
                                                                        posicao
                                                                            ? evento
                                                                                  .target
                                                                                  .value
                                                                            : campo,
                                                                ),
                                                            )
                                                        }
                                                        className={classeSelect}
                                                    >
                                                        {Object.entries(
                                                            campos,
                                                        ).map(
                                                            ([
                                                                valor,
                                                                rotulo,
                                                            ]) => (
                                                                <option
                                                                    key={valor}
                                                                    value={
                                                                        valor
                                                                    }
                                                                >
                                                                    {rotulo}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Aviso de campo repetido (role="alert": o leitor de tela anuncia na hora). */}
                            {repetidos.length > 0 && (
                                <p
                                    role="alert"
                                    className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                                >
                                    Um campo foi escolhido para mais de uma
                                    coluna:{' '}
                                    {[...new Set(repetidos)]
                                        .map((campo) => campos[campo])
                                        .join(', ')}
                                    . Deixe cada campo em uma coluna só.
                                </p>
                            )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={
                                        processing || repetidos.length > 0
                                    }
                                >
                                    Continuar
                                </Button>
                                <a
                                    href={create().url}
                                    className="text-sm underline underline-offset-4"
                                >
                                    Enviar outro arquivo
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
ImportarColunas.layout = {
    breadcrumbs: [{ title: 'Importar filiados', href: create() }],
};
