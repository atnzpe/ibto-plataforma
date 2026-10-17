/**
 * CONFIGURAÇÕES · MEUS DADOS (LGPD, #47): o que a plataforma guarda sobre a
 * pessoa (dados sensíveis mascarados), a autorização de imagem e a lista de
 * quem abriu a ficha dela com CPF, nascimento e telefone. Pedidos de correção
 * ou exclusão vão por e-mail ao suporte.
 * Responsivo: a lista vira cartões no celular (sem rolagem lateral).
 */
import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { show as meusDados } from '@/routes/meus-dados';

/** Os dados da ficha de filiado (null para quem não é filiado). */
type FichaFiliado = {
    registro: string;
    equipe: string | null;
    categoria: string | null;
    nivel: string | null;
    cidade: string;
    cpf: string | null;
    telefone: string | null;
    nascimento: string | null;
    autoriza_imagem: boolean;
    autoriza_imagem_em: string | null;
};

/** Um acesso à ficha. Ex.: { quando: '17/10/2026 10:32', quem: 'Aline S.' }. */
type Acesso = { quando: string | null; quem: string };

/**
 * Uma linha "rótulo: valor" da lista de dados.
 *
 * @param rotulo O nome do dado. Ex.: 'CPF'.
 * @param valor O valor, ou null (aparece "não informado").
 * @returns O par para a lista de definições.
 */
function Dado({ rotulo, valor }: { rotulo: string; valor: string | null }) {
    return (
        <>
            <dt className="text-muted-foreground">{rotulo}</dt>
            <dd className="[overflow-wrap:anywhere]">
                {valor ?? 'não informado'}
            </dd>
        </>
    );
}

/**
 * @param conta Nome, e-mail e data de criação da conta.
 * @param filiado A ficha de filiado, ou null.
 * @param acessos Os últimos acessos à ficha com dados sensíveis.
 * @param suporte_email Para onde mandar os pedidos de correção ou exclusão.
 * @returns A tela "Meus dados".
 */
export default function MeusDados({
    conta,
    filiado,
    acessos,
    suporte_email,
}: {
    conta: { nome: string; email: string; criada_em: string | null };
    filiado: FichaFiliado | null;
    acessos: Acesso[];
    suporte_email: string;
}) {
    return (
        <>
            <Head title="Meus dados" />
            <h1 className="sr-only">Meus dados</h1>

            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Meus dados"
                    description="O que o IBTO guarda sobre você e quem acessou (LGPD)"
                />

                <section aria-labelledby="t-conta" className="space-y-3">
                    <h2 id="t-conta" className="font-medium">
                        Conta
                    </h2>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                        <Dado rotulo="Nome" valor={conta.nome} />
                        <Dado rotulo="E-mail" valor={conta.email} />
                        <Dado
                            rotulo="Conta criada em"
                            valor={conta.criada_em}
                        />
                    </dl>
                </section>

                {/* Ficha de filiado: só para quem é filiado. */}
                {filiado && (
                    <section aria-labelledby="t-ficha" className="space-y-3">
                        <h2 id="t-ficha" className="font-medium">
                            Ficha de filiado
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Os dados sensíveis aparecem mascarados nesta tela e
                            ficam criptografados no banco.
                        </p>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <Dado rotulo="Registro" valor={filiado.registro} />
                            <Dado rotulo="Equipe" valor={filiado.equipe} />
                            <Dado
                                rotulo="Categoria"
                                valor={filiado.categoria}
                            />
                            <Dado rotulo="Nível" valor={filiado.nivel} />
                            <Dado
                                rotulo="Cidade"
                                valor={filiado.cidade || null}
                            />
                            <Dado rotulo="CPF" valor={filiado.cpf} />
                            <Dado rotulo="Telefone" valor={filiado.telefone} />
                            <Dado
                                rotulo="Nascimento"
                                valor={filiado.nascimento}
                            />
                            <Dado
                                rotulo="Uso da imagem"
                                valor={
                                    filiado.autoriza_imagem
                                        ? `autorizado${filiado.autoriza_imagem_em ? ` em ${filiado.autoriza_imagem_em}` : ''}`
                                        : 'não autorizado'
                                }
                            />
                        </dl>
                    </section>
                )}

                {filiado && (
                    <section aria-labelledby="t-acessos" className="space-y-3">
                        <h2 id="t-acessos" className="font-medium">
                            Quem abriu sua ficha com CPF, nascimento e telefone
                        </h2>
                        {acessos.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Ninguém abriu sua ficha completa até agora.
                            </p>
                        ) : (
                            <table className="tabela-cartoes w-full text-sm">
                                <thead>
                                    <tr className="text-left">
                                        <th scope="col" className="py-2">
                                            Quando
                                        </th>
                                        <th scope="col" className="py-2">
                                            Quem
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {acessos.map((acesso, i) => (
                                        <tr key={i} className="border-t">
                                            <td
                                                data-rotulo="Quando"
                                                className="py-2"
                                            >
                                                {acesso.quando}
                                            </td>
                                            <td
                                                data-rotulo="Quem"
                                                className="py-2"
                                            >
                                                {acesso.quem}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>
                )}

                <section
                    aria-labelledby="t-direitos"
                    className="space-y-2 text-sm"
                >
                    <h2 id="t-direitos" className="font-medium">
                        Seus direitos
                    </h2>
                    <p>
                        Para corrigir um dado, pedir uma cópia ou a exclusão,
                        escreva para{' '}
                        <a
                            href={`mailto:${suporte_email}?subject=LGPD%20-%20pedido%20sobre%20meus%20dados`}
                            className="underline underline-offset-4"
                        >
                            {suporte_email}
                        </a>
                        . A autorização de imagem você muda em Perfil, quando
                        quiser.
                    </p>
                </section>
            </div>
        </>
    );
}

MeusDados.layout = {
    breadcrumbs: [{ title: 'Meus dados', href: meusDados() }],
};
