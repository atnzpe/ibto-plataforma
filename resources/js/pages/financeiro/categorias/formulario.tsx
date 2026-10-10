/**
 * Tela de FORMULÁRIO da categoria do financeiro (#59): cadastrar (categoria =
 * null) ou editar. Se a categoria já tem lançamentos, o tipo fica travado
 * (a regra de verdade é conferida no servidor: CategoriaRequest).
 */
import { Form, Head, Link } from '@inertiajs/react';
import CategoriaController from '@/actions/App/Http/Controllers/Financeiro/CategoriaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/financeiro/categorias';
import { index as livroCaixa } from '@/routes/financeiro/lancamentos';

/** A categoria aberta para edição. */
type CategoriaEdicao = {
    id: number;
    nome: string;
    tipo: 'receita' | 'despesa';
    ativa: boolean;
    // true se já tem lançamentos (o tipo não pode mudar).
    em_uso: boolean;
};

/**
 * @param categoria A categoria a editar, ou null para cadastrar uma nova.
 * @returns A tela do formulário.
 */
export default function CategoriaFormulario({
    categoria,
}: {
    categoria: CategoriaEdicao | null;
}) {
    // true quando estamos editando.
    const editando = categoria !== null;
    const titulo = editando ? `Editar "${categoria.nome}"` : 'Nova categoria';

    // Para onde o formulário envia: atualizar (edição) ou gravar (nova).
    const destino = editando
        ? CategoriaController.update.form(categoria.id)
        : CategoriaController.store.form();

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
                                    maxLength={100}
                                    defaultValue={categoria?.nome}
                                    placeholder="Ex.: Patrocínio"
                                />
                                <InputError message={errors.nome} />
                            </div>

                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Tipo
                                </legend>
                                {/* Em uso: o tipo fica travado e a tela explica por quê. */}
                                {categoria?.em_uso && (
                                    <input
                                        type="hidden"
                                        name="tipo"
                                        value={categoria.tipo}
                                    />
                                )}
                                <div className="grid grid-cols-2 gap-3 text-sm sm:flex sm:gap-6">
                                    {(['receita', 'despesa'] as const).map(
                                        (opcao) => (
                                            <label
                                                key={opcao}
                                                className="flex min-h-11 items-center gap-2"
                                            >
                                                <input
                                                    type="radio"
                                                    name={
                                                        categoria?.em_uso
                                                            ? undefined
                                                            : 'tipo'
                                                    }
                                                    value={opcao}
                                                    defaultChecked={
                                                        (categoria?.tipo ??
                                                            'despesa') === opcao
                                                    }
                                                    disabled={categoria?.em_uso}
                                                    className="size-4"
                                                />
                                                {opcao === 'receita'
                                                    ? 'Receita (entra)'
                                                    : 'Despesa (sai)'}
                                            </label>
                                        ),
                                    )}
                                </div>
                                {categoria?.em_uso && (
                                    <p className="text-xs text-muted-foreground">
                                        Esta categoria já tem lançamentos: o
                                        tipo não pode mudar.
                                    </p>
                                )}
                                <InputError message={errors.tipo} />
                            </fieldset>

                            <div className="grid gap-2">
                                {/* "0" escondido: caixa desmarcada não é enviada pelo navegador. */}
                                <input type="hidden" name="ativa" value="0" />
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="ativa"
                                        value="1"
                                        defaultChecked={
                                            categoria?.ativa ?? true
                                        }
                                        className="size-4"
                                    />
                                    Ativa (aparece nas opções de lançamento)
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
CategoriaFormulario.layout = {
    breadcrumbs: [
        { title: 'Financeiro', href: livroCaixa() },
        { title: 'Categorias', href: index() },
    ],
};
