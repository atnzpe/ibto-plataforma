/**
 * VALIDAÇÃO PÚBLICA do certificado (#44): qualquer pessoa (ex.: uma empresa
 * que recebeu o certificado) confere pelo código. Mostra só o necessário:
 * curso, datas, carga horária e o NOME ABREVIADO (LGPD). Código inventado:
 * "não encontrado". Cancelado: aviso em destaque (deixa de valer).
 * Tem também a caixa para digitar outro código.
 */
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { dataBr } from '@/pages/catalogo/index';
import { validar } from '@/routes/certificados';

/**
 * @param codigo O código consultado. Ex.: 'IBTO-7K2M-9QXA'
 * @param certificado O que achou, ou null (não encontrado).
 * @returns A tela da validação.
 */
export default function ValidarCertificado({
    codigo,
    certificado,
}: {
    codigo: string;
    certificado: {
        valido: boolean;
        nome: string;
        curso: string;
        carga_horaria: number | null;
        concluido_em: string;
        emitido_em: string;
        cancelado_em: string | null;
    } | null;
}) {
    // O código digitado na caixa (para consultar outro).
    const [outro, setOutro] = useState('');

    return (
        <>
            <Head title="Validar certificado" />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-6">
                <h1 className="font-[Audiowide,sans-serif] text-2xl font-normal">
                    Validar certificado
                </h1>

                {/* Resultado, com o estado ESCRITO (não só cor). */}
                {certificado === null && (
                    <p
                        role="alert"
                        className="rounded-xl border border-red-700/40 bg-red-50 p-4 text-red-900"
                    >
                        <strong>Não encontrado.</strong> Nenhum certificado do
                        IBTO tem o código{' '}
                        <span className="font-mono">{codigo}</span>. Confira se
                        foi digitado certo.
                    </p>
                )}

                {certificado !== null && (
                    <section
                        aria-label="Resultado da validação"
                        className={`flex flex-col gap-3 rounded-xl border p-5 ${certificado.valido ? 'border-green-700/40 bg-green-50 text-green-950' : 'border-red-700/40 bg-red-50 text-red-950'}`}
                    >
                        <strong className="text-lg">
                            {certificado.valido
                                ? '✓ Certificado VÁLIDO'
                                : `✗ Certificado CANCELADO em ${dataBr(certificado.cancelado_em ?? '')}`}
                        </strong>
                        <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                            <dt className="font-medium">Código</dt>
                            <dd className="font-mono">{codigo}</dd>
                            <dt className="font-medium">Nome</dt>
                            <dd>{certificado.nome}</dd>
                            <dt className="font-medium">Curso</dt>
                            <dd>{certificado.curso}</dd>
                            {certificado.carga_horaria !== null && (
                                <>
                                    <dt className="font-medium">
                                        Carga horária
                                    </dt>
                                    <dd>{certificado.carga_horaria} horas</dd>
                                </>
                            )}
                            <dt className="font-medium">Concluído em</dt>
                            <dd>{dataBr(certificado.concluido_em)}</dd>
                            <dt className="font-medium">Emitido em</dt>
                            <dd>{dataBr(certificado.emitido_em)}</dd>
                        </dl>
                        <p className="text-xs">
                            O nome aparece abreviado para proteger os dados da
                            pessoa (LGPD).
                        </p>
                    </section>
                )}

                {/* Consultar outro código. */}
                <form
                    className="flex flex-col gap-2 sm:flex-row"
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        // Vazio: não consulta.
                        if (outro.trim() !== '') {
                            router.get(validar(outro.trim()).url);
                        }
                    }}
                >
                    <label htmlFor="codigo" className="sr-only">
                        Código do certificado
                    </label>
                    <input
                        id="codigo"
                        value={outro}
                        onChange={(evento) => setOutro(evento.target.value)}
                        placeholder="Ex.: IBTO-7K2M-9QXA"
                        className="h-11 flex-1 rounded-lg border border-[#b9ae8c] bg-white px-3 font-mono uppercase"
                    />
                    <button
                        type="submit"
                        className="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#6b5d3a] px-5 font-semibold text-white"
                    >
                        Validar
                    </button>
                </form>
            </div>
        </>
    );
}
