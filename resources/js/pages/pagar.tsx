/**
 * PÁGINA DE PAGAMENTO ÚNICA /pagar/<código> (17/10/2026): o "checkout" de
 * qualquer cobrança (inscrição, fotos ou mensalidade). À esquerda (ou em cima,
 * no celular) o resumo; ao lado, o Pix com QR, "Copiar código" e o identificador
 * do extrato. Enquanto aguarda, a página se atualiza sozinha a cada 10 s e vira
 * "Pagamento confirmado ✓" quando o IBTO confirma.
 */
import { Head, Link, usePoll } from '@inertiajs/react';
import PixCopiaECola from '@/components/cursos/pix-copia-e-cola';
import type { PixDaMatricula } from '@/components/cursos/pix-copia-e-cola';
import { formatarReais } from '@/lib/dinheiro';

/** A cobrança mostrada. */
type Cobranca = {
    tipo: string;
    descricao: string;
    valor_centavos: number;
    vence_em: string | null;
    situacao: 'aguardando' | 'pago' | 'cancelado';
    pix: (PixDaMatricula & { favorecido: string; chave: string }) | null;
    voltar: { rotulo: string; href: string };
};

// O texto e a cor de cada situação (o texto diz, além da cor).
const SITUACAO: Record<
    Cobranca['situacao'],
    { texto: string; classe: string }
> = {
    aguardando: {
        texto: 'Aguardando o pagamento',
        classe: 'border-amber-700 bg-amber-50 text-amber-900',
    },
    pago: {
        texto: 'Pagamento confirmado ✓',
        classe: 'border-green-700 bg-green-50 text-green-900',
    },
    cancelado: {
        texto: 'Cobrança cancelada',
        classe: 'border-red-700 bg-red-50 text-red-900',
    },
};

/**
 * @param cobranca A cobrança.
 * @returns A página de pagamento.
 */
export default function Pagar({ cobranca }: { cobranca: Cobranca }) {
    // Atualiza sozinha enquanto aguarda (para quando o IBTO confirmar). Paga ou cancelada: parada.
    usePoll(10000, {}, { autoStart: cobranca.situacao === 'aguardando' });
    const situacao = SITUACAO[cobranca.situacao];

    return (
        <>
            <Head title={`Pagamento · ${cobranca.descricao}`} />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 lg:grid-cols-[1fr_1.2fr]">
                {/* Resumo da cobrança. */}
                <section
                    aria-labelledby="t-resumo"
                    className="flex flex-col gap-4 rounded-xl border p-5"
                >
                    <h1
                        id="t-resumo"
                        className="text-xl font-semibold tracking-tight"
                    >
                        Pagamento
                    </h1>
                    <p
                        role="status"
                        aria-live="polite"
                        className={`rounded-md border p-3 font-semibold ${situacao.classe}`}
                    >
                        {situacao.texto}
                    </p>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">O quê</dt>
                        <dd>{cobranca.tipo}</dd>
                        <dt className="text-muted-foreground">Item</dt>
                        <dd className="[overflow-wrap:anywhere]">
                            {cobranca.descricao}
                        </dd>
                        {cobranca.vence_em && (
                            <>
                                <dt className="text-muted-foreground">
                                    Vencimento
                                </dt>
                                <dd>{cobranca.vence_em}</dd>
                            </>
                        )}
                    </dl>
                    <p className="border-t pt-4 text-2xl font-bold">
                        {formatarReais(cobranca.valor_centavos)}
                    </p>
                    <Link
                        href={cobranca.voltar.href}
                        className="inline-flex min-h-11 items-center self-start underline underline-offset-4"
                    >
                        {cobranca.voltar.rotulo}
                    </Link>
                </section>

                {/* O Pix (só enquanto aguarda). */}
                <section
                    aria-labelledby="t-pix"
                    data-tour="pix"
                    className="flex flex-col gap-4 rounded-xl border p-5"
                >
                    <h2 id="t-pix" className="text-lg font-semibold">
                        Pagar com Pix
                    </h2>
                    {cobranca.situacao === 'aguardando' && cobranca.pix && (
                        <>
                            <PixCopiaECola
                                pix={cobranca.pix}
                                favorecido={cobranca.pix.favorecido}
                                chave={cobranca.pix.chave}
                            />
                            <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                                <li>
                                    Abra o app do seu banco e escolha Pix →
                                    "Copia e cola" (ou leia o QR).
                                </li>
                                <li>
                                    Cole o código: o valor e o favorecido já vêm
                                    preenchidos.
                                </li>
                                <li>
                                    Pague. Quando o IBTO conferir, esta página
                                    muda sozinha para "Pagamento confirmado".
                                </li>
                            </ol>
                        </>
                    )}
                    {cobranca.situacao === 'aguardando' && !cobranca.pix && (
                        <p className="text-sm">
                            O IBTO ainda não configurou a chave Pix. Fale com o
                            suporte.
                        </p>
                    )}
                    {cobranca.situacao === 'pago' && (
                        <p className="text-sm">Tudo certo. Obrigado!</p>
                    )}
                    {cobranca.situacao === 'cancelado' && (
                        <p className="text-sm">
                            Esta cobrança foi cancelada e não precisa ser paga.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

Pagar.layout = {
    breadcrumbs: [{ title: 'Pagamento', href: '#' }],
};
