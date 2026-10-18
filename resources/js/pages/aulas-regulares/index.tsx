/**
 * AULAS REGULARES — LISTA (#80): os programas abertos (Krav Maga, Kapap...),
 * com a mensalidade, as turmas e as vagas. Atalho para "Minhas aulas".
 * Cartões: 1 coluna no celular, 2 no tablet, 3 na tela larga.
 */
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';

/** Um programa na vitrine. */
export type ProgramaCartao = {
    id: number;
    titulo: string;
    modalidade: string;
    instrutor: string | null;
    valor_mensal_centavos: number;
    so_filiados: boolean;
    vagas_restantes: number | null;
    turmas: {
        id: number;
        nome: string;
        horario: string;
        local: string | null;
    }[];
};

/**
 * @param programas Os programas abertos.
 * @returns A lista.
 */
export default function AulasRegulares({
    programas,
}: {
    programas: ProgramaCartao[];
}) {
    return (
        <>
            <Head title="Aulas regulares" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Aulas regulares
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Aulas contínuas com mensalidade, presenciais ou
                            online. Sem certificado: a frequência vai para o seu
                            dossiê.
                        </p>
                    </div>
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href="/minhas-aulas">Minhas aulas</Link>
                    </Button>
                </div>

                {programas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nenhum programa aberto no momento.
                    </p>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {programas.map((p) => (
                            <li
                                key={p.id}
                                className="flex flex-col gap-2 rounded-md border p-4 text-sm"
                            >
                                <p className="text-base font-semibold">
                                    {p.titulo}
                                </p>
                                <p className="text-muted-foreground">
                                    {p.modalidade}
                                    {p.instrutor ? ` · ${p.instrutor}` : ''}
                                </p>
                                <p className="font-medium">
                                    {formatarReais(p.valor_mensal_centavos)} por
                                    mês
                                </p>
                                {p.turmas.length > 0 && (
                                    <p>
                                        {p.turmas
                                            .map((t) => t.horario)
                                            .join(' · ')}
                                    </p>
                                )}
                                {p.so_filiados && (
                                    <p className="text-amber-800 dark:text-amber-300">
                                        Exclusivo para filiados
                                    </p>
                                )}
                                {p.vagas_restantes !== null && (
                                    <p>
                                        {p.vagas_restantes > 0
                                            ? `${p.vagas_restantes} vaga(s)`
                                            : 'Vagas esgotadas'}
                                    </p>
                                )}
                                <Link
                                    href={`/aulas/${p.id}`}
                                    className="mt-1 inline-flex min-h-11 items-center self-start rounded-md border px-4 font-medium"
                                >
                                    Ver e assinar
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

AulasRegulares.layout = {
    breadcrumbs: [{ title: 'Aulas regulares', href: '/aulas' }],
};
