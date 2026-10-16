/**
 * CAMPOS DE PRÉ-REQUISITOS no cadastro do curso (#88). Só o IBTO vê e grava.
 *
 * Três tipos, todos opcionais (todos os marcados precisam ser cumpridos):
 * - cursos anteriores: basta ter concluído UM dos marcados (certificado válido);
 * - nível mínimo: o nível do filiado igual ou acima;
 * - tempo mínimo de filiado, em meses.
 * A conferência de verdade é no servidor, na hora da inscrição.
 */
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * @param valores O que já está gravado (curso novo: tudo vazio).
 * @param niveis Os níveis em ordem (Praticante I … Instrutor Expert V).
 * @param cursos Os outros cursos que podem ser exigidos (id e título).
 * @param erros As mensagens do servidor, pelo nome do campo.
 * @param desativado true = só olhar (curso que o dono não pode mais editar).
 * @returns A seção "Pré-requisitos".
 */
export default function PreRequisitosCampos({
    valores,
    niveis,
    cursos,
    erros,
    desativado = false,
}: {
    valores: {
        nivel_minimo_id: number | null;
        meses_filiacao_minimo: number | null;
        requisitos: number[];
    };
    niveis: { id: number; nome: string }[];
    cursos: { id: number; titulo: string }[];
    erros: Record<string, string | undefined>;
    desativado?: boolean;
}) {
    // Mesmo visual dos outros campos de escolha do formulário.
    const classeSelect =
        'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

    return (
        <fieldset
            className="grid gap-4 rounded-md border border-border p-4"
            disabled={desativado}
        >
            <legend className="px-1 text-sm font-semibold">
                Pré-requisitos (opcional)
            </legend>
            <p className="text-xs text-muted-foreground">
                Como nas graduações: quem não cumpre não consegue se inscrever
                sozinho. O Comando Geral pode matricular por exceção, com o
                motivo.
            </p>

            {/* Cursos anteriores: "requisitos[]" manda a lista; o campo escondido manda vazio se nada for marcado. */}
            <div className="grid gap-2">
                <span className="text-sm font-medium">
                    Ter concluído um destes cursos
                </span>
                <input type="hidden" name="requisitos_enviados" value="1" />
                {cursos.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                        Nenhum outro curso cadastrado ainda.
                    </p>
                )}
                <div className="grid max-h-48 gap-1 overflow-y-auto rounded-md border border-border p-2">
                    {cursos.map((curso) => (
                        <label
                            key={curso.id}
                            className="flex min-h-9 items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                name="requisitos[]"
                                value={curso.id}
                                defaultChecked={valores.requisitos.includes(
                                    curso.id,
                                )}
                                className="size-4"
                            />
                            {curso.titulo}
                        </label>
                    ))}
                </div>
                <InputError
                    message={erros.requisitos ?? erros['requisitos.0']}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {/* Nível mínimo: vazio = qualquer nível. */}
                <div className="grid gap-1">
                    <Label htmlFor="nivel_minimo_id">Nível mínimo</Label>
                    <select
                        id="nivel_minimo_id"
                        name="nivel_minimo_id"
                        defaultValue={valores.nivel_minimo_id ?? ''}
                        className={classeSelect}
                    >
                        <option value="">Qualquer nível</option>
                        {niveis.map((nivel) => (
                            <option key={nivel.id} value={nivel.id}>
                                {nivel.nome} ou acima
                            </option>
                        ))}
                    </select>
                    <InputError message={erros.nivel_minimo_id} />
                </div>

                {/* Tempo mínimo de filiado, em meses: vazio = sem exigência. */}
                <div className="grid gap-1">
                    <Label htmlFor="meses_filiacao_minimo">
                        Tempo mínimo de filiado (meses)
                    </Label>
                    <Input
                        id="meses_filiacao_minimo"
                        name="meses_filiacao_minimo"
                        type="number"
                        min={1}
                        max={600}
                        inputMode="numeric"
                        defaultValue={valores.meses_filiacao_minimo ?? ''}
                        placeholder="Ex.: 12"
                        aria-describedby="meses-dica"
                    />
                    <p
                        id="meses-dica"
                        className="text-xs text-muted-foreground"
                    >
                        Conta da data de filiação, na ficha do filiado.
                    </p>
                    <InputError message={erros.meses_filiacao_minimo} />
                </div>
            </div>
        </fieldset>
    );
}
