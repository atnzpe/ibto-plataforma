/**
 * Tela de PAPÉIS de uma pessoa (#36): caixas para marcar Aluno, Filiado,
 * Instrutor, Administrador e Tecnologia. O papel Tecnologia só pode ser
 * mudado por quem mexe nas permissões; para os outros, a caixa fica travada
 * (e o servidor também garante isso).
 */
import { Form, Head, Link } from '@inertiajs/react';
import UsuarioController from '@/actions/App/Http/Controllers/Admin/UsuarioController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/admin/usuarios';

/** Um papel para marcar. */
type PapelOpcao = {
    id: number;
    slug: string;
    nome: string;
    descricao: string | null;
};

/**
 * @param usuario A pessoa, com os números dos papéis que ela já tem.
 * @param papeis Todos os papéis.
 * @param pode_papel_protegido Se quem está logado pode mudar o papel protegido.
 * @param papel_protegido O slug do papel protegido. Ex.: 'tecnologia'.
 * @returns A tela do formulário.
 */
export default function UsuarioFormulario({
    usuario,
    papeis,
    pode_papel_protegido,
    papel_protegido,
}: {
    usuario: { id: number; nome: string; email: string; papeis: number[] };
    papeis: PapelOpcao[];
    pode_papel_protegido: boolean;
    papel_protegido: string;
}) {
    const titulo = `Papéis de ${usuario.nome}`;

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {titulo}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {usuario.email}
                    </p>
                </div>

                <Form
                    {...UsuarioController.update.form(usuario.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset className="space-y-3">
                                <legend className="mb-2 font-medium">
                                    Papéis
                                </legend>
                                {papeis.map((papel) => {
                                    // Travada: papel protegido e a pessoa logada não pode mexer nele.
                                    const travada =
                                        papel.slug === papel_protegido &&
                                        !pode_papel_protegido;

                                    return (
                                        <label
                                            key={papel.id}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="papeis[]"
                                                value={papel.id}
                                                defaultChecked={usuario.papeis.includes(
                                                    papel.id,
                                                )}
                                                disabled={travada}
                                                className="mt-0.5 size-4"
                                            />
                                            <span>
                                                <span className="font-medium">
                                                    {papel.nome}
                                                </span>
                                                {travada &&
                                                    ' (só a Tecnologia muda este papel)'}
                                                {papel.descricao && (
                                                    <span className="block text-muted-foreground">
                                                        {papel.descricao}
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    );
                                })}
                                <InputError message={errors.papeis} />
                            </fieldset>

                            <div className="flex items-center gap-4">
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
UsuarioFormulario.layout = {
    breadcrumbs: [{ title: 'Usuários', href: index() }],
};
