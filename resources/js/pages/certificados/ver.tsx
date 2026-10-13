/**
 * CERTIFICADO para imprimir / salvar em PDF (#44): nome, curso, carga
 * horária, data de conclusão, instrutor e o CÓDIGO único com o endereço da
 * validação pública. Página "limpa" (sem menu), em A4 deitado. O botão
 * "Imprimir / Salvar em PDF" abre a impressão do navegador (lá se escolhe
 * "Salvar como PDF") e não sai no papel.
 * Visual: as cores do IBTO; quando houver um modelo oficial (ex.: Canva), o
 * desenho é ajustado aqui.
 */
import { Head } from '@inertiajs/react';
import { dataBr } from '@/pages/catalogo/index';

/**
 * @param certificado A "foto" do certificado (do dia da emissão).
 * @returns A página do certificado.
 */
export default function VerCertificado({
    certificado,
}: {
    certificado: {
        codigo: string;
        nome: string;
        curso: string;
        carga_horaria: number | null;
        instrutor: string | null;
        concluido_em: string;
        emitido_em: string;
        valido: boolean;
        validar_url: string;
    };
}) {
    return (
        <>
            <Head title={`Certificado ${certificado.codigo}`} />
            {/* Regras de impressão: A4 deitado, sem margem do navegador, sem o botão. */}
            <style>
                {
                    '@page { size: A4 landscape; margin: 0 } @media print { .nao-imprimir { display: none !important } body { background: #fff } }'
                }
            </style>

            <div className="flex min-h-svh flex-col items-center gap-4 bg-[#e9e2cc] p-4 print:bg-white print:p-0">
                {/* Ações (não saem no papel). */}
                <div className="nao-imprimir flex w-full max-w-[297mm] flex-wrap items-center justify-between gap-2">
                    {!certificado.valido && (
                        <p
                            role="alert"
                            className="rounded-md bg-red-100 px-3 py-2 text-sm font-medium text-red-900"
                        >
                            Este certificado foi CANCELADO e não vale mais.
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="ml-auto inline-flex min-h-11 items-center rounded-xl bg-[#6b5d3a] px-5 font-semibold text-white"
                    >
                        Imprimir / Salvar em PDF
                    </button>
                </div>

                {/* A folha: proporção de um A4 deitado (297 × 210 mm). */}
                <article
                    className="relative flex aspect-[297/210] w-full max-w-[297mm] flex-col items-center justify-between overflow-hidden border-[10px] border-double border-[#6b5d3a] bg-[#fdfbf5] p-[6%] text-center text-[#1c1b17] print:max-w-none print:border-[8px]"
                    aria-label="Certificado"
                >
                    <header className="flex flex-col items-center gap-1">
                        <span className="font-[Audiowide,sans-serif] text-[clamp(1.5rem,5vw,3rem)] leading-none text-[#6b5d3a]">
                            IBTO
                        </span>
                        <span className="text-[clamp(0.6rem,1.4vw,0.9rem)] tracking-[0.2em] uppercase">
                            Instituto Brasileiro de Treinamento Operacional
                        </span>
                    </header>

                    <div className="flex flex-col items-center gap-[2%]">
                        <h1 className="font-[Audiowide,sans-serif] text-[clamp(1.25rem,4vw,2.5rem)] font-normal tracking-wide uppercase">
                            Certificado
                        </h1>
                        <p className="max-w-[85%] text-[clamp(0.75rem,1.8vw,1.15rem)] leading-relaxed">
                            Certificamos que{' '}
                            <strong className="text-[1.25em]">
                                {certificado.nome}
                            </strong>{' '}
                            concluiu o curso{' '}
                            <strong>{certificado.curso}</strong>
                            {certificado.carga_horaria
                                ? `, com carga horária de ${certificado.carga_horaria} horas`
                                : ''}
                            , em {dataBr(certificado.concluido_em)}.
                        </p>
                    </div>

                    <footer className="grid w-full grid-cols-2 items-end gap-4 text-[clamp(0.55rem,1.2vw,0.8rem)]">
                        <div className="flex flex-col items-center gap-1">
                            {/* Linha de assinatura do instrutor. */}
                            <span className="w-4/5 border-t border-[#1c1b17]" />
                            <span>
                                {certificado.instrutor ??
                                    'Instrutor responsável'}
                            </span>
                            <span className="text-[#5c5644]">Instrutor</span>
                        </div>
                        <div className="flex flex-col items-center gap-1">
                            <span className="font-mono text-[1.2em] font-semibold">
                                {certificado.codigo}
                            </span>
                            <span className="text-[#5c5644]">
                                Confira se este certificado é verdadeiro em:
                            </span>
                            <span className="font-mono [overflow-wrap:anywhere]">
                                {certificado.validar_url}
                            </span>
                            <span className="text-[#5c5644]">
                                Emitido em {dataBr(certificado.emitido_em)}
                            </span>
                        </div>
                    </footer>
                </article>
            </div>
        </>
    );
}
