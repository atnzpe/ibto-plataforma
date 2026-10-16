/**
 * SOLICITAÇÕES DE CADASTRO (#84; o IBTO é um grupo fechado, só de indicados).
 *
 * Quem pediu acesso e está "em análise", do pedido mais antigo para o mais
 * novo. Para cada um: nome, e-mail (e se já confirmou), equipe e quem indicou,
 * com os botões APROVAR e RECUSAR (a recusa pede o motivo, que vai no e-mail).
 * O admin de equipe vê só os pedidos da própria equipe (filtro no servidor).
 * Celular: cartões; a partir de 1024 px: tabela (regra da casa, sem rolagem lateral).
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

// Um pedido de acesso, como o servidor manda.
type Solicitacao = {
    id: number;
    nome: string;
    email: string;
    equipe: string | null;
    indicado_por: string | null;
    // true = a pessoa já clicou no link de confirmação do e-mail.
    email_confirmado: boolean;
    // Ex.: '17/10/2026 09:30'
    pedido_em: string | null;
};

/**
 * A tela das solicitações.
 *
 * @param solicitacoes Os pedidos em análise que esta pessoa pode ver.
 * @returns A lista com os botões de aprovar e recusar.
 */
export default function Solicitacoes({
    solicitacoes,
}: {
    solicitacoes: Solicitacao[];
}) {
    // A mensagem de sucesso da última ação (ex.: "Acesso de Ana aprovado.").
    const { sucesso } = usePage<{ sucesso: string | null }>().props;
    // O pedido que está com a caixa de "motivo da recusa" aberta (null = nenhum).
    const [recusando, setRecusando] = useState<number | null>(null);
    const [motivo, setMotivo] = useState('');
    // O erro de validação do motivo (vindo do servidor), se houver.
    const [erroMotivo, setErroMotivo] = useState<string | null>(null);

    /** Aprova o pedido (POST), mantendo a rolagem da tela. */
    const aprovar = (id: number) => {
        router.post(
            `/admin/solicitacoes/${id}/aprovar`,
            {},
            { preserveScroll: true },
        );
    };

    /** Envia a recusa com o motivo; fecha a caixa se deu certo. */
    const recusar = (id: number) => {
        router.post(
            `/admin/solicitacoes/${id}/recusar`,
            { motivo },
            {
                preserveScroll: true,
                onError: (erros) =>
                    setErroMotivo(erros.motivo ?? 'Confira o motivo.'),
                onSuccess: () => {
                    setRecusando(null);
                    setMotivo('');
                    setErroMotivo(null);
                },
            },
        );
    };

    return (
        <>
            <Head title="Solicitações de cadastro" />
            <div className="px-safe-4 flex flex-col gap-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Solicitações de cadastro
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        O IBTO é um grupo fechado: confira se a pessoa foi mesmo
                        indicada antes de aprovar. Aprovada, ela vira Aluno e
                        recebe um e-mail.
                    </p>
                </div>

                {/* Mensagem da última ação (role="status" para o leitor de tela). */}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-border bg-accent px-4 py-3 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* Nenhum pedido: aviso calmo. */}
                {solicitacoes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nenhum pedido aguardando análise.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {solicitacoes.map((pedido) => (
                            <li
                                key={pedido.id}
                                className="rounded-xl border border-border p-4"
                            >
                                {/* No celular, os dados empilhados; na tela larga, lado a lado com os botões. */}
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <dl className="grid gap-1 text-sm">
                                        <div>
                                            <dt className="sr-only">Nome</dt>
                                            <dd className="font-medium">
                                                {pedido.nome}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="sr-only">E-mail</dt>
                                            <dd className="break-all text-muted-foreground">
                                                {pedido.email}{' '}
                                                {pedido.email_confirmado
                                                    ? '(confirmado)'
                                                    : '(ainda não confirmado)'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="inline font-medium">
                                                Equipe:{' '}
                                            </dt>
                                            <dd className="inline">
                                                {pedido.equipe ??
                                                    'não informada'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="inline font-medium">
                                                Indicado por:{' '}
                                            </dt>
                                            <dd className="inline">
                                                {pedido.indicado_por ??
                                                    'não informado'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="inline font-medium">
                                                Pedido em:{' '}
                                            </dt>
                                            <dd className="inline">
                                                {pedido.pedido_em}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            className="min-h-11"
                                            onClick={() => aprovar(pedido.id)}
                                        >
                                            Aprovar
                                            <span className="sr-only">
                                                {' '}
                                                o acesso de {pedido.nome}
                                            </span>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                            aria-expanded={
                                                recusando === pedido.id
                                            }
                                            onClick={() => {
                                                setRecusando(
                                                    recusando === pedido.id
                                                        ? null
                                                        : pedido.id,
                                                );
                                                setErroMotivo(null);
                                            }}
                                        >
                                            Recusar
                                            <span className="sr-only">
                                                {' '}
                                                o pedido de {pedido.nome}
                                            </span>
                                        </Button>
                                    </div>
                                </div>

                                {/* Caixa do motivo da recusa (o texto vai no e-mail para a pessoa). */}
                                {recusando === pedido.id && (
                                    <div className="mt-3 flex flex-col gap-2">
                                        <label
                                            htmlFor={`motivo-${pedido.id}`}
                                            className="text-sm font-medium"
                                        >
                                            Motivo da recusa (vai no e-mail para
                                            a pessoa)
                                        </label>
                                        <textarea
                                            id={`motivo-${pedido.id}`}
                                            rows={2}
                                            maxLength={500}
                                            value={motivo}
                                            onChange={(evento) =>
                                                setMotivo(evento.target.value)
                                            }
                                            placeholder="Ex.: A equipe não confirmou a indicação."
                                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                                        />
                                        {erroMotivo && (
                                            <p
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {erroMotivo}
                                            </p>
                                        )}
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            className="min-h-11 w-fit"
                                            onClick={() => recusar(pedido.id)}
                                        >
                                            Confirmar a recusa
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

// O "caminho" no topo (o layout com o menu lateral vem do app.tsx).
Solicitacoes.layout = {
    breadcrumbs: [
        { title: 'Solicitações de cadastro', href: '/admin/solicitacoes' },
    ],
};
