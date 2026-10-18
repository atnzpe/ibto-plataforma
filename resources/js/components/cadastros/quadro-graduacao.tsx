/**
 * QUADRO DO CERTIFICADO DE GRADUAÇÃO na ficha do filiado (#46, parte 2), só
 * para quem cuida de todos os filiados. Mostra:
 * - o formulário de emissão, já com os textos SUGERIDOS (condição, nível,
 *   abrangência e local), que o Comando Geral pode ajustar antes de emitir;
 * - os certificados já emitidos, com "Ver / imprimir" e "Cancelar" (com motivo).
 */
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/** Os textos do certificado. */
type TextosGraduacao = {
    condicao: string;
    nivel: string;
    abrangencia: string;
    local: string;
};

/** Um certificado já emitido. */
type CertificadoEmitido = {
    id: number;
    codigo: string;
    numero_serie: string;
    condicao: string;
    nivel: string;
    emitido_em: string;
    valido: boolean;
    motivo_cancelamento: string | null;
};

/**
 * @param filiadoId O filiado da ficha. Ex.: 12
 * @param quadro A sugestão dos textos e os certificados já emitidos.
 * @param erro A mensagem de regra do servidor (ex.: filiado sem conta), ou undefined.
 * @returns O quadro do certificado de graduação.
 */
export default function QuadroGraduacao({
    filiadoId,
    quadro,
    erro,
}: {
    filiadoId: number;
    quadro: { sugestao: TextosGraduacao; certificados: CertificadoEmitido[] };
    erro?: string;
}) {
    // Os textos do formulário, começando pela sugestão.
    const [textos, setTextos] = useState<TextosGraduacao>(quadro.sugestao);
    // Enviando: trava o botão (evita emitir dois certificados).
    const [enviando, setEnviando] = useState(false);
    // Visual dos campos (44 px de altura).
    const campo =
        'h-11 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm';

    /**
     * Cancela um certificado, perguntando o motivo (fica no histórico).
     *
     * @param certificado O certificado a cancelar.
     */
    const cancelar = (certificado: CertificadoEmitido) => {
        const motivo = window.prompt(
            `Cancelar o certificado ${certificado.numero_serie}? A validação pública passa a mostrar "cancelado". Escreva o motivo:`,
        );
        // Fechou a janela ou deixou em branco: não cancela.
        if (motivo === null || motivo.trim() === '') {
            return;
        }
        router.post(
            `/cadastros/graduacoes/${certificado.id}/cancelar`,
            { motivo },
            { preserveScroll: true },
        );
    };

    return (
        <section
            aria-labelledby="titulo-graduacao"
            className="flex flex-col gap-4 rounded-md border border-border p-4"
        >
            <h2 id="titulo-graduacao" className="font-medium">
                Certificado de graduação
            </h2>
            <p className="text-sm text-muted-foreground">
                Confira os textos (vêm do nível e das áreas do filiado) e emita.
                O número de série e o QR code da validação saem sozinhos.
            </p>
            {erro && (
                <p
                    role="alert"
                    className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                >
                    {erro}
                </p>
            )}

            <form
                className="grid gap-3 sm:grid-cols-2"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    setEnviando(true);
                    router.post(
                        `/cadastros/filiados/${filiadoId}/graduacao`,
                        textos,
                        {
                            preserveScroll: true,
                            onFinish: () => setEnviando(false),
                        },
                    );
                }}
            >
                <label className="grid gap-1 text-sm sm:col-span-2">
                    Na condição de
                    <input
                        required
                        maxLength={150}
                        value={textos.condicao}
                        onChange={(evento) =>
                            setTextos({
                                ...textos,
                                condicao: evento.target.value,
                            })
                        }
                        placeholder="Ex.: Instrutor de Krav Maga"
                        className={campo}
                    />
                </label>
                <label className="grid gap-1 text-sm sm:col-span-2">
                    Nível
                    <input
                        required
                        maxLength={100}
                        value={textos.nivel}
                        onChange={(evento) =>
                            setTextos({ ...textos, nivel: evento.target.value })
                        }
                        placeholder="Ex.: Nível Expert - [E]"
                        className={campo}
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    Abrangência
                    <input
                        required
                        maxLength={60}
                        value={textos.abrangencia}
                        onChange={(evento) =>
                            setTextos({
                                ...textos,
                                abrangencia: evento.target.value,
                            })
                        }
                        className={campo}
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    Local
                    <input
                        required
                        maxLength={100}
                        value={textos.local}
                        onChange={(evento) =>
                            setTextos({ ...textos, local: evento.target.value })
                        }
                        className={campo}
                    />
                </label>
                <Button
                    type="submit"
                    className="min-h-11 sm:w-fit"
                    disabled={enviando}
                    data-tour="emitir-graduacao"
                >
                    Emitir certificado de graduação
                </Button>
            </form>

            {/* Os certificados já emitidos (o mais novo primeiro). */}
            {quadro.certificados.length > 0 && (
                <ul
                    className="flex flex-col gap-2"
                    aria-label="Certificados de graduação emitidos"
                >
                    {quadro.certificados.map((certificado) => (
                        <li
                            key={certificado.id}
                            className="flex flex-col gap-2 rounded-md border border-border p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span>
                                <strong className="font-mono">
                                    {certificado.numero_serie}
                                </strong>{' '}
                                · {certificado.condicao} · {certificado.nivel} ·{' '}
                                {certificado.emitido_em}{' '}
                                {/* Situação escrita (não só cor). */}
                                <span
                                    className={
                                        certificado.valido
                                            ? 'text-green-800 dark:text-green-300'
                                            : 'text-red-800 dark:text-red-300'
                                    }
                                >
                                    ·{' '}
                                    {certificado.valido
                                        ? 'Válido'
                                        : `Cancelado (${certificado.motivo_cancelamento ?? ''})`}
                                </span>
                            </span>
                            <span className="flex flex-wrap gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11"
                                >
                                    <a
                                        href={`/graduacoes/${certificado.codigo}`}
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Ver / imprimir
                                    </a>
                                </Button>
                                {certificado.valido && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11"
                                        onClick={() => cancelar(certificado)}
                                        aria-label={`Cancelar o certificado ${certificado.numero_serie}`}
                                    >
                                        Cancelar
                                    </Button>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
