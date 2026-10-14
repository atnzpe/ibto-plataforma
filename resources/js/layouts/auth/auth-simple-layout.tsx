/*
 * Moldura das telas de ENTRAR, CADASTRAR, ESQUECI A SENHA etc. Com as cores e
 * o logo do IBTO (pedido do Gleyson, 10/10/2026): o logo largo do site no
 * topo, sobre a faixa escura, e o título da tela embaixo.
 */
import { Link } from '@inertiajs/react';
// Menu de idiomas (#53), no modo "dica do tradutor do navegador".
import MenuIdiomas from '@/components/menu-idiomas';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            {/* Idiomas (#53) no canto de cima. Entrar e cadastrar enviam
                formulário, que precisa sair do nosso domínio: por isso a dica
                do tradutor do navegador, e não o link do Google. */}
            <div className="absolute top-4 right-4">
                <MenuIdiomas modo="navegador" />
            </div>
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        {/* Logo largo do IBTO (o mesmo do site), sobre a faixa escura da marca. */}
                        <Link
                            href={home()}
                            className="flex w-full justify-center rounded-xl bg-[#0a0a0a] px-4 py-3"
                        >
                            <img
                                src="/images/ibto-logo.webp"
                                alt="IBTO — Instituto Brasileiro de Treinamento Operacional (página inicial)"
                                className="h-16 w-auto max-w-full object-contain"
                            />
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
