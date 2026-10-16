/**
 * CADASTRO EM ANÁLISE (#84; o IBTO é um grupo fechado, só de indicados).
 *
 * Quem pediu acesso e ainda não foi aprovado só vê esta tela (o porteiro do
 * servidor manda para cá qualquer outro endereço). Explica o que acontece
 * agora, mostra a equipe escolhida e deixa sair ou falar com o suporte (o
 * botão de suporte já vem na moldura das telas de entrar).
 */
import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { logout } from '@/routes';

/**
 * A tela de espera.
 *
 * @param equipe O nome da equipe escolhida no pedido, ou null. Ex.: 'IBTO Pernambuco'
 * @returns O aviso de "em análise" com os próximos passos.
 */
export default function CadastroEmAnalise({
    equipe,
}: {
    equipe: string | null;
}) {
    return (
        <>
            <Head title="Cadastro em análise" />
            {/* role="status": o leitor de tela anuncia a situação ao abrir. */}
            <div role="status" className="space-y-4 text-center text-sm">
                <p>
                    O seu pedido de acesso foi recebido
                    {equipe ? (
                        <>
                            {' '}
                            e está com a equipe <strong>{equipe}</strong>
                        </>
                    ) : null}
                    .
                </p>
                <ol className="mx-auto max-w-xs list-decimal space-y-1 pl-5 text-left text-muted-foreground">
                    <li>
                        Confirme o seu e-mail pelo link que enviamos (veja
                        também o spam).
                    </li>
                    <li>O admin da sua equipe ou o IBTO analisa o pedido.</li>
                    <li>
                        Você recebe um e-mail com a resposta e, aprovado, já
                        pode entrar.
                    </li>
                </ol>
                <TextLink href={logout()} className="mx-auto block">
                    Sair
                </TextLink>
            </div>
        </>
    );
}

// Título e texto da moldura das telas de entrar.
CadastroEmAnalise.layout = {
    title: 'Cadastro em análise',
    description:
        'O IBTO é um grupo fechado: todo pedido de acesso passa por uma análise.',
};
