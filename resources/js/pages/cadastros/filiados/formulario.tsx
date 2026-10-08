/**
 * Tela de FORMULÁRIO do filiado (#37): cadastrar (filiado = null) ou editar.
 *
 * Cadastrar cria também a CONTA do app com o e-mail informado (a pessoa recebe
 * o convite para criar a senha; bloco F3). O número de registro é gerado pela
 * plataforma. LGPD: CPF, nascimento e telefone são gravados criptografados.
 */
import { Form, Head, Link, usePage } from '@inertiajs/react';
import FiliadoController from '@/actions/App/Http/Controllers/Cadastros/FiliadoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { convite, index } from '@/routes/cadastros/filiados';

/** Os dados do filiado na edição. */
type FiliadoFormulario = {
    id: number;
    registro: string;
    nome: string | null;
    email: string | null;
    cpf: string;
    nascimento: string;
    telefone: string | null;
    municipio: string;
    uf: string;
    equipe_id: number | null;
    nivel_id: number | null;
    areas: number[];
    status: string;
    // true quando a pessoa já criou a senha pelo convite.
    conta_ativada: boolean;
};

/** Uma opção com número e nome. */
type Opcao = { id: number; nome: string };

// Visual das caixas de escolha (igual aos campos de texto).
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * @param filiado O filiado a editar, ou null para cadastrar.
 * @param equipes As equipes (para escolher).
 * @param niveis Os níveis ativos, na ordem da hierarquia.
 * @param areas As áreas de atuação ativas.
 * @param ufs As 27 siglas de estado.
 * @returns A tela do formulário.
 */
export default function FiliadoFormularioTela({
    filiado,
    equipes,
    niveis,
    areas,
    ufs,
}: {
    filiado: FiliadoFormulario | null;
    equipes: Opcao[];
    niveis: Opcao[];
    areas: Opcao[];
    ufs: string[];
}) {
    // Mensagem da última ação (ex.: "Convite reenviado."), ou null.
    const { sucesso } = usePage().props;
    const editando = filiado !== null;
    const titulo = editando
        ? `Editar filiado ${filiado.registro}`
        : 'Novo filiado';

    const destino = editando
        ? FiliadoController.update.form(filiado.id)
        : FiliadoController.store.form();

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {titulo}
                    </h1>
                    {!editando && (
                        <p className="text-sm text-muted-foreground">
                            O número de registro é gerado ao salvar. A conta do
                            app é criada com o e-mail informado, e a pessoa
                            recebe um convite para criar a senha.
                        </p>
                    )}
                </div>

                {/* Mensagem da última ação (ex.: "Convite reenviado."). */}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* Situação da conta e botão de reenviar o convite (só na edição). */}
                {editando && (
                    <div className="flex flex-wrap items-center gap-3 rounded-md border border-border px-4 py-3 text-sm">
                        <span>
                            Conta do app:{' '}
                            {filiado.conta_ativada
                                ? 'ativada (senha criada)'
                                : 'aguardando o convite ser usado'}
                        </span>
                        {!filiado.conta_ativada && (
                            // Formulário só com o botão: POST para reenviar o convite.
                            <Form {...convite.form(filiado.id)}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Reenviar convite
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}

                <Form {...destino} className="space-y-8">
                    {({ processing, errors }) => (
                        <>
                            {/* ---------- Pessoa e conta ---------- */}
                            <fieldset className="space-y-4">
                                <legend className="mb-2 font-medium">
                                    Pessoa
                                </legend>
                                <p className="text-sm text-muted-foreground">
                                    CPF, nascimento e telefone ficam
                                    criptografados.
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="nome">Nome completo</Label>
                                    <Input
                                        id="nome"
                                        name="nome"
                                        defaultValue={filiado?.nome ?? ''}
                                        required
                                        maxLength={150}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.nome} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        E-mail (conta do app)
                                    </Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={filiado?.email ?? ''}
                                        required
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="cpf">CPF</Label>
                                        <Input
                                            id="cpf"
                                            name="cpf"
                                            inputMode="numeric"
                                            defaultValue={filiado?.cpf ?? ''}
                                            required
                                            autoComplete="off"
                                        />
                                        <InputError message={errors.cpf} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nascimento">
                                            Nascimento
                                        </Label>
                                        <Input
                                            id="nascimento"
                                            name="nascimento"
                                            type="date"
                                            defaultValue={
                                                filiado?.nascimento ?? ''
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.nascimento}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="telefone">
                                        Telefone (com DDD)
                                    </Label>
                                    <Input
                                        id="telefone"
                                        name="telefone"
                                        type="tel"
                                        defaultValue={filiado?.telefone ?? ''}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.telefone} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                    <div className="grid gap-2">
                                        <Label htmlFor="municipio">
                                            Município
                                        </Label>
                                        <Input
                                            id="municipio"
                                            name="municipio"
                                            defaultValue={
                                                filiado?.municipio ?? ''
                                            }
                                            required
                                            maxLength={100}
                                        />
                                        <InputError
                                            message={errors.municipio}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="uf">Estado</Label>
                                        <select
                                            id="uf"
                                            name="uf"
                                            defaultValue={filiado?.uf ?? ''}
                                            required
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
                            </fieldset>

                            {/* ---------- Filiação ---------- */}
                            <fieldset className="space-y-4">
                                <legend className="mb-2 font-medium">
                                    Filiação
                                </legend>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="equipe_id">
                                            Equipe
                                        </Label>
                                        <select
                                            id="equipe_id"
                                            name="equipe_id"
                                            defaultValue={
                                                filiado?.equipe_id ?? ''
                                            }
                                            className={classeSelect}
                                        >
                                            <option value="">Sem equipe</option>
                                            {equipes.map((equipe) => (
                                                <option
                                                    key={equipe.id}
                                                    value={equipe.id}
                                                >
                                                    {equipe.nome}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.equipe_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nivel_id">Nível</Label>
                                        <select
                                            id="nivel_id"
                                            name="nivel_id"
                                            defaultValue={
                                                filiado?.nivel_id ?? ''
                                            }
                                            className={classeSelect}
                                        >
                                            <option value="">—</option>
                                            {niveis.map((nivel) => (
                                                <option
                                                    key={nivel.id}
                                                    value={nivel.id}
                                                >
                                                    {nivel.nome}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.nivel_id} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Situação</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        defaultValue={
                                            filiado?.status ?? 'ativo'
                                        }
                                        className={classeSelect}
                                    >
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>

                                <fieldset className="space-y-2">
                                    <legend className="text-sm font-medium">
                                        Áreas de atuação
                                    </legend>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {areas.map((area) => (
                                            <label
                                                key={area.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="areas[]"
                                                    value={area.id}
                                                    defaultChecked={
                                                        filiado?.areas.includes(
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
FiliadoFormularioTela.layout = {
    breadcrumbs: [{ title: 'Filiados', href: index() }],
};
