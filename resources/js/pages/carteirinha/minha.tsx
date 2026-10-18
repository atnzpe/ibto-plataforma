/**
 * MINHA CARTEIRINHA (#46): o cartão do filiado para imprimir ou guardar em PDF.
 *
 * Frente única no tamanho de cartão (85,6 × 54 mm, o mesmo do RG e do cartão de
 * banco): foto 3x4, nome, registro, nível, equipe, validade e o QR code que abre
 * a validação pública. Página limpa (sem menu): o botão "Imprimir" some na
 * impressão. Sem foto: um aviso para enviar a 3x4 em Configurações.
 * PASSAPORTE (#81): mostra o sinal atual do semáforo e o botão para trocar o
 * código do QR, caso a foto da carteirinha tenha vazado.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { TourGuiado } from '@/components/tour-guiado';

/** Os dados da carteirinha da própria pessoa. */
type MinhaCarteirinha = {
    nome: string;
    // O semáforo do passaporte (#81). Ex.: 'Liberado com ressalva' e 'Anuidade pendente'.
    sinal: 'verde' | 'amarelo' | 'vermelho';
    sinal_rotulo: string;
    sinal_motivo: string | null;
    registro: string;
    situacao: 'ativa' | 'pendente' | 'inativa';
    situacao_rotulo: string;
    validade: string | null;
    categoria: string | null;
    nivel: string | null;
    equipe: string | null;
    foto_url: string | null;
    codigo: string;
    endereco_validacao: string;
    qr_svg: string;
};

/**
 * @param carteirinha Os dados da carteirinha.
 * @returns A página da carteirinha.
 */
export default function MinhaCarteirinha({
    carteirinha,
}: {
    carteirinha: MinhaCarteirinha;
}) {
    // O aviso de sucesso que o servidor mandou (ou null).
    const { sucesso } = usePage<{ sucesso?: string | null }>().props;

    /**
     * Troca o código do QR (#81), depois de confirmar: o QR impresso antes deixa de valer.
     */
    function trocarCodigo() {
        // Sem confirmação, não troca nada.
        if (
            !window.confirm(
                'Trocar o código do QR? A carteirinha impressa antes deixa de valer e você precisa imprimir de novo.',
            )
        ) {
            return;
        }
        router.post(
            '/minha-carteirinha/trocar-codigo',
            {},
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Minha carteirinha" />
            <main className="mx-auto flex max-w-3xl flex-col items-center gap-6 bg-white p-6 text-black print:p-0">
                <div className="flex w-full flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link
                        href="/dashboard"
                        className="text-sm underline underline-offset-4"
                    >
                        Voltar à plataforma
                    </Link>
                    {/* O "?" do tour (17/10/2026): esta página não tem menu, então o botão fica aqui. */}
                    <TourGuiado />
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="min-h-11 rounded-md border border-black/30 px-4 text-sm font-medium"
                    >
                        Imprimir ou salvar em PDF
                    </button>
                </div>

                {/* Aviso de sucesso (ex.: depois de trocar o código do QR). */}
                {sucesso && (
                    <p
                        role="status"
                        className="w-full rounded-md border border-green-700 bg-green-50 p-3 text-sm text-green-900 print:hidden"
                    >
                        {sucesso}
                    </p>
                )}

                {/* PASSAPORTE (#81): o sinal que o instrutor vai ver ao ler o QR (some na impressão). */}
                <p
                    role="note"
                    className="w-full rounded-md border border-black/20 p-3 text-sm print:hidden"
                >
                    Sinal do passaporte agora:{' '}
                    <strong>{carteirinha.sinal_rotulo}</strong>
                    {carteirinha.sinal_motivo
                        ? ` (${carteirinha.sinal_motivo})`
                        : ''}
                    .
                </p>

                {/* Sem foto 3x4: aviso (some na impressão). */}
                {!carteirinha.foto_url && (
                    <p
                        role="note"
                        className="w-full rounded-md border border-amber-700 bg-amber-50 p-3 text-sm text-amber-900 print:hidden"
                    >
                        Sua carteirinha está sem foto. Envie a foto 3x4 em
                        Configurações → Perfil.
                    </p>
                )}

                {/* O cartão: 85,6 × 54 mm na impressão. */}
                <section
                    aria-label="Carteirinha do filiado"
                    className="flex w-full max-w-[540px] gap-3 overflow-hidden rounded-xl border-2 border-[#6b5d3a] bg-[#14161a] p-4 text-[#f3f1ec] print:h-[54mm] print:w-[85.6mm] print:max-w-none print:gap-2 print:p-2"
                >
                    <div className="flex h-36 w-28 shrink-0 items-center justify-center overflow-hidden rounded-md bg-neutral-700 print:h-[38mm] print:w-[28mm]">
                        {carteirinha.foto_url ? (
                            <img
                                src={carteirinha.foto_url}
                                alt="Foto 3x4"
                                className="size-full object-cover"
                            />
                        ) : (
                            <span className="text-xs text-neutral-300">
                                sem foto
                            </span>
                        )}
                    </div>
                    <div className="flex min-w-0 flex-1 flex-col justify-between gap-1">
                        <div className="flex items-center gap-2">
                            <img
                                src="/images/ibto-emblema.png"
                                alt=""
                                className="size-8 print:size-6"
                            />
                            <p className="text-[10px] leading-tight tracking-wide uppercase print:text-[6pt]">
                                Instituto Brasileiro de Treinamento Operacional
                            </p>
                        </div>
                        <div>
                            <p className="font-semibold [overflow-wrap:anywhere] print:text-[8pt]">
                                {carteirinha.nome}
                            </p>
                            <p className="font-mono text-sm print:text-[7pt]">
                                {carteirinha.registro}
                            </p>
                            <p className="text-xs text-[#c9bd94] print:text-[6.5pt]">
                                {/* Categoria · nível; quando os dois têm o mesmo nome (ex.: "Praticante"), aparece uma vez só. */}
                                {[
                                    ...new Set(
                                        [
                                            carteirinha.categoria,
                                            carteirinha.nivel,
                                        ].filter(Boolean),
                                    ),
                                ].join(' · ')}
                            </p>
                            {carteirinha.equipe && (
                                <p className="text-xs print:text-[6.5pt]">
                                    {carteirinha.equipe}
                                </p>
                            )}
                        </div>
                        <p className="text-xs print:text-[6.5pt]">
                            {carteirinha.situacao_rotulo}
                            {carteirinha.validade
                                ? ` · válida até ${carteirinha.validade}`
                                : ''}
                        </p>
                    </div>
                    {/* QR code: abre a validação pública (fundo branco para a câmera ler). */}
                    <div className="flex shrink-0 flex-col items-center gap-1">
                        <div
                            className="rounded bg-white p-1 [&>svg]:size-24 print:[&>svg]:size-[22mm]"
                            dangerouslySetInnerHTML={{
                                __html: carteirinha.qr_svg,
                            }}
                        />
                        <p className="font-mono text-[9px] print:text-[5pt]">
                            {carteirinha.codigo}
                        </p>
                    </div>
                </section>

                <p className="text-center text-xs text-black/60 print:hidden">
                    Quem ler o QR code vê a validação em{' '}
                    {carteirinha.endereco_validacao}, só com o necessário para
                    conferir.
                </p>

                {/* Passaporte (#81): se a foto do QR vazou, sorteia um código novo (o antigo para de valer). */}
                <button
                    type="button"
                    onClick={trocarCodigo}
                    className="min-h-11 rounded-md border border-red-800/40 px-4 text-sm font-medium text-red-900 print:hidden"
                >
                    Trocar código do QR (se ele vazou)
                </button>
            </main>
        </>
    );
}
