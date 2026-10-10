/**
 * Tela de FORMULÁRIO da forma de pagamento do financeiro (#59): cadastrar
 * (forma = null) ou editar (inclusive desativar).
 */
import { Form, Head, Link } from '@inertiajs/react';
import FormaPagamentoController from '@/actions/App/Http/Controllers/Financeiro/FormaPagamentoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/financeiro/formas';
import { index as livroCaixa } from '@/routes/financeiro/lancamentos';

/**
 * @param forma A forma a editar, ou null para cadastrar uma nova.
 * @returns A tela do formulário.
 */
export default function FormaFormulario({
    forma,
}: {
    forma: { id: number; nome: string; ativa: boolean } | null;
}) {
    // true quando estamos editando.
    const editando = forma !== null;
    const titulo = editando
        ? `Editar "${forma.nome}"`
        : 'Nova forma de pagamento';

    // Para onde o formulário envia: atualizar (edição) ou gravar (nova).
    const destino = editando
        ? FormaPagamentoController.update.form(forma.id)
        : FormaPagamentoController.store.form();

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {titulo}
                </h1>

                <Form {...destino} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nome">Nome</Label>
                                <Input
                                    id="nome"
                                    name="nome"
                                    maxLength={50}
                                    defaultValue={forma?.nome}
                                    placeholder="Ex.: Boleto"
                                />
                                <InputError message={errors.nome} />
                            </div>

                            <div className="grid gap-2">
                                {/* "0" escondido: caixa desmarcada não é enviada pelo navegador. */}
                                <input type="hidden" name="ativa" value="0" />
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="ativa"
                                        value="1"
                                        defaultChecked={forma?.ativa ?? true}
                                        className="size-4"
                                    />
                                    Ativa (aparece na baixa)
                                </label>
                                <InputError message={errors.ativa} />
                            </div>

                            <div className="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                                {/* Desabilitado enquanto envia, para não gravar duas vezes. */}
                                <Button disabled={processing}>Salvar</Button>
                                <Button asChild variant="outline">
                                    <Link href={index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
FormaFormulario.layout = {
    breadcrumbs: [
        { title: 'Financeiro', href: livroCaixa() },
        { title: 'Formas de pagamento', href: index() },
    ],
};
