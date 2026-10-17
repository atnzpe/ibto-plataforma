/**
 * Tela de LISTA dos cupons de desconto (#89), no Financeiro. Mostra o desconto,
 * a validade, os usos (e o limite), o total já descontado e a situação. Filtro
 * por situação. Não existe apagar: desativar é pela edição. Responsiva: cartões
 * no celular e na tela larga (cabe sem rolagem lateral).
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';
import { create, edit, index } from '@/routes/financeiro/cupons';
import { index as livroCaixa } from '@/routes/financeiro/lancamentos';

/** Um cupom como a lista mostra. */
type CupomLista = {
    id: number;
    codigo: string;
    // Ex.: '10%' ou 'R$ 50,00'.
    desconto: string;
    // Ex.: 'até 30/11/2026 23:59'; null = sem prazo.
    validade: string | null;
    usos: number;
    limite_total: number | null;
    total_desconto_centavos: number;
    // 'Ativo', 'Vencido', 'Esgotado', 'Desativado' ou 'Ainda não começou'.
    situacao: string;
};

// As situações do filtro, na ordem em que aparecem.
const SITUACOES = [
    'Ativo',
    'Ainda não começou',
    'Vencido',
    'Esgotado',
    'Desativado',
];

/**
 * @param cupons Os cupons (do mais novo ao mais antigo), já filtrados.
 * @param filtro_situacao A situação filtrada agora ('' = todas).
 * @returns A tela da lista.
 */
export default function CuponsIndex({
    cupons,
    filtro_situacao,
}: {
    cupons: CupomLista[];
    filtro_situacao: string;
}) {
    // Mensagem da última ação, ou null.
    const { sucesso } = usePage().props;

    return (
        <>
            <Head title="Cupons de desconto" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Cupons de desconto
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            O aluno digita o código na inscrição e o Pix já sai
                            com o desconto. O desconto entra antes da divisão
                            entre o IBTO e o instrutor.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={create()}>Novo cupom</Link>
                    </Button>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}

                {/* Filtro por situação: troca a lista sem recarregar a página inteira. */}
                <div className="grid gap-1 sm:max-w-xs">
                    <label
                        htmlFor="filtro-situacao"
                        className="text-sm font-medium"
                    >
                        Situação
                    </label>
                    <select
                        id="filtro-situacao"
                        value={filtro_situacao}
                        onChange={(evento) =>
                            router.get(
                                index().url,
                                evento.target.value
                                    ? { situacao: evento.target.value }
                                    : {},
                                { preserveState: true },
                            )
                        }
                        className="h-11 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Todas</option>
                        {SITUACOES.map((situacao) => (
                            <option key={situacao} value={situacao}>
                                {situacao}
                            </option>
                        ))}
                    </select>
                </div>

                {cupons.length === 0 && (
                    <p className="rounded-md border border-border p-6 text-center text-sm text-muted-foreground">
                        Nenhum cupom por aqui.
                    </p>
                )}

                <ul
                    aria-label="Lista dos cupons"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    {cupons.map((cupom) => (
                        <li
                            key={cupom.id}
                            className="flex flex-col gap-2 rounded-md border border-border p-4 text-sm"
                        >
                            <span className="flex flex-wrap items-center justify-between gap-2">
                                <strong className="font-mono text-base">
                                    {cupom.codigo}
                                </strong>
                                {/* Situação escrita (não só cor). */}
                                <span className="rounded bg-muted px-1.5 py-0.5 text-xs font-medium">
                                    {cupom.situacao}
                                </span>
                            </span>
                            <span>Desconto: {cupom.desconto}</span>
                            <span className="text-muted-foreground">
                                {cupom.validade ?? 'Sem prazo'}
                            </span>
                            <span>
                                Usos: {cupom.usos}
                                {cupom.limite_total !== null
                                    ? ` de ${cupom.limite_total}`
                                    : ''}{' '}
                                · Descontado:{' '}
                                {formatarReais(cupom.total_desconto_centavos)}
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                className="mt-1 min-h-11"
                            >
                                <Link
                                    href={edit(cupom.id)}
                                    aria-label={`Editar o cupom ${cupom.codigo}`}
                                >
                                    Editar
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
CuponsIndex.layout = {
    breadcrumbs: [
        { title: 'Financeiro', href: livroCaixa() },
        { title: 'Cupons', href: index() },
    ],
};
