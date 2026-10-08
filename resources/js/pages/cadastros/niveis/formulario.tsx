/**
 * Tela de FORMULÁRIO do nível (#37): cadastrar (nivel = null) ou editar.
 *
 * O campo "Grau" muda conforme a categoria: Praticante não tem grau; Monitor
 * mostra I–II; Instrutor e Instrutor Expert, I–V. Isso só ajuda quem preenche:
 * a regra é conferida de verdade no servidor (NivelRequest).
 */
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import NivelController from '@/actions/App/Http/Controllers/Cadastros/NivelController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/cadastros/niveis';
import type { CategoriaNivel, Nivel } from '@/types/cadastros';

// Graus em algarismos romanos, como aparecem nas graduações.
const ROMANOS = ['I', 'II', 'III', 'IV', 'V'];

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param nivel O nível a editar, ou null para cadastrar um novo.
 * @param categorias As 4 categorias com o grau máximo de cada uma (vêm do servidor).
 * @returns A tela do formulário.
 */
export default function NivelFormulario({
    nivel,
    categorias,
}: {
    nivel: Nivel | null;
    categorias: CategoriaNivel[];
}) {
    // true quando estamos editando.
    const editando = nivel !== null;
    const titulo = editando ? `Editar "${nivel.nome}"` : 'Novo nível';

    // Categoria escolhida agora (começa com a do nível, ou a primeira da lista).
    const [categoria, setCategoria] = useState(
        nivel?.categoria ?? categorias[0]?.valor ?? '',
    );

    // Maior grau da categoria escolhida (0 = sem grau).
    const grauMaximo =
        categorias.find((opcao) => opcao.valor === categoria)?.grau_maximo ?? 0;

    // Para onde o formulário envia: atualizar (edição) ou gravar (novo).
    const destino = editando
        ? NivelController.update.form(nivel.id)
        : NivelController.store.form();

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
                                <Label htmlFor="categoria">Categoria</Label>
                                <select
                                    id="categoria"
                                    name="categoria"
                                    value={categoria}
                                    onChange={(evento) =>
                                        setCategoria(evento.target.value)
                                    }
                                    className={classeSelect}
                                >
                                    {categorias.map((opcao) => (
                                        <option
                                            key={opcao.valor}
                                            value={opcao.valor}
                                        >
                                            {opcao.valor}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.categoria} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="grau">Grau</Label>
                                {/* Sem grau (Praticante): o campo vai vazio e fica travado. */}
                                <select
                                    id="grau"
                                    name="grau"
                                    // key: ao trocar de categoria, a caixa volta ao começo.
                                    key={categoria}
                                    defaultValue={
                                        nivel?.categoria === categoria
                                            ? (nivel.grau ?? '')
                                            : ''
                                    }
                                    disabled={grauMaximo === 0}
                                    className={classeSelect}
                                >
                                    <option value="">
                                        {grauMaximo === 0
                                            ? 'Sem grau'
                                            : 'Escolha o grau'}
                                    </option>
                                    {ROMANOS.slice(0, grauMaximo).map(
                                        (romano, posicao) => (
                                            <option
                                                key={romano}
                                                value={posicao + 1}
                                            >
                                                {romano}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError message={errors.grau} />
                            </div>

                            <div className="grid gap-2">
                                {/* "0" escondido: caixa desmarcada não é enviada pelo navegador. */}
                                <input type="hidden" name="ativo" value="0" />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="ativo"
                                        value="1"
                                        defaultChecked={nivel?.ativo ?? true}
                                        className="size-4"
                                    />
                                    Ativo (aparece nas listas de escolha)
                                </label>
                                <InputError message={errors.ativo} />
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

// Caminho mostrado no topo da tela: volta para a lista.
NivelFormulario.layout = {
    breadcrumbs: [{ title: 'Níveis', href: index() }],
};
