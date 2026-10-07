import { Breadcrumbs } from '@/components/breadcrumbs';
// Menu de idiomas (#53), no modo "dica do tradutor do navegador".
import MenuIdiomas from '@/components/menu-idiomas';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {/* Idiomas (#53), encostado à direita (ml-auto). Tela com login:
                dica do tradutor do navegador, que não tira o login. */}
            <div className="ml-auto">
                <MenuIdiomas modo="navegador" />
            </div>
        </header>
    );
}
