/**
 * CERTIFICADO DE GRADUAÇÃO para imprimir / salvar em PDF (#46, parte 2), no
 * modelo institucional do Mestre Eduardo (14/10/2026): folha A4 EM PÉ, moldura
 * dupla, faixas verde-amarelo-azul, a marca do IBTO com o CNPJ, letras
 * clássicas (Cinzel) e o texto do modelo:
 *   "Certificamos que [nome] integra o quadro de membros do Instituto Brasileiro
 *   de Treinamento Operacional – IBTO e é reconhecido por esta instituição na
 *   condição de [condição] [nível]".
 * Rodapé: número de série, abrangência, local e data por extenso; assinaturas
 * do Presidente e do Vice-presidente e, no centro, o QR code da validação.
 * O botão "Imprimir / Salvar em PDF" não sai no papel.
 */
import { Head } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

// CNPJ do IBTO, impresso no topo como no modelo (dado público da instituição).
const CNPJ_IBTO = '35.855.768/0001-21';

// Os nomes dos meses, para a data por extenso. Ex.: 0 → 'janeiro'.
const MESES = [
    'janeiro',
    'fevereiro',
    'março',
    'abril',
    'maio',
    'junho',
    'julho',
    'agosto',
    'setembro',
    'outubro',
    'novembro',
    'dezembro',
];

/**
 * Data por extenso, em maiúsculas como no modelo. Ex.: '2026-10-14' → '14 DE OUTUBRO DE 2026'.
 *
 * @param data A data no formato do banco (ano-mês-dia).
 */
function dataPorExtenso(data: string): string {
    // Separa ano, mês e dia (sem usar o fuso do aparelho, que poderia mudar o dia).
    const [ano, mes, dia] = data.split('-').map(Number);

    return `${dia} de ${MESES[mes - 1]} de ${ano}`.toUpperCase();
}

/**
 * As três faixinhas verde, amarela e azul do modelo (enfeite: o leitor de tela pula).
 */
function FaixaBrasil() {
    return (
        <span aria-hidden="true" className="flex h-1 w-24">
            <span className="flex-1 bg-[#2e7d4f]" />
            <span className="flex-1 bg-[#e8c33a]" />
            <span className="flex-1 bg-[#2f5aa8]" />
        </span>
    );
}

/**
 * Um campo do rodapé: rótulo pequeno em cima e o valor em negrito embaixo.
 *
 * @param rotulo Ex.: 'Número de série'
 * @param valor Ex.: 'GR20260034E0'
 */
function CampoRodape({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="flex flex-col items-center gap-0.5 px-2 text-center">
            <span className="text-[0.62em] tracking-wider text-[#5c5644] uppercase">
                {rotulo}
            </span>
            <strong className="text-[0.78em] font-semibold tracking-wide uppercase">
                {valor}
            </strong>
        </div>
    );
}

/**
 * Uma assinatura: a linha, o nome e o cargo.
 *
 * @param nome Ex.: 'Eduardo Lopes'
 * @param cargo Ex.: 'Presidente'
 */
function Assinatura({ nome, cargo }: { nome: string; cargo: string }) {
    return (
        <div className="flex flex-col items-center gap-0.5">
            <span className="w-full border-t border-[#1c1b17]" />
            <span>{cargo}</span>
            <span className="text-[#5c5644]">{nome}</span>
        </div>
    );
}

/**
 * @param certificado A "foto" do dia da emissão, a validação e quem assina.
 * @returns A página do certificado de graduação.
 */
export default function VerGraduacao({
    certificado,
}: {
    certificado: {
        codigo: string;
        numero_serie: string;
        nome: string;
        condicao: string;
        nivel: string;
        abrangencia: string;
        local: string;
        emitido_em: string;
        valido: boolean;
        validar_url: string;
        // O desenho do QR code (texto SVG, gerado no nosso servidor) que leva a validar_url.
        qr_svg: string;
        presidente: string;
        vice: string;
    };
}) {
    // Os quatro campos do rodapé, como no modelo.
    const campos = [
        { rotulo: 'Número de série', valor: certificado.numero_serie },
        { rotulo: 'Abrangência', valor: certificado.abrangencia },
        { rotulo: 'Local', valor: certificado.local },
        {
            rotulo: 'Data de emissão',
            valor: dataPorExtenso(certificado.emitido_em),
        },
    ];

    return (
        <>
            <Head title={`Certificado ${certificado.numero_serie}`} />
            {/* Regras de impressão: A4 EM PÉ, sem margem do navegador, sem o botão. */}
            <style>
                {
                    '@page { size: A4 portrait; margin: 0 } @media print { .nao-imprimir { display: none !important } body { background: #fff } }'
                }
            </style>

            <main className="flex min-h-svh flex-col items-center gap-4 bg-[#e9e2cc] p-4 print:bg-white print:p-0">
                {/* Ações (não saem no papel). */}
                <div className="nao-imprimir flex w-full max-w-[210mm] flex-wrap items-center justify-between gap-2">
                    {/* Cancelado: o aviso aparece na tela (o papel não serve mais). */}
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

                {/* A folha: proporção de um A4 em pé; as letras acompanham a largura (cqw). */}
                <article
                    aria-label="Certificado de graduação"
                    className="@container relative aspect-[210/297] w-full max-w-[210mm] bg-[#fbf8ef] text-[#1c1b17] print:max-w-none"
                >
                    {/* Moldura dupla dourada (enfeite). */}
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-[2.5%] border-[3px] border-double border-[#b39b5a]"
                    />

                    <div className="relative flex h-full flex-col items-center px-[11%] py-[8%] text-center text-[2.4cqw]">
                        {/* Topo: o emblema, a marca, o nome do instituto e o CNPJ. */}
                        <header className="flex flex-col items-center gap-[0.4em]">
                            <AppLogoIcon className="size-[5em]" />
                            <span className="font-[Audiowide,sans-serif] text-[2.6em] leading-none text-[#3f3722]">
                                IBTO
                            </span>
                            <span className="text-[0.85em] tracking-[0.3em] uppercase">
                                Instituto Brasileiro
                                <br />
                                de Treinamento Operacional
                            </span>
                            <span className="text-[0.6em] tracking-wider">
                                CNPJ: {CNPJ_IBTO}
                            </span>
                            <FaixaBrasil />
                        </header>

                        {/* Corpo: o texto do modelo do Mestre Eduardo. */}
                        <div className="flex flex-1 flex-col items-center justify-center gap-[0.9em]">
                            <h1 className="font-[Cinzel,serif] text-[3.2em] leading-none font-bold tracking-wide">
                                CERTIFICADO
                            </h1>
                            <p className="text-[0.75em] tracking-[0.2em] uppercase">
                                Certificamos que:
                            </p>
                            <p className="w-full border-b border-[#cfc2a0] pb-[0.3em] font-[Cinzel,serif] text-[1.9em] font-bold">
                                {certificado.nome}
                            </p>
                            <p className="max-w-[92%] text-[0.8em] leading-relaxed tracking-wide uppercase">
                                integra o quadro de membros do{' '}
                                <strong>
                                    Instituto Brasileiro de Treinamento
                                    Operacional – IBTO
                                </strong>{' '}
                                e é reconhecido por esta instituição na condição
                                de
                            </p>
                            <p className="w-full border-b border-[#cfc2a0] pb-[0.3em] font-[Cinzel,serif] text-[1.7em] leading-tight font-bold">
                                {certificado.condicao}
                                <br />
                                {certificado.nivel}
                            </p>
                            <p className="max-w-[92%] text-[0.7em] leading-relaxed tracking-wide uppercase">
                                Para fins de registro e comprovação do vínculo e
                                da qualificação acima indicados, expede-se o
                                presente certificado, devidamente firmado pelos
                                representantes da instituição.
                            </p>
                        </div>

                        {/* Rodapé: série, abrangência, local e data, separados por traços. */}
                        <div className="mb-[1.6em] grid w-full auto-cols-fr grid-flow-col divide-x divide-[#1c1b17]/40">
                            {campos.map((campo) => (
                                <CampoRodape
                                    key={campo.rotulo}
                                    rotulo={campo.rotulo}
                                    valor={campo.valor}
                                />
                            ))}
                        </div>

                        {/* Assinaturas e o QR code no centro, como no modelo. */}
                        <footer className="grid w-full grid-cols-3 items-end gap-[1em] text-[0.75em]">
                            <Assinatura
                                nome={certificado.presidente}
                                cargo="Presidente"
                            />
                            <div className="flex flex-col items-center gap-[0.3em]">
                                {/* O QR code (SVG gerado no NOSSO servidor a partir do nosso endereço; seguro). */}
                                <div
                                    className="aspect-square w-[65%] [&>svg]:h-full [&>svg]:w-full"
                                    role="img"
                                    aria-label={`QR code para validar o certificado ${certificado.codigo}`}
                                    dangerouslySetInnerHTML={{
                                        __html: certificado.qr_svg,
                                    }}
                                />
                                <span className="text-[0.75em] [overflow-wrap:anywhere]">
                                    {certificado.validar_url}
                                </span>
                            </div>
                            <Assinatura
                                nome={certificado.vice}
                                cargo="Vice-presidente"
                            />
                        </footer>

                        <span className="mt-[1.2em]">
                            <FaixaBrasil />
                        </span>
                    </div>
                </article>
            </main>
        </>
    );
}
