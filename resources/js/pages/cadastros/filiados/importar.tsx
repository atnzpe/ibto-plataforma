/**
 * Importação de filiados, TELA 1 de 3 (#37, F4b): enviar o arquivo CSV.
 * Depois vêm: relacionar as colunas → prévia e confirmar.
 * LGPD: o arquivo não é guardado no servidor.
 */
import { Form, Head, Link } from '@inertiajs/react';
import ImportacaoFiliadosController from '@/actions/App/Http/Controllers/Cadastros/ImportacaoFiliadosController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/cadastros/filiados';

/**
 * @returns A tela de envio da planilha.
 */
export default function ImportarFiliados() {
    return (
        <>
            <Head title="Importar filiados" />

            <div className="flex max-w-xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Importar filiados
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Passo 1 de 4: envie a planilha em CSV. No próximo passo,
                        você liga cada coluna da planilha ao campo certo.
                    </p>
                </div>

                <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                    <li>
                        No Excel ou no Google Planilhas: Arquivo → Salvar como
                        (ou Fazer download) → CSV.
                    </li>
                    <li>A primeira linha precisa ter os nomes das colunas.</li>
                    <li>
                        Cada filiado precisa de e-mail: a conta do app e o
                        convite são criados com ele.
                    </li>
                </ol>

                <Form
                    {...ImportacaoFiliadosController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="planilha">Arquivo CSV</Label>
                                <Input
                                    id="planilha"
                                    name="planilha"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                />
                                <InputError message={errors.planilha} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>
                                    Enviar e relacionar colunas
                                </Button>
                                <Link
                                    href={index()}
                                    className="text-sm underline underline-offset-4"
                                >
                                    Cancelar
                                </Link>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho no topo: volta para a lista de filiados.
ImportarFiliados.layout = {
    breadcrumbs: [{ title: 'Filiados', href: index() }],
};
