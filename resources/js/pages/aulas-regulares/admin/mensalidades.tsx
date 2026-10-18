/**
 * MENSALIDADES — COMANDO GERAL (#80): a fila das mensalidades aguardando o Pix
 * (o identificador IBTOAUL… ajuda a achar no extrato) com "Confirmar" (data do
 * Pix), e as assinaturas com a situação do acesso e "Cancelar".
 * Responsivo: cartões no celular, tabela a partir de 1024 px.
 */
import { Form, Head, router, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';

/** Uma mensalidade aguardando. */
type Aguardando = {
    id: number;
    txid: string;
    programa: string;
    pessoa: string;
    referencia: string;
    valor_centavos: number;
    vence_em: string;
};
/** Uma assinatura. */
type AssinaturaItem = {
    id: number;
    programa: string;
    pessoa: string;
    acesso: { acesso: string; rotulo: string };
    pago_ate: string | null;
};

/** Hoje no formato do campo de data. Ex.: '2026-10-17'. */
const hoje = () => new Date().toISOString().slice(0, 10);

/**
 * @param aguardando As mensalidades aguardando o Pix.
 * @param assinaturas As assinaturas (não canceladas).
 * @returns A tela.
 */
export default function Mensalidades({
    aguardando,
    assinaturas,
}: {
    aguardando: Aguardando[];
    assinaturas: AssinaturaItem[];
}) {
    const { sucesso, errors } = usePage<{
        sucesso?: string | null;
        errors: Record<string, string>;
    }>().props;

    return (
        <>
            <Head title="Mensalidades" />
            <div className="flex flex-col gap-8 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Mensalidades das aulas regulares
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Confira o Pix no extrato pelo identificador e confirme.
                        A receita e a parte do instrutor entram no livro-caixa.
                    </p>
                </div>
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}
                <InputError message={errors.mensalidade} />

                <section
                    aria-labelledby="t-aguardando"
                    className="flex flex-col gap-3"
                >
                    <h2 id="t-aguardando" className="font-medium">
                        Aguardando o Pix ({aguardando.length})
                    </h2>
                    {aguardando.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nenhuma mensalidade aguardando.
                        </p>
                    ) : (
                        <table className="tabela-cartoes w-full text-sm">
                            <thead>
                                <tr className="text-left">
                                    <th scope="col" className="py-2">
                                        Identificador
                                    </th>
                                    <th scope="col" className="py-2">
                                        Programa
                                    </th>
                                    <th scope="col" className="py-2">
                                        Pessoa
                                    </th>
                                    <th scope="col" className="py-2">
                                        Mês
                                    </th>
                                    <th scope="col" className="py-2">
                                        Valor
                                    </th>
                                    <th scope="col" className="py-2">
                                        Vence
                                    </th>
                                    <th scope="col" className="py-2">
                                        Confirmar
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {aguardando.map((m) => (
                                    <tr key={m.id} className="border-t">
                                        <td
                                            data-rotulo="Identificador"
                                            className="py-2 font-mono"
                                        >
                                            {m.txid}
                                        </td>
                                        <td
                                            data-rotulo="Programa"
                                            className="py-2"
                                        >
                                            {m.programa}
                                        </td>
                                        <td
                                            data-rotulo="Pessoa"
                                            className="py-2"
                                        >
                                            {m.pessoa}
                                        </td>
                                        <td data-rotulo="Mês" className="py-2">
                                            {m.referencia}
                                        </td>
                                        <td
                                            data-rotulo="Valor"
                                            className="py-2"
                                        >
                                            {formatarReais(m.valor_centavos)}
                                        </td>
                                        <td
                                            data-rotulo="Vence"
                                            className="py-2"
                                        >
                                            {m.vence_em}
                                        </td>
                                        <td
                                            data-rotulo="Confirmar"
                                            className="py-2"
                                        >
                                            {/* A data do Pix (padrão: hoje) e o botão. */}
                                            <Form
                                                action={`/comando-geral/mensalidades/${m.id}/confirmar`}
                                                method="post"
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                                className="flex flex-wrap items-center gap-2"
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <label
                                                            className="sr-only"
                                                            htmlFor={`pago-${m.id}`}
                                                        >
                                                            Data do Pix
                                                        </label>
                                                        <input
                                                            id={`pago-${m.id}`}
                                                            type="date"
                                                            name="pago_em"
                                                            defaultValue={hoje()}
                                                            max={hoje()}
                                                            className="min-h-11 rounded-md border bg-transparent px-2"
                                                        />
                                                        <Button
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                            className="min-h-11"
                                                        >
                                                            Confirmar
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>

                <section
                    aria-labelledby="t-assinaturas"
                    className="flex flex-col gap-3"
                >
                    <h2 id="t-assinaturas" className="font-medium">
                        Assinaturas ({assinaturas.length})
                    </h2>
                    {assinaturas.length > 0 && (
                        <table className="tabela-cartoes w-full text-sm">
                            <thead>
                                <tr className="text-left">
                                    <th scope="col" className="py-2">
                                        Programa
                                    </th>
                                    <th scope="col" className="py-2">
                                        Pessoa
                                    </th>
                                    <th scope="col" className="py-2">
                                        Acesso
                                    </th>
                                    <th scope="col" className="py-2">
                                        Pago até
                                    </th>
                                    <th scope="col" className="py-2">
                                        <span className="sr-only">
                                            Cancelar
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {assinaturas.map((a) => (
                                    <tr key={a.id} className="border-t">
                                        <td
                                            data-rotulo="Programa"
                                            className="py-2"
                                        >
                                            {a.programa}
                                        </td>
                                        <td
                                            data-rotulo="Pessoa"
                                            className="py-2"
                                        >
                                            {a.pessoa}
                                        </td>
                                        <td
                                            data-rotulo="Acesso"
                                            className="py-2"
                                        >
                                            {a.acesso.rotulo}
                                        </td>
                                        <td
                                            data-rotulo="Pago até"
                                            className="py-2"
                                        >
                                            {a.pago_ate ?? '—'}
                                        </td>
                                        <td className="py-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="min-h-11"
                                                onClick={() =>
                                                    window.confirm(
                                                        'Cancelar esta assinatura? O histórico fica guardado.',
                                                    ) &&
                                                    router.post(
                                                        `/comando-geral/mensalidades/assinaturas/${a.id}/cancelar`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </>
    );
}

Mensalidades.layout = {
    breadcrumbs: [
        { title: 'Mensalidades', href: '/comando-geral/mensalidades' },
    ],
};
