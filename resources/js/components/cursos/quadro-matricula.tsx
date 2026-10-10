/**
 * QUADRO DA MATRÍCULA na página do curso (#41, bloco C5b). Mostra o passo em
 * que a pessoa está:
 * - visitante: "Entrar para se inscrever";
 * - sem matrícula: "Garantir minha vaga" (ou "Vagas esgotadas");
 * - aguardando pagamento: os dados para pagar (PIX e/ou conta, do curso ou
 *   do IBTO, conforme a chave liga/desliga do Comando Geral), o envio do
 *   comprovante e "Desistir";
 * - confirmada: o selo "Matrícula confirmada" (o botão de assistir fica na página).
 * Mensagens de sucesso e de erro (com o código) aparecem no topo do quadro.
 */
import { Form, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MatriculaController from '@/actions/App/Http/Controllers/MatriculaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';
import { login } from '@/routes';
import { desistir } from '@/routes/matriculas';
import { ver as verComprovante } from '@/routes/matriculas/comprovante';

/** A matrícula da pessoa (vem do CatalogoController::matriculaDe). */
export type MatriculaDaPessoa = {
    id: number;
    situacao: 'aguardando_pagamento' | 'confirmada';
    situacao_rotulo: string;
    valor_centavos: number;
    tem_comprovante: boolean;
    // Só quando aguardando pagamento (ver App\Support\DadosPagamento). Campos vazios = não cadastrados.
    pagamento: DadosParaPagar | null;
};

/** Os dados para pagar: do curso ou do IBTO (chave liga/desliga no Comando Geral). */
type DadosParaPagar = {
    origem: 'curso' | 'ibto';
    pix_chave: string;
    favorecido: string;
    banco: string;
    agencia: string;
    conta: string;
    instrucoes: string;
};

/**
 * @param cursoId O curso. Ex.: 7
 * @param logado true se há alguém logado.
 * @param gratuito true se o curso não custa nada (o botão muda de texto).
 * @param previa true = curso ainda não publicado (sem matrícula).
 * @param vagasRestantes Quantas vagas sobram; null = sem limite.
 * @param matricula A matrícula da pessoa, ou null.
 * @returns O quadro com o passo atual.
 */
export default function QuadroMatricula({
    cursoId,
    logado,
    gratuito,
    previa,
    vagasRestantes,
    matricula,
}: {
    cursoId: number;
    logado: boolean;
    gratuito: boolean;
    previa: boolean;
    vagasRestantes: number | null;
    matricula: MatriculaDaPessoa | null;
}) {
    // Mensagem de sucesso e erros (a matrícula manda em "matricula"; o envio, em "comprovante").
    const { sucesso, errors } = usePage().props;
    // Chave copiada? (mostra "Copiada!" por um instante no botão).
    const [copiada, setCopiada] = useState(false);

    /**
     * Copia a chave PIX para a área de transferência (colar no app do banco).
     *
     * @param chave Ex.: 'financeiro@ib-to.org'
     */
    const copiar = async (chave: string) => {
        try {
            await navigator.clipboard.writeText(chave);
            setCopiada(true);
            // Volta ao texto normal depois de 2 segundos.
            setTimeout(() => setCopiada(false), 2000);
        } catch {
            // Navegador sem permissão de copiar: a chave continua visível para copiar à mão.
            setCopiada(false);
        }
    };

    // Classe comum do botão principal (48 px, largura toda).
    const principal =
        'inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#6b5d3a] text-base font-semibold text-white no-underline hover:bg-[#5a4e30] disabled:opacity-60';

    return (
        <div className="flex flex-col gap-3">
            {sucesso && (
                <p
                    role="status"
                    className="rounded-lg border border-green-700/40 bg-green-700/10 px-3 py-2 text-sm"
                >
                    {sucesso}
                </p>
            )}
            {errors.matricula && (
                <p
                    role="alert"
                    className="rounded-lg border border-red-700/40 bg-red-700/10 px-3 py-2 text-sm"
                >
                    {errors.matricula}
                </p>
            )}

            {/* Prévia (curso não publicado): sem matrícula. */}
            {previa && (
                <p className="text-sm text-[#5c5644]">
                    Prévia: a matrícula abre quando o curso for publicado.
                </p>
            )}

            {/* Visitante: entrar primeiro (a matrícula fica presa à conta). */}
            {!previa && !logado && (
                <Link href={login()} className={principal}>
                    Entrar para se inscrever
                </Link>
            )}

            {/* Logado e sem matrícula: garantir a vaga, ou vagas esgotadas. */}
            {!previa &&
                logado &&
                matricula === null &&
                (vagasRestantes === 0 ? (
                    <button type="button" className={principal} disabled>
                        Vagas esgotadas
                    </button>
                ) : (
                    <Form {...MatriculaController.store.form(cursoId)}>
                        {({ processing }) => (
                            <button
                                type="submit"
                                className={principal}
                                disabled={processing}
                            >
                                {gratuito
                                    ? 'Matricular-me (gratuito)'
                                    : 'Garantir minha vaga'}
                            </button>
                        )}
                    </Form>
                ))}

            {/* Vagas restantes (só quando há limite e ainda sobram). */}
            {!previa &&
                vagasRestantes !== null &&
                vagasRestantes > 0 &&
                matricula === null && (
                    <p className="text-center text-xs text-[#5c5644]">
                        Restam {vagasRestantes} vaga(s).
                    </p>
                )}

            {/* Confirmada: selo escrito (não só cor). */}
            {matricula?.situacao === 'confirmada' && (
                <p className="rounded-lg bg-[#1c1b17] px-3 py-2 text-center text-sm font-semibold text-[#c9bd94]">
                    ✓ Matrícula confirmada
                </p>
            )}

            {/* Aguardando pagamento: PIX, comprovante e desistir. */}
            {matricula?.situacao === 'aguardando_pagamento' && (
                <div className="flex flex-col gap-3 rounded-xl border border-[#e4dccb] bg-[#faf8f3] p-4">
                    <p className="text-sm font-semibold">
                        Vaga reservada · aguardando pagamento
                    </p>

                    {/* Dados para pagar (do curso ou do IBTO). Sem PIX nem conta: orienta pelo WhatsApp. */}
                    {matricula.pagamento &&
                    (matricula.pagamento.pix_chave !== '' ||
                        matricula.pagamento.conta !== '') ? (
                        <dl className="grid gap-1 text-sm">
                            <dt className="text-xs text-[#5c5644]">Valor</dt>
                            <dd className="text-lg font-semibold">
                                {formatarReais(matricula.valor_centavos)}
                            </dd>
                            {matricula.pagamento.pix_chave !== '' && (
                                <>
                                    <dt className="text-xs text-[#5c5644]">
                                        Chave PIX
                                    </dt>
                                    {/* overflow-wrap: chave comprida quebra a linha (sem rolagem lateral). */}
                                    <dd className="font-mono [overflow-wrap:anywhere]">
                                        {matricula.pagamento.pix_chave}
                                    </dd>
                                </>
                            )}
                            {matricula.pagamento.favorecido !== '' && (
                                <>
                                    <dt className="text-xs text-[#5c5644]">
                                        Favorecido
                                    </dt>
                                    <dd>{matricula.pagamento.favorecido}</dd>
                                </>
                            )}
                            {/* Transferência: banco, agência e conta (só o que estiver cadastrado). */}
                            {matricula.pagamento.conta !== '' && (
                                <>
                                    <dt className="text-xs text-[#5c5644]">
                                        Transferência
                                    </dt>
                                    <dd className="[overflow-wrap:anywhere]">
                                        {[
                                            matricula.pagamento.banco,
                                            matricula.pagamento.agencia &&
                                                `Ag. ${matricula.pagamento.agencia}`,
                                            `Conta ${matricula.pagamento.conta}`,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </dd>
                                </>
                            )}
                            {matricula.pagamento.instrucoes !== '' && (
                                <dd className="mt-1 text-xs text-[#5c5644]">
                                    {matricula.pagamento.instrucoes}
                                </dd>
                            )}
                            {matricula.pagamento.pix_chave !== '' && (
                                <dd className="mt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11 w-full"
                                        onClick={() =>
                                            copiar(
                                                matricula.pagamento
                                                    ?.pix_chave ?? '',
                                            )
                                        }
                                    >
                                        {copiada
                                            ? 'Copiada!'
                                            : 'Copiar a chave PIX'}
                                    </Button>
                                </dd>
                            )}
                        </dl>
                    ) : (
                        // Nada cadastrado ainda: orienta a falar com o IBTO.
                        <p className="text-sm">
                            Fale com o IBTO pelo WhatsApp do curso para receber
                            os dados para pagamento.
                        </p>
                    )}

                    {/* Envio do comprovante (PDF ou foto). */}
                    <Form
                        {...MatriculaController.comprovante.form(matricula.id)}
                        className="flex flex-col gap-2"
                    >
                        {({ processing }) => (
                            <>
                                <label
                                    htmlFor="comprovante"
                                    className="text-sm font-medium"
                                >
                                    {matricula.tem_comprovante
                                        ? 'Trocar o comprovante'
                                        : 'Enviar o comprovante do PIX'}
                                </label>
                                <input
                                    id="comprovante"
                                    name="comprovante"
                                    type="file"
                                    accept="application/pdf,image/jpeg,image/png"
                                    required
                                    className="w-full text-sm"
                                />
                                <InputError message={errors.comprovante} />
                                <Button
                                    type="submit"
                                    className="min-h-11 w-full"
                                    disabled={processing}
                                >
                                    Enviar comprovante
                                </Button>
                            </>
                        )}
                    </Form>

                    {/* Já enviou: link para conferir o arquivo e o aviso do próximo passo. */}
                    {matricula.tem_comprovante && (
                        <p className="text-xs text-[#5c5644]">
                            Comprovante recebido (
                            <a
                                href={verComprovante(matricula.id).url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="underline underline-offset-4"
                            >
                                ver
                            </a>
                            ). O IBTO confere e confirma a sua matrícula; você
                            recebe um e-mail.
                        </p>
                    )}

                    <button
                        type="button"
                        className="min-h-11 text-sm text-[#5c5644] underline underline-offset-4"
                        onClick={() =>
                            // Confirmação antes: desistir libera a vaga para outra pessoa.
                            window.confirm(
                                'Desistir desta matrícula? A vaga fica livre para outra pessoa.',
                            ) &&
                            router.post(
                                desistir(matricula.id).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Desistir da matrícula
                    </button>
                </div>
            )}
        </div>
    );
}
