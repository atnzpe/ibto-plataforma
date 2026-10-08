/**
 * Tela de PAPÉIS × PERMISSÕES (#36), no molde do Painel da CBSA.
 *
 * Uma grade: cada linha é uma permissão, cada coluna um papel; a caixa marcada
 * quer dizer "este papel pode isto". Salvar grava tudo de uma vez e cada
 * mudança vai para a auditoria. A caixa travada (Tecnologia × "Configurar
 * papéis e permissões") não pode ser desmarcada: sem ela, ninguém mais abriria esta tela.
 */
import { Form, Head, usePage } from '@inertiajs/react';
import PermissaoController from '@/actions/App/Http/Controllers/Admin/PermissaoController';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/admin/permissoes';

/** Um papel da grade, com os números das permissões marcadas. */
type PapelGrade = {
    id: number;
    slug: string;
    nome: string;
    permissoes: number[];
};

/** Uma permissão (linha da grade). */
type PermissaoGrade = { id: number; slug: string; nome: string; grupo: string };

/**
 * @param papeis Os papéis (colunas).
 * @param permissoes As permissões (linhas), já em ordem.
 * @param travadas Caixas que não podem ser desmarcadas. Ex.: [{ papel: 'tecnologia', permissao: 'permissoes.gerenciar' }]
 * @returns A tela da grade.
 */
export default function Permissoes({
    papeis,
    permissoes,
    travadas,
}: {
    papeis: PapelGrade[];
    permissoes: PermissaoGrade[];
    travadas: { papel: string; permissao: string }[];
}) {
    // Mensagem da última gravação (ex.: "Permissões salvas (2 mudança(s)).").
    const { sucesso } = usePage().props;

    /**
     * Diz se a caixa (papel × permissão) é travada.
     *
     * @returns true se não pode ser desmarcada.
     */
    const ehTravada = (papel: PapelGrade, permissao: PermissaoGrade) =>
        travadas.some(
            (t) => t.papel === papel.slug && t.permissao === permissao.slug,
        );

    return (
        <>
            <Head title="Papéis e permissões" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Papéis e permissões
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Marque o que cada papel pode fazer. Cada mudança fica
                        registrada na auditoria (quem, o quê e quando).
                    </p>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                <Form
                    {...PermissaoController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <>
                            <div className="overflow-x-auto rounded-md border border-border">
                                <table className="w-full text-left text-sm">
                                    <caption className="sr-only">
                                        Grade de papéis e permissões
                                    </caption>
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 font-medium"
                                            >
                                                Permissão
                                            </th>
                                            {papeis.map((papel) => (
                                                <th
                                                    key={papel.id}
                                                    scope="col"
                                                    className="px-3 py-2 text-center font-medium"
                                                >
                                                    {papel.nome}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {permissoes.map((permissao) => (
                                            <tr
                                                key={permissao.id}
                                                className="border-t border-border"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-4 py-2 font-normal"
                                                >
                                                    {permissao.nome}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {permissao.grupo}
                                                    </span>
                                                </th>
                                                {papeis.map((papel) => {
                                                    const travada = ehTravada(
                                                        papel,
                                                        permissao,
                                                    );

                                                    return (
                                                        <td
                                                            key={papel.id}
                                                            className="px-3 py-2 text-center"
                                                        >
                                                            {/* name="vinculos[papel][]": o navegador envia, por papel, a lista das marcadas.
                                                                aria-label: o leitor de tela diz "Administrador: Cadastro de equipes". */}
                                                            <input
                                                                type="checkbox"
                                                                name={`vinculos[${papel.id}][]`}
                                                                value={
                                                                    permissao.id
                                                                }
                                                                defaultChecked={
                                                                    travada ||
                                                                    papel.permissoes.includes(
                                                                        permissao.id,
                                                                    )
                                                                }
                                                                // Travada: não dá para desmarcar (o servidor também garante).
                                                                disabled={
                                                                    travada
                                                                }
                                                                aria-label={`${papel.nome}: ${permissao.nome}${travada ? ' (sempre ligada)' : ''}`}
                                                                className="size-4"
                                                            />
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                A caixa cinza (Tecnologia × Configurar papéis e
                                permissões) fica sempre ligada, para ninguém
                                perder o acesso a esta tela.
                            </p>

                            {/* Desabilitado enquanto envia, para não gravar duas vezes. */}
                            <Button disabled={processing}>
                                Salvar permissões
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela ("migalhas de pão").
Permissoes.layout = {
    breadcrumbs: [{ title: 'Papéis e permissões', href: edit() }],
};
