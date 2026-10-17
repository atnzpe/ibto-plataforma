/**
 * Tela de FORMULÁRIO do cupom de desconto (#89): criar (cupom = null) ou editar
 * (inclusive desativar). Campos: código, tipo e valor do desconto, validade
 * de/até, limites, só filiados e os cursos em que vale.
 */
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import CupomController from '@/actions/App/Http/Controllers/Financeiro/CupomController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/financeiro/cupons';
import { index as livroCaixa } from '@/routes/financeiro/lancamentos';

/** O cupom aberto para edição (valores já no formato dos campos). */
type CupomEdicao = {
    id: number;
    codigo: string;
    tipo: 'percentual' | 'valor';
    // Ex.: '10' (percentual) ou '50,00' (reais).
    valor: string;
    // Ex.: '2026-11-01T00:00'.
    valido_de: string | null;
    valido_ate: string | null;
    limite_total: number | null;
    limite_por_pessoa: number;
    so_filiados: boolean;
    todos_cursos: boolean;
    ativo: boolean;
    cursos: number[];
    // true = já foi usado (mudar o desconto não muda as matrículas antigas).
    usado: boolean;
};

/**
 * @param cupom O cupom a editar, ou null para criar um novo.
 * @param cursos Os cursos pagos e abertos, para "vale só nestes cursos".
 * @returns A tela do formulário.
 */
export default function CupomFormulario({
    cupom,
    cursos,
}: {
    cupom: CupomEdicao | null;
    cursos: { id: number; titulo: string }[];
}) {
    const editando = cupom !== null;
    const titulo = editando ? `Editar o cupom ${cupom.codigo}` : 'Novo cupom';
    // Tipo escolhido agora: muda a dica do campo "Valor".
    const [tipo, setTipo] = useState<'percentual' | 'valor'>(
        cupom?.tipo ?? 'percentual',
    );
    // Vale em todos os cursos? Desmarcado, aparece a lista de cursos.
    const [todos, setTodos] = useState(cupom?.todos_cursos ?? true);
    // Para onde o formulário envia: atualizar (edição) ou gravar (novo).
    const destino = editando
        ? CupomController.update.form(cupom.id)
        : CupomController.store.form();
    const classeSelect =
        'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {titulo}
                </h1>

                {/* Já usado: as matrículas antigas ficam com o desconto do dia. */}
                {cupom?.usado && (
                    <p
                        role="note"
                        className="rounded-md border border-border bg-muted p-3 text-sm"
                    >
                        Este cupom já foi usado. Mudar o desconto vale só para
                        as próximas inscrições; para encerrar, desmarque
                        "Ativo".
                    </p>
                )}

                <Form {...destino} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="codigo">Código</Label>
                                <Input
                                    id="codigo"
                                    name="codigo"
                                    maxLength={30}
                                    defaultValue={cupom?.codigo}
                                    placeholder="Ex.: FILIADO10"
                                    autoCapitalize="characters"
                                    className="uppercase"
                                    aria-describedby="codigo-dica"
                                />
                                <p
                                    id="codigo-dica"
                                    className="text-xs text-muted-foreground"
                                >
                                    Só letras e números, sem espaço. É o que o
                                    aluno digita.
                                </p>
                                <InputError message={errors.codigo} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="tipo">
                                        Tipo de desconto
                                    </Label>
                                    <select
                                        id="tipo"
                                        name="tipo"
                                        value={tipo}
                                        onChange={(evento) =>
                                            setTipo(
                                                evento.target.value as
                                                    | 'percentual'
                                                    | 'valor',
                                            )
                                        }
                                        className={classeSelect}
                                    >
                                        <option value="percentual">
                                            Percentual (%)
                                        </option>
                                        <option value="valor">
                                            Valor fixo (R$)
                                        </option>
                                    </select>
                                    <InputError message={errors.tipo} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="valor">
                                        {tipo === 'percentual'
                                            ? 'Percentual (1 a 100)'
                                            : 'Valor do desconto (R$)'}
                                    </Label>
                                    <Input
                                        id="valor"
                                        name="valor"
                                        inputMode="decimal"
                                        maxLength={20}
                                        defaultValue={cupom?.valor}
                                        placeholder={
                                            tipo === 'percentual'
                                                ? 'Ex.: 10'
                                                : 'Ex.: 50,00'
                                        }
                                    />
                                    <InputError message={errors.valor} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="valido_de">
                                        Vale a partir de
                                    </Label>
                                    <Input
                                        id="valido_de"
                                        name="valido_de"
                                        type="datetime-local"
                                        defaultValue={cupom?.valido_de ?? ''}
                                    />
                                    <InputError message={errors.valido_de} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="valido_ate">Vale até</Label>
                                    <Input
                                        id="valido_ate"
                                        name="valido_ate"
                                        type="datetime-local"
                                        defaultValue={cupom?.valido_ate ?? ''}
                                    />
                                    <InputError message={errors.valido_ate} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="limite_total">
                                        Limite total de usos
                                    </Label>
                                    <Input
                                        id="limite_total"
                                        name="limite_total"
                                        type="number"
                                        min={1}
                                        inputMode="numeric"
                                        defaultValue={cupom?.limite_total ?? ''}
                                        placeholder="Vazio = sem limite"
                                    />
                                    <InputError message={errors.limite_total} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="limite_por_pessoa">
                                        Usos por pessoa
                                    </Label>
                                    <Input
                                        id="limite_por_pessoa"
                                        name="limite_por_pessoa"
                                        type="number"
                                        min={1}
                                        inputMode="numeric"
                                        defaultValue={
                                            cupom?.limite_por_pessoa ?? 1
                                        }
                                    />
                                    <InputError
                                        message={errors.limite_por_pessoa}
                                    />
                                </div>
                            </div>

                            {/* Caixas de sim/não: o "0" escondido vai quando a caixa está desmarcada. */}
                            <div className="grid gap-2">
                                <input
                                    type="hidden"
                                    name="so_filiados"
                                    value="0"
                                />
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="so_filiados"
                                        value="1"
                                        defaultChecked={
                                            cupom?.so_filiados ?? false
                                        }
                                        className="size-4"
                                    />
                                    Só para filiados ativos
                                </label>
                                <input
                                    type="hidden"
                                    name="todos_cursos"
                                    value="0"
                                />
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="todos_cursos"
                                        value="1"
                                        checked={todos}
                                        onChange={(evento) =>
                                            setTodos(evento.target.checked)
                                        }
                                        className="size-4"
                                    />
                                    Vale em todos os cursos pagos
                                </label>
                            </div>

                            {/* Vale só em alguns cursos: a lista para marcar. */}
                            {!todos && (
                                <fieldset className="grid gap-2">
                                    <legend className="text-sm font-medium">
                                        Cursos em que o cupom vale
                                    </legend>
                                    <div className="grid max-h-56 gap-1 overflow-y-auto rounded-md border border-border p-2">
                                        {cursos.map((curso) => (
                                            <label
                                                key={curso.id}
                                                className="flex min-h-9 items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="cursos[]"
                                                    value={curso.id}
                                                    defaultChecked={
                                                        cupom?.cursos.includes(
                                                            curso.id,
                                                        ) ?? false
                                                    }
                                                    className="size-4"
                                                />
                                                {curso.titulo}
                                            </label>
                                        ))}
                                    </div>
                                    <InputError message={errors.cursos} />
                                </fieldset>
                            )}

                            <div className="grid gap-2">
                                <input type="hidden" name="ativo" value="0" />
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="ativo"
                                        value="1"
                                        defaultChecked={cupom?.ativo ?? true}
                                        className="size-4"
                                    />
                                    Ativo (aceito nas inscrições)
                                </label>
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
CupomFormulario.layout = {
    breadcrumbs: [
        { title: 'Financeiro', href: livroCaixa() },
        { title: 'Cupons', href: index() },
    ],
};
