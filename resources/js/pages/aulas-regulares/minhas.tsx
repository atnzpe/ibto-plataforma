/**
 * MINHAS AULAS (#80): cada programa que a pessoa assina, com a situação do
 * acesso (em dia, carência, travado, aguardando), até quando está pago, a turma,
 * o link da sala (só com o acesso liberado) e o Pix da mensalidade em aberto.
 * Cancelar a assinatura pede confirmação.
 */
import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import PixCopiaECola from '@/components/cursos/pix-copia-e-cola';
import type { PixDaMatricula } from '@/components/cursos/pix-copia-e-cola';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';

/** Uma assinatura da pessoa. */
type MinhaAssinatura = {
    id: number;
    programa: string;
    programa_id: number;
    acesso: { acesso: string; rotulo: string };
    pago_ate: string | null;
    turma: string | null;
    local: string | null;
    link_sala: string | null;
    em_aberto: {
        id: number;
        referencia: string;
        valor_centavos: number;
        vence_em: string;
        // A página de pagamento única (/pagar/<código>), para abrir ou mandar por WhatsApp.
        link_pagamento: string;
        pix: (PixDaMatricula & { favorecido: string; chave: string }) | null;
    } | null;
};

// Cor de cada situação do acesso (o texto também diz, para quem não distingue cores).
const COR: Record<string, string> = {
    liberado: 'border-green-700 bg-green-50 text-green-900',
    carencia: 'border-amber-700 bg-amber-50 text-amber-900',
    travado: 'border-red-700 bg-red-50 text-red-900',
    aguardando: 'border-amber-700 bg-amber-50 text-amber-900',
};

/**
 * @param assinaturas As assinaturas (não canceladas).
 * @returns A tela.
 */
export default function MinhasAulas({
    assinaturas,
}: {
    assinaturas: MinhaAssinatura[];
}) {
    const { sucesso } = usePage<{ sucesso?: string | null }>().props;
    // Atualiza a tela sozinha a cada 15 s enquanto houver mensalidade em aberto (quando o
    // IBTO confirma o Pix, a situação muda sem a pessoa recarregar). Sem nada em aberto: parado.
    usePoll(
        15000,
        {},
        { autoStart: assinaturas.some((a) => a.em_aberto !== null) },
    );

    return (
        <>
            <Head title="Minhas aulas" />
            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    Minhas aulas
                </h1>
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {assinaturas.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Você ainda não assina nenhum programa.{' '}
                        <Link
                            href="/aulas"
                            className="underline underline-offset-4"
                        >
                            Ver as aulas regulares
                        </Link>
                    </p>
                )}

                {assinaturas.map((a) => (
                    <section
                        key={a.id}
                        aria-labelledby={`t-ass-${a.id}`}
                        className="flex flex-col gap-3 rounded-md border p-4 text-sm"
                    >
                        <h2
                            id={`t-ass-${a.id}`}
                            className="text-base font-semibold"
                        >
                            {a.programa}
                        </h2>
                        <p
                            role="status"
                            className={`rounded-md border p-2 font-medium ${COR[a.acesso.acesso] ?? ''}`}
                        >
                            {a.acesso.rotulo}
                            {a.pago_ate ? ` · pago até ${a.pago_ate}` : ''}
                        </p>
                        {a.turma && (
                            <p>
                                Turma: {a.turma}
                                {a.local ? ` · ${a.local}` : ''}
                            </p>
                        )}
                        {/* Sala online: só com o acesso liberado (em dia ou na carência). */}
                        {a.link_sala && (
                            <a
                                href={a.link_sala}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex min-h-11 items-center self-start rounded-md bg-[#6b5d3a] px-4 font-medium text-white"
                            >
                                Entrar na sala ao vivo
                            </a>
                        )}

                        {/* A mensalidade em aberto, com o Pix copia e cola. */}
                        {a.em_aberto && (
                            <div className="flex flex-col gap-2 rounded-md border border-dashed p-3">
                                <p className="font-medium">
                                    Mensalidade {a.em_aberto.referencia}:{' '}
                                    {formatarReais(a.em_aberto.valor_centavos)}{' '}
                                    · vence {a.em_aberto.vence_em}
                                </p>
                                {a.em_aberto.pix ? (
                                    <PixCopiaECola
                                        pix={a.em_aberto.pix}
                                        favorecido={a.em_aberto.pix.favorecido}
                                        chave={a.em_aberto.pix.chave}
                                    />
                                ) : (
                                    <p className="text-muted-foreground">
                                        O IBTO ainda não configurou a chave Pix.
                                        Fale com o suporte.
                                    </p>
                                )}
                                <p className="text-muted-foreground">
                                    Depois do Pix, o IBTO confere e confirma; a
                                    situação acima muda sozinha.
                                </p>
                                {/* A página de pagamento desta mensalidade (o mesmo link do e-mail de aviso). */}
                                <Link
                                    href={a.em_aberto.link_pagamento}
                                    className="inline-flex min-h-11 items-center self-start underline underline-offset-4"
                                >
                                    Abrir a página de pagamento
                                </Link>
                            </div>
                        )}

                        <Button
                            variant="outline"
                            size="sm"
                            className="min-h-11 self-start"
                            onClick={() =>
                                window.confirm(
                                    `Cancelar a assinatura de ${a.programa}?`,
                                ) &&
                                router.post(
                                    `/minhas-aulas/${a.id}/cancelar`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Cancelar assinatura
                        </Button>
                    </section>
                ))}
            </div>
        </>
    );
}

MinhasAulas.layout = {
    breadcrumbs: [{ title: 'Minhas aulas', href: '/minhas-aulas' }],
};
