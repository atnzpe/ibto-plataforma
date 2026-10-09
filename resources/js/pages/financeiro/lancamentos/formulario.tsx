/**
 * Tela de LANÇAMENTO do financeiro (#59): cadastrar (lancamento = null) ou
 * EDITAR um pendente. Uma conta a receber (receita) ou a pagar (despesa).
 * Nasce PENDENTE; a baixa (pagamento) é outra ação. Pago não se edita (estorno).
 * A caixa de categorias mostra só as do tipo escolhido; a regra de verdade é
 * conferida no servidor (LancamentoRequest + LivroCaixa).
 */
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import LancamentoController from '@/actions/App/Http/Controllers/Financeiro/LancamentoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/financeiro/lancamentos';
import type { CategoriaFinanceira, LancamentoEdicao } from '@/types/financeiro';

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param lancamento O lançamento pendente a editar, ou null para cadastrar um novo.
 * @param categorias As categorias ativas. Ex.: [{ id: 1, nome: 'Anuidade', tipo: 'receita' }]
 * @param equipes As equipes, para ligar o lançamento a uma (opcional).
 * @returns A tela do formulário.
 */
export default function LancamentoFormulario({
    lancamento,
    categorias,
    equipes,
}: {
    lancamento: LancamentoEdicao | null;
    categorias: CategoriaFinanceira[];
    equipes: { id: number; nome: string }[];
}) {
    // true quando estamos editando.
    const editando = lancamento !== null;
    const titulo = editando ? 'Editar lançamento' : 'Novo lançamento';

    // Tipo escolhido agora (começa com o do lançamento, ou despesa): a lista de
    // categorias depende dele.
    const [tipo, setTipo] = useState<'receita' | 'despesa'>(
        lancamento?.tipo ?? 'despesa',
    );

    // Só as categorias do tipo escolhido.
    const categoriasDoTipo = categorias.filter(
        (categoria) => categoria.tipo === tipo,
    );

    // Para onde o formulário envia: atualizar (edição) ou gravar (novo).
    const destino = editando
        ? LancamentoController.update.form(lancamento.id)
        : LancamentoController.store.form();

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
                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Tipo
                                </legend>
                                <div className="flex gap-6 text-sm">
                                    {/* Trocar o tipo troca a lista de categorias. */}
                                    {(['despesa', 'receita'] as const).map(
                                        (opcao) => (
                                            <label
                                                key={opcao}
                                                className="flex items-center gap-2"
                                            >
                                                <input
                                                    type="radio"
                                                    name="tipo"
                                                    value={opcao}
                                                    checked={tipo === opcao}
                                                    onChange={() =>
                                                        setTipo(opcao)
                                                    }
                                                    className="size-4"
                                                />
                                                {opcao === 'despesa'
                                                    ? 'Despesa (a pagar)'
                                                    : 'Receita (a receber)'}
                                            </label>
                                        ),
                                    )}
                                </div>
                                <InputError message={errors.tipo} />
                            </fieldset>

                            <div className="grid gap-2">
                                <Label htmlFor="categoria_id">Categoria</Label>
                                <select
                                    id="categoria_id"
                                    name="categoria_id"
                                    // key: ao trocar o tipo, a caixa volta ao começo.
                                    key={tipo}
                                    // Na edição, começa na categoria atual (se ela for do tipo escolhido).
                                    defaultValue={
                                        lancamento?.tipo === tipo
                                            ? lancamento.categoria_id
                                            : ''
                                    }
                                    className={classeSelect}
                                >
                                    <option value="" disabled>
                                        Escolha a categoria
                                    </option>
                                    {categoriasDoTipo.map((categoria) => (
                                        <option
                                            key={categoria.id}
                                            value={categoria.id}
                                        >
                                            {categoria.nome}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.categoria_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="descricao">Descrição</Label>
                                <Input
                                    id="descricao"
                                    name="descricao"
                                    maxLength={200}
                                    defaultValue={lancamento?.descricao}
                                    placeholder="Ex.: Hospedagem do site (outubro)"
                                />
                                <InputError message={errors.descricao} />
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="valor">Valor (R$)</Label>
                                    {/* inputMode decimal: no celular abre o teclado numérico com vírgula. */}
                                    <Input
                                        id="valor"
                                        name="valor"
                                        inputMode="decimal"
                                        defaultValue={lancamento?.valor}
                                        placeholder="90,00"
                                        aria-describedby="valor-dica"
                                    />
                                    {/* Dica para não confundir centavos com milhar. */}
                                    <p
                                        id="valor-dica"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Centavos com vírgula: 12,50. Mil reais:
                                        1.000,00.
                                    </p>
                                    <InputError message={errors.valor} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="vencimento">
                                        Vencimento
                                    </Label>
                                    <Input
                                        id="vencimento"
                                        name="vencimento"
                                        type="date"
                                        defaultValue={lancamento?.vencimento}
                                    />
                                    <InputError message={errors.vencimento} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="equipe_id">
                                    Equipe (opcional)
                                </Label>
                                <select
                                    id="equipe_id"
                                    name="equipe_id"
                                    defaultValue={lancamento?.equipe_id ?? ''}
                                    className={classeSelect}
                                >
                                    <option value="">Nenhuma</option>
                                    {equipes.map((equipe) => (
                                        <option
                                            key={equipe.id}
                                            value={equipe.id}
                                        >
                                            {equipe.nome}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.equipe_id} />
                            </div>

                            <div className="flex items-center gap-4">
                                {/* Desabilitado enquanto envia, para não gravar duas vezes. */}
                                <Button disabled={processing}>Salvar</Button>
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

// Caminho mostrado no topo da tela: volta para o livro-caixa.
LancamentoFormulario.layout = {
    breadcrumbs: [{ title: 'Financeiro', href: index() }],
};
