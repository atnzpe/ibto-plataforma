/**
 * Importação de filiados, TELA 3 de 3 (#37, F4b): prévia e confirmação.
 *
 * Mostra quantas linhas entram e quais são recusadas, com o motivo. LGPD: só o
 * NÚMERO da linha (como no Excel) e o motivo, sem nome, CPF ou e-mail.
 * Confirmar cria as contas e manda os convites por e-mail.
 */
import { Form, Head } from '@inertiajs/react';
import ImportacaoFiliadosController from '@/actions/App/Http/Controllers/Cadastros/ImportacaoFiliadosController';
import { Button } from '@/components/ui/button';
import { colunas, create } from '@/routes/cadastros/filiados/importar';

/**
 * @param codigo O código desta importação.
 * @param validas Quantas linhas estão certas e vão entrar.
 * @param recusadas As linhas recusadas: número da linha e motivos.
 * @returns A tela da prévia.
 */
export default function ImportarPrevia({
    codigo,
    validas,
    recusadas,
}: {
    codigo: string;
    validas: number;
    recusadas: { linha: number; motivos: string[] }[];
}) {
    return (
        <>
            <Head title="Prévia da importação" />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Prévia da importação
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Passo 3 de 3: confira antes de confirmar.
                    </p>
                </div>

                <ul className="space-y-1 text-sm">
                    <li>
                        <strong>{validas}</strong> linha(s) certa(s): vão ser
                        cadastradas, e cada pessoa recebe o convite por e-mail.
                    </li>
                    <li>
                        <strong>{recusadas.length}</strong> linha(s)
                        recusada(s): corrija na planilha e importe de novo
                        depois.
                    </li>
                </ul>

                {recusadas.length > 0 && (
                    <div className="overflow-x-auto rounded-md border border-border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Linhas recusadas
                            </caption>
                            <thead className="bg-muted/50">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Linha
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 font-medium"
                                    >
                                        Motivo
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {recusadas.map((recusada) => (
                                    <tr
                                        key={recusada.linha}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-4 py-2 font-mono">
                                            {recusada.linha}
                                        </td>
                                        <td className="px-4 py-2">
                                            <ul className="list-disc pl-4">
                                                {recusada.motivos.map(
                                                    (motivo) => (
                                                        <li key={motivo}>
                                                            {motivo}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Form {...ImportacaoFiliadosController.confirmar.form(codigo)}>
                    {({ processing }) => (
                        <div className="flex flex-wrap items-center gap-4">
                            {/* Sem linha certa, não há o que confirmar. */}
                            <Button disabled={processing || validas === 0}>
                                Confirmar e cadastrar {validas} filiado(s)
                            </Button>
                            <a
                                href={colunas(codigo).url}
                                className="text-sm underline underline-offset-4"
                            >
                                Voltar e mudar as colunas
                            </a>
                            <a
                                href={create().url}
                                className="text-sm underline underline-offset-4"
                            >
                                Enviar outro arquivo
                            </a>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho no topo: volta para o passo 1.
ImportarPrevia.layout = {
    breadcrumbs: [{ title: 'Importar filiados', href: create() }],
};
