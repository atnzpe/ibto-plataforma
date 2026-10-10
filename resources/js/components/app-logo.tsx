/**
 * MARCA no topo do menu lateral (pedido do Gleyson, 10/10/2026: cores e logo
 * do IBTO): o emblema redondo + "IBTO" na fonte do site e, embaixo, o nome
 * da plataforma. Com o menu recolhido, só o emblema aparece.
 */
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * @returns O emblema e o nome, lado a lado.
 */
export default function AppLogo() {
    return (
        <>
            <AppLogoIcon className="size-8 shrink-0" />
            <div className="ml-1 grid flex-1 text-left">
                {/* "IBTO" na fonte Audiowide (a do logo do site), em dourado. */}
                <span className="truncate font-display text-base leading-tight text-sidebar-primary">
                    IBTO
                </span>
                <span className="truncate text-xs leading-tight text-sidebar-foreground/70">
                    Treinamento Operacional
                </span>
            </div>
        </>
    );
}
