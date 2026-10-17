/*
 * Moldura das telas de ENTRAR, CADASTRAR, ESQUECI A SENHA etc. Com as cores e
 * o logo do IBTO (pedido do Gleyson, 10/10/2026): o logo largo do site no
 * topo, sobre a faixa escura, e o título da tela embaixo.
 */
// Menu de idiomas (#53), no modo "dica do tradutor do navegador".
import BotaoSuporte from '@/components/botao-suporte';
import MenuIdiomas from '@/components/menu-idiomas';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        // overflow-x-clip: a página nunca rola para o lado, mesmo num celular estreito
        // (regra da casa: nenhuma tela com rolagem lateral). Conserto de 17/10/2026.
        <div className="relative flex min-h-svh w-full flex-col items-center justify-center gap-6 overflow-x-clip bg-background p-6 md:p-10">
            {/* Idiomas (#53) no canto de cima. Entrar e cadastrar enviam
                formulário, que precisa sair do nosso domínio: por isso a dica
                do tradutor do navegador, e não o link do Google. */}
            <div className="absolute top-4 right-4">
                <MenuIdiomas modo="navegador" />
            </div>
            {/* min-w-0 deixa a coluna encolher; os botões (que no kit não quebram linha)
                passam a quebrar o texto, com altura mínima de 44 px para o toque.
                Sem isso, "Enviar o e-mail de confirmação de novo" alargava a página no celular. */}
            <div className="w-full max-w-sm min-w-0 [&_button]:h-auto [&_button]:min-h-11 [&_button]:whitespace-normal">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        {/* Logo largo do IBTO (o mesmo do site), sobre a faixa escura da marca. */}
                        {/* Logo (17/10/2026): leva ao endereço "/" — visitante vai para a home do site do IBTO;
                            logado, para o painel. Link comum (<a>), porque pode sair da plataforma. */}
                        <a
                            href={home().url}
                            className="flex w-full justify-center rounded-xl bg-[#0a0a0a] px-4 py-3"
                        >
                            <img
                                src="/images/ibto-logo.webp"
                                alt="IBTO — Instituto Brasileiro de Treinamento Operacional (página inicial)"
                                className="h-16 w-auto max-w-full object-contain"
                            />
                        </a>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                    {/* Suporte técnico pelo WhatsApp (15/10/2026): quem não consegue entrar é quem mais precisa. */}
                    <div className="flex justify-center">
                        <BotaoSuporte />
                    </div>
                </div>
            </div>
        </div>
    );
}
