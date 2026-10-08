/**
 * Tela de FORMULÁRIO da equipe (#37): cadastrar (equipe = null) ou editar.
 *
 * "Em homologação" pode ficar incompleta; para salvar como "Ativa", o servidor
 * exige cidade, estado, nome e telefone do responsável e pelo menos uma área
 * (EquipeRequest).
 * LGPD: telefone, e-mail e documento do responsável são gravados criptografados.
 */
import { Form, Head, Link } from '@inertiajs/react';
import EquipeController from '@/actions/App/Http/Controllers/Cadastros/EquipeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/cadastros/equipes';
import type { EquipeFormulario, Opcao } from '@/types/cadastros';

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param equipe A equipe a editar, ou null para cadastrar uma nova.
 * @param situacoes As 3 situações (em homologação, ativa, inativa).
 * @param ufs As 27 siglas de estado.
 * @param areas As áreas de atuação ATIVAS, para marcar.
 * @returns A tela do formulário.
 */
export default function EquipeFormularioTela({
    equipe,
    situacoes,
    ufs,
    areas,
}: {
    equipe: EquipeFormulario | null;
    situacoes: Opcao[];
    ufs: string[];
    areas: { id: number; nome: string }[];
}) {
    const editando = equipe !== null;
    const titulo = editando ? `Editar "${equipe.nome}"` : 'Nova equipe';

    // Atualizar (edição) ou gravar (nova).
    const destino = editando
        ? EquipeController.update.form(equipe.id)
        : EquipeController.store.form();

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {titulo}
                </h1>

                <Form {...destino} className="space-y-8">
                    {({ processing, errors }) => (
                        <>
                            {/* ---------- Dados da equipe ---------- */}
                            <fieldset className="space-y-4">
                                <legend className="mb-2 font-medium">
                                    Equipe
                                </legend>

                                <div className="grid gap-2">
                                    <Label htmlFor="nome">Nome</Label>
                                    <Input
                                        id="nome"
                                        name="nome"
                                        defaultValue={equipe?.nome ?? ''}
                                        required
                                        maxLength={150}
                                    />
                                    <InputError message={errors.nome} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Situação</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        defaultValue={
                                            equipe?.status ?? 'em_homologacao'
                                        }
                                        className={classeSelect}
                                        aria-describedby="dica-status"
                                    >
                                        {situacoes.map((opcao) => (
                                            <option
                                                key={opcao.valor}
                                                value={opcao.valor}
                                            >
                                                {opcao.rotulo}
                                            </option>
                                        ))}
                                    </select>
                                    <p
                                        id="dica-status"
                                        className="text-sm text-muted-foreground"
                                    >
                                        Para ficar "Ativa", a equipe precisa de
                                        cidade, estado, nome e telefone do
                                        responsável e pelo menos uma área.
                                    </p>
                                    <InputError message={errors.status} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                    <div className="grid gap-2">
                                        <Label htmlFor="cidade">Cidade</Label>
                                        <Input
                                            id="cidade"
                                            name="cidade"
                                            defaultValue={equipe?.cidade ?? ''}
                                            maxLength={100}
                                        />
                                        <InputError message={errors.cidade} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="uf">Estado</Label>
                                        <select
                                            id="uf"
                                            name="uf"
                                            defaultValue={equipe?.uf ?? ''}
                                            className={classeSelect}
                                        >
                                            <option value="">—</option>
                                            {ufs.map((uf) => (
                                                <option key={uf} value={uf}>
                                                    {uf}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.uf} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="logo">Logo</Label>
                                    {equipe?.logo_url && (
                                        <img
                                            src={equipe.logo_url}
                                            alt={`Logo atual de ${equipe.nome}`}
                                            className="size-24 rounded-md border border-border object-contain"
                                        />
                                    )}
                                    <Input
                                        id="logo"
                                        name="logo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        aria-describedby="dica-logo"
                                    />
                                    <p
                                        id="dica-logo"
                                        className="text-sm text-muted-foreground"
                                    >
                                        JPG, PNG ou WEBP, até 2 MB.
                                        {editando &&
                                            ' Deixe em branco para manter a logo atual.'}
                                    </p>
                                    <InputError message={errors.logo} />
                                </div>
                            </fieldset>

                            {/* ---------- Áreas de atuação (várias) ---------- */}
                            <fieldset className="space-y-2">
                                <legend className="mb-2 font-medium">
                                    Áreas de atuação
                                </legend>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {areas.map((area) => (
                                        <label
                                            key={area.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            {/* name="areas[]": o navegador envia uma lista com as marcadas. */}
                                            <input
                                                type="checkbox"
                                                name="areas[]"
                                                value={area.id}
                                                defaultChecked={
                                                    equipe?.areas.includes(
                                                        area.id,
                                                    ) ?? false
                                                }
                                                className="size-4"
                                            />
                                            {area.nome}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.areas} />
                            </fieldset>

                            {/* ---------- Responsável (dados pessoais: LGPD) ---------- */}
                            <fieldset className="space-y-4">
                                <legend className="mb-2 font-medium">
                                    Responsável
                                </legend>
                                <p className="text-sm text-muted-foreground">
                                    Telefone, e-mail e documento ficam
                                    criptografados e só administradores veem.
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="responsavel_nome">
                                        Nome do responsável
                                    </Label>
                                    <Input
                                        id="responsavel_nome"
                                        name="responsavel_nome"
                                        defaultValue={
                                            equipe?.responsavel_nome ?? ''
                                        }
                                        maxLength={150}
                                        autoComplete="off"
                                    />
                                    <InputError
                                        message={errors.responsavel_nome}
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="responsavel_telefone">
                                            Telefone (com DDD)
                                        </Label>
                                        <Input
                                            id="responsavel_telefone"
                                            name="responsavel_telefone"
                                            type="tel"
                                            inputMode="tel"
                                            defaultValue={
                                                equipe?.responsavel_telefone ??
                                                ''
                                            }
                                            placeholder="Ex.: (81) 99999-0000"
                                            autoComplete="off"
                                        />
                                        <InputError
                                            message={
                                                errors.responsavel_telefone
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="responsavel_email">
                                            E-mail
                                        </Label>
                                        <Input
                                            id="responsavel_email"
                                            name="responsavel_email"
                                            type="email"
                                            defaultValue={
                                                equipe?.responsavel_email ?? ''
                                            }
                                            autoComplete="off"
                                        />
                                        <InputError
                                            message={errors.responsavel_email}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="documento">
                                        CNPJ da equipe ou CPF do responsável
                                        (opcional)
                                    </Label>
                                    <Input
                                        id="documento"
                                        name="documento"
                                        inputMode="numeric"
                                        defaultValue={equipe?.documento ?? ''}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.documento} />
                                </div>
                            </fieldset>

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
EquipeFormularioTela.layout = {
    breadcrumbs: [{ title: 'Equipes', href: index() }],
};
