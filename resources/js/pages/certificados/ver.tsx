/**
 * CERTIFICADO DE CURSO para imprimir / salvar em PDF (#44), no MODELO
 * INSTITUCIONAL que o Mestre Eduardo mandou em 14/10/2026 ("mais
 * institucional, mais limpo, sem a vertente militar pesada"): folha A4 EM PÉ,
 * fundo claro com moldura dupla, faixas verde-amarelo-azul, a marca do IBTO
 * com o CNPJ, letras clássicas (Cinzel), o rodapé com código, carga horária,
 * local e data, as assinaturas e o QR code da validação no centro.
 *
 * O botão "Imprimir / Salvar em PDF" abre a impressão do navegador (lá se
 * escolhe "Salvar como PDF") e não sai no papel. O certificado de GRADUAÇÃO
 * (#46) vai usar este mesmo desenho, com outro texto.
 */
import { Head } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dataBr } from '@/pages/catalogo/index';

// CNPJ do IBTO, impresso no topo como no modelo do Mestre (dado público da instituição).
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
 * Data por extenso, como no modelo. Ex.: '2026-10-14' → '14 de outubro de 2026'.
 *
 * @param data A data no formato do banco (ano-mês-dia).
 * @returns A data escrita.
 */
function dataPorExtenso(data: string): string {
    // Separa ano, mês e dia (sem usar o fuso do aparelho, que poderia mudar o dia).
    const [ano, mes, dia] = data.split('-').map(Number);

    return `${dia} de ${MESES[mes - 1]} de ${ano}`;
}

/**
 * As três faixinhas verde, amarela e azul do modelo (só enfeite: o leitor de tela pula).
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
 * @param rotulo Ex.: 'Carga horária'
 * @param valor Ex.: '16 horas'
 */
function CampoRodape({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="flex flex-col items-center gap-0.5 px-2 text-center">
            <span className="text-[0.62em] tracking-wider text-[#5c5644] uppercase">
                {rotulo}
            </span>
            <strong className="text-[0.8em] font-semibold tracking-wide uppercase">
                {valor}
            </strong>
        </div>
    );
}

/**
 * @param certificado A "foto" do certificado (do dia da emissão), o local, o
 *                    endereço da validação e o QR code.
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
        // Onde foi o curso. Ex.: 'Recife - PE' ou 'Online'; null = não aparece.
        local: string | null;
        concluido_em: string;
        emitido_em: string;
        valido: boolean;
        validar_url: string;
        // O desenho do QR code (texto SVG) que leva a validar_url.
        qr_svg: string;
    };
}) {
    // Os campos do rodapé, só os que têm valor (sem carga horária ou local, o campo some).
    const campos = [
        { rotulo: 'Código', valor: certificado.codigo },
        certificado.carga_horaria
            ? {
                  rotulo: 'Carga horária',
                  valor: `${certificado.carga_horaria} horas`,
              }
            : null,
        certificado.local
            ? { rotulo: 'Local', valor: certificado.local }
            : null,
        {
            rotulo: 'Data de emissão',
            valor: dataPorExtenso(certificado.emitido_em),
        },
    ].filter((campo) => campo !== null);

    return (
        <>
            <Head title={`Certificado ${certificado.codigo}`} />
            {/* Regras de impressão: A4 EM PÉ, sem margem do navegador, sem o botão. */}
            <style>
                {
                    '@page { size: A4 portrait; margin: 0 } @media print { .nao-imprimir { display: none !important } body { background: #fff } }'
                }
            </style>

            <div className="flex min-h-svh flex-col items-center gap-4 bg-[#e9e2cc] p-4 print:bg-white print:p-0">
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

                {/*
                 * A folha: proporção de um A4 em pé (210 × 297 mm). O tamanho
                 * das letras acompanha a largura da folha (cqw = % da largura
                 * do "container"), então o desenho fica igual no celular, no
                 * computador e no papel.
                 */}
                <article
                    aria-label="Certificado"
                    className="@container relative aspect-[210/297] w-full max-w-[210mm] bg-[#fbf8ef] text-[#1c1b17] print:max-w-none"
                >
                    {/* Moldura dupla dourada (enfeite). */}
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-[2.5%] border-[3px] border-double border-[#b39b5a]"
                    />

                    <div className="relative flex h-full flex-col items-center px-[11%] py-[9%] text-center text-[2.4cqw]">
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

                        {/* Corpo: CERTIFICADO, o nome e o curso. */}
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
                            <p className="max-w-[90%] text-[0.8em] leading-relaxed tracking-wide uppercase">
                                concluiu, no{' '}
                                <strong>
                                    Instituto Brasileiro de Treinamento
                                    Operacional – IBTO
                                </strong>
                                , o curso
                            </p>
                            <p className="w-full border-b border-[#cfc2a0] pb-[0.3em] font-[Cinzel,serif] text-[1.7em] leading-tight font-bold">
                                {certificado.curso}
                            </p>
                            <p className="max-w-[90%] text-[0.7em] leading-relaxed tracking-wide uppercase">
                                concluído em {dataBr(certificado.concluido_em)}.
                                Para fins de registro e comprovação, expede-se o
                                presente certificado.
                            </p>
                        </div>

                        {/* Rodapé: código, carga horária, local e data, separados por traços. */}
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
                            <div className="flex flex-col items-center gap-0.5">
                                {/* Linha da assinatura do instrutor. */}
                                <span className="w-full border-t border-[#1c1b17]" />
                                <span>
                                    {certificado.instrutor ??
                                        'Instrutor responsável'}
                                </span>
                                <span className="text-[#5c5644]">
                                    Instrutor
                                </span>
                            </div>
                            <div className="flex flex-col items-center gap-[0.3em]">
                                {/*
                                 * O QR code (SVG gerado no NOSSO servidor, em
                                 * App\Support\QrCode, a partir do nosso próprio
                                 * endereço; por isso é seguro colocá-lo direto
                                 * na página). Leva à validação pública.
                                 */}
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
                            <div className="flex flex-col items-center gap-0.5">
                                {/* Linha da assinatura do presidente (quem assina pelo IBTO no modelo). */}
                                <span className="w-full border-t border-[#1c1b17]" />
                                <span>Eduardo Lopes</span>
                                <span className="text-[#5c5644]">
                                    Presidente
                                </span>
                            </div>
                        </footer>

                        <span className="mt-[1.2em]">
                            <FaixaBrasil />
                        </span>
                    </div>
                </article>
            </div>
        </>
    );
}
