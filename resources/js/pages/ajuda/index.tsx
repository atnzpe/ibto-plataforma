/**
 * PÁGINA AJUDA (#68, parte 2): o manual de instruções de cada perfil.
 *
 * - Abas com os manuais liberados para a pessoa (o servidor decide quais).
 * - Busca dentro do manual (sem diferença de acento e maiúscula).
 * - Cada seção abre e fecha (<details>, acessível pelo teclado) e tem o botão
 *   "Mostrar na tela", que abre a tela já com o tour guiado.
 * - "Imprimir ou salvar em PDF": o navegador gera o PDF do manual aberto.
 */
import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type IdDoManual, manuaisVisiveis, secoesDaBusca } from '@/lib/manuais';

/**
 * A página Ajuda.
 *
 * @param manuais Os manuais liberados para a pessoa. Ex.: ['aluno', 'instrutor']
 * @returns As abas, a busca e as seções do manual escolhido.
 */
export default function Ajuda({ manuais }: { manuais: IdDoManual[] }) {
    // Os manuais que a pessoa vê, na ordem da página.
    const visiveis = manuaisVisiveis(manuais);
    // O manual aberto (começa pelo primeiro) e o texto da busca.
    const [aberto, setAberto] = useState<IdDoManual>(
        visiveis[0]?.id ?? 'aluno',
    );
    const [busca, setBusca] = useState('');
    const manual = visiveis.find((item) => item.id === aberto) ?? visiveis[0];
    const secoes = manual ? secoesDaBusca(manual, busca) : [];

    // Na hora de imprimir (ou salvar em PDF), abre todas as seções; depois,
    // volta como estava. Sem isso, o PDF sairia só com os títulos.
    useEffect(() => {
        // As seções que estavam fechadas antes de imprimir.
        let fechadas: HTMLDetailsElement[] = [];
        const antes = () => {
            fechadas = Array.from(
                document.querySelectorAll<HTMLDetailsElement>(
                    'main details:not([open])',
                ),
            );
            fechadas.forEach((secao) => (secao.open = true));
        };
        const depois = () => fechadas.forEach((secao) => (secao.open = false));
        window.addEventListener('beforeprint', antes);
        window.addEventListener('afterprint', depois);

        // Saiu da página: tira os "ouvintes" da impressão.
        return () => {
            window.removeEventListener('beforeprint', antes);
            window.removeEventListener('afterprint', depois);
        };
    }, []);

    return (
        <>
            <Head title="Ajuda — manual de instruções" />
            <div className="px-safe-4 mx-auto flex w-full max-w-4xl flex-col gap-6 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Manual de instruções
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Passo a passo de cada tarefa, com o porquê das regras.
                        Em qualquer tela, o "?" do topo mostra o tour daquela
                        tela.
                    </p>
                </div>

                {/* Abas dos manuais (só aparecem com mais de um manual). role="tablist" para o leitor de tela. */}
                {visiveis.length > 1 && (
                    <div
                        role="tablist"
                        aria-label="Manuais"
                        className="flex flex-wrap gap-2 print:hidden"
                    >
                        {visiveis.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                role="tab"
                                aria-selected={item.id === aberto}
                                onClick={() => setAberto(item.id)}
                                className={`min-h-11 rounded-full border px-4 text-sm ${
                                    item.id === aberto
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border hover:bg-accent'
                                }`}
                            >
                                {item.titulo}
                            </button>
                        ))}
                    </div>
                )}

                {manual && (
                    <section
                        aria-labelledby="t-manual"
                        className="flex flex-col gap-4"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2
                                    id="t-manual"
                                    className="text-xl font-semibold"
                                >
                                    {manual.titulo}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {manual.publico}
                                </p>
                            </div>
                            {/* window.print: o navegador oferece "Salvar como PDF". */}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.print()}
                                className="min-h-11 print:hidden"
                            >
                                <Printer
                                    className="mr-2 size-4"
                                    aria-hidden="true"
                                />
                                Imprimir ou salvar em PDF
                            </Button>
                        </div>

                        <Input
                            type="search"
                            value={busca}
                            onChange={(evento) => setBusca(evento.target.value)}
                            placeholder="Buscar no manual. Ex.: certificado"
                            aria-label="Buscar no manual"
                            className="print:hidden"
                        />

                        {/* Nenhuma seção com o texto buscado: aviso calmo. */}
                        {secoes.length === 0 && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                Nada encontrado para "{busca}". Tente outra
                                palavra.
                            </p>
                        )}

                        {secoes.map((secao) => (
                            // <details>: abre e fecha pelo clique ou pelo teclado (Enter/Espaço), sem JavaScript.
                            // "open" na impressão: o PDF sai com todas as seções abertas.
                            <details
                                key={secao.id}
                                id={secao.id}
                                className="group rounded-xl border border-border p-4 print:break-inside-avoid"
                            >
                                <summary className="cursor-pointer list-none font-medium marker:hidden">
                                    <span
                                        className="mr-2 inline-block transition-transform group-open:rotate-90 motion-reduce:transition-none"
                                        aria-hidden="true"
                                    >
                                        ›
                                    </span>
                                    {secao.titulo}
                                </summary>
                                <div className="mt-3 flex flex-col gap-3 text-sm">
                                    <p className="text-muted-foreground">
                                        {secao.resumo}
                                    </p>
                                    <ol className="list-decimal space-y-1 pl-5">
                                        {secao.passos.map((passo) => (
                                            <li key={passo}>{passo}</li>
                                        ))}
                                    </ol>
                                    {secao.regras &&
                                        secao.regras.length > 0 && (
                                            <div>
                                                <p className="font-medium">
                                                    Regras e por quê
                                                </p>
                                                <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                                                    {secao.regras.map(
                                                        (regra) => (
                                                            <li key={regra}>
                                                                {regra}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </div>
                                        )}
                                    {/* "Mostrar na tela": abre a tela com ?tour=1, e o tour dela começa sozinho. */}
                                    {secao.tela && (
                                        <Link
                                            href={`${secao.tela.endereco}?tour=1`}
                                            className="inline-flex min-h-11 w-fit items-center rounded-full border border-border px-4 font-medium hover:bg-accent print:hidden"
                                        >
                                            Mostrar na tela: {secao.tela.nome}
                                        </Link>
                                    )}
                                </div>
                            </details>
                        ))}
                    </section>
                )}
            </div>
        </>
    );
}

// O layout com o menu lateral já vem do app.tsx (para toda tela com login); aqui só o
// "caminho" no topo. Antes a página desenhava o layout de novo, e o menu e o topo
// apareciam duplicados (corrigido em 17/10/2026).
Ajuda.layout = {
    breadcrumbs: [{ title: 'Ajuda', href: '/ajuda' }],
};
