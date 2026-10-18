/**
 * POLÍTICA DE PRIVACIDADE (LGPD, #47): o texto público de quais dados o IBTO
 * guarda, para quê, por quanto tempo, com quem compartilha e como a pessoa
 * exerce os direitos dela. Versão 0.1: RASCUNHO em revisão pela diretoria e
 * pelo jurídico (o aviso aparece no topo).
 * Quem está logado vê também o próprio aceite, com o botão de aceitar ou revogar.
 */
import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Uma seção da política, com título h2 e o texto.
 *
 * @param titulo O título. Ex.: 'Quais dados guardamos'.
 * @param children O texto da seção.
 * @returns A seção.
 */
function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-2">
            <h2 className="font-[Audiowide,sans-serif] text-lg">{titulo}</h2>
            <div className="flex flex-col gap-2 text-sm leading-relaxed">
                {children}
            </div>
        </section>
    );
}

/**
 * @param versao_politica A versão do texto. Ex.: '0.1'. (Não se chama "versao": esse nome é da versão da plataforma.)
 * @param data A data desta versão. Ex.: '17/10/2026'.
 * @param encarregado O encarregado de dados (DPO), ou null ("a definir").
 * @param contato O e-mail para os pedidos do titular.
 * @param logado Se quem vê está logado.
 * @param aceito_em Quando aceitou esta versão, ou null.
 * @param exigido Se a plataforma está pedindo o aceite antes de seguir.
 * @returns A página da política.
 */
export default function Privacidade({
    versao_politica,
    data,
    encarregado,
    contato,
    logado,
    aceito_em,
    exigido,
}: {
    versao_politica: string;
    data: string;
    encarregado: string | null;
    contato: string;
    logado: boolean;
    aceito_em: string | null;
    exigido: boolean;
}) {
    // O aviso de sucesso que o servidor mandou (ou null).
    const { sucesso } = usePage<{ sucesso?: string | null }>().props;

    return (
        <>
            <Head title="Política de privacidade" />
            <article className="mx-auto flex max-w-3xl flex-col gap-6">
                <h1 className="font-[Audiowide,sans-serif] text-2xl">
                    Política de privacidade
                </h1>
                <p className="text-sm text-[#5c5644]">
                    Versão {versao_politica} · {data}
                </p>
                {/* Enquanto for rascunho (versão 0.x), o aviso fica no topo. Desde 18/10/2026 a
                    política está aprovada (1.0): o aviso não aparece mais. */}
                {versao_politica.startsWith('0.') && (
                    <p
                        role="note"
                        className="rounded-lg border border-amber-700 bg-amber-50 p-3 text-sm text-amber-900"
                    >
                        Rascunho em revisão pela diretoria e pelo jurídico do
                        IBTO. O texto final pode mudar.
                    </p>
                )}
                {sucesso && (
                    <p
                        role="status"
                        className="rounded-lg border border-green-700 bg-green-50 p-3 text-sm text-green-900"
                    >
                        {sucesso}
                    </p>
                )}

                <Secao titulo="Quem somos">
                    <p>
                        O Instituto Brasileiro de Treinamento Operacional (IBTO)
                        é uma instituição privada e fechada, de acesso por
                        indicação. Esta política vale para a plataforma de
                        cursos e para o site do IBTO.
                    </p>
                </Secao>

                <Secao titulo="Quais dados guardamos">
                    <ul className="list-disc pl-5">
                        <li>
                            Conta: nome, e-mail e senha (a senha fica
                            embaralhada; nem o IBTO consegue lê-la).
                        </li>
                        <li>
                            Ficha de filiado: registro, equipe, categoria,
                            nível, cidade, CPF, data de nascimento, telefone e
                            fotos (perfil e 3x4).
                        </li>
                        <li>
                            Cursos: inscrições, presenças, conteúdo assistido e
                            certificados.
                        </li>
                        <li>
                            Pagamentos: valor, data e o identificador do Pix.
                            Não guardamos dados de cartão nem senha de banco.
                        </li>
                        <li>
                            Registros técnicos: data, tela e erro, para manter a
                            plataforma funcionando, sem dados pessoais.
                        </li>
                    </ul>
                </Secao>

                <Secao titulo="Para que usamos">
                    <ul className="list-disc pl-5">
                        <li>
                            Identificar o filiado, emitir carteirinha,
                            certificados e o dossiê de formação.
                        </li>
                        <li>
                            Organizar turmas, chamadas e pagamentos dos cursos.
                        </li>
                        <li>
                            Cumprir obrigações legais (por exemplo, registros
                            financeiros).
                        </li>
                        <li>
                            Mostrar a sua foto fora da plataforma (Rede
                            Nacional, validação pública) só com a sua
                            autorização, que você muda quando quiser.
                        </li>
                    </ul>
                </Secao>

                <Secao titulo="Como protegemos">
                    <p>
                        CPF, nascimento e telefone ficam criptografados no
                        banco. Cada pessoa da equipe vê só o que a função dela
                        permite. Toda abertura da sua ficha completa fica
                        registrada, e você vê a lista em Configurações → Meus
                        dados. Os registros técnicos passam por uma máscara que
                        esconde dados pessoais.
                    </p>
                </Secao>

                <Secao titulo="Com quem compartilhamos">
                    <p>
                        Não vendemos nem cedemos dados. A plataforma fica num
                        servidor contratado (hospedagem). O tradutor do Google e
                        o VLibras (gov.br) só recebem a página quando você mesmo
                        usa essas ferramentas.
                    </p>
                </Secao>

                <Secao titulo="Por quanto tempo guardamos">
                    <ul className="list-disc pl-5">
                        <li>
                            Ficha e histórico de formação: enquanto você for
                            filiado, e depois pelo prazo que a lei exigir.
                        </li>
                        <li>
                            Registros técnicos: 14 dias. Registros de segurança:
                            30 dias.
                        </li>
                        <li>
                            Fotos à venda dos eventos: apagadas na data de
                            expiração do álbum.
                        </li>
                    </ul>
                </Secao>

                <Secao titulo="Seus direitos">
                    <p>
                        Você pode saber quais dados temos, corrigir, pedir uma
                        cópia, pedir a exclusão ou a anonimização (quando a lei
                        permitir) e revogar autorizações. Escreva para{' '}
                        <a
                            href={`mailto:${contato}?subject=LGPD`}
                            className="underline underline-offset-4"
                        >
                            {contato}
                        </a>
                        .
                    </p>
                    <p>
                        Encarregado de dados (DPO):{' '}
                        {encarregado ?? 'a definir pela diretoria'}.
                    </p>
                </Secao>

                {/* O aceite: só para quem está logado. */}
                {logado && (
                    <section
                        aria-labelledby="t-aceite"
                        className="flex flex-col gap-3 rounded-xl border border-[#e4dccb] bg-white p-4"
                    >
                        <h2 id="t-aceite" className="font-medium">
                            Seu aceite
                        </h2>
                        {aceito_em ? (
                            <>
                                <p className="text-sm">
                                    Você aceitou a versão {versao_politica} em{' '}
                                    {aceito_em}.
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.delete('/privacidade/aceite')
                                    }
                                    className="min-h-11 self-start rounded-md border border-red-800/40 px-4 text-sm font-medium text-red-900"
                                >
                                    Revogar meu aceite
                                </button>
                            </>
                        ) : (
                            <>
                                {/* Com o aceite obrigatório ligado, explica por que a pessoa caiu aqui. */}
                                <p className="text-sm">
                                    {exigido
                                        ? 'Para continuar usando a plataforma, leia e aceite esta política.'
                                        : 'Você ainda não aceitou esta versão.'}
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post('/privacidade/aceite')
                                    }
                                    className="min-h-11 self-start rounded-md bg-[#6b5d3a] px-4 text-sm font-medium text-white hover:bg-[#5a4e30]"
                                >
                                    Li e aceito a política de privacidade
                                </button>
                            </>
                        )}
                    </section>
                )}
            </article>
        </>
    );
}
