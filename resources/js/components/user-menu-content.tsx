/**
 * MENU DO USUÁRIO (clique no nome, embaixo à esquerda): Configurações, o
 * interruptor do tour guiado (#68) e Sair.
 * Textos em português (tradução das telas do kit do Laravel, 14/10/2026, antes do piloto).
 */
import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, CircleHelp, LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    // Tour guiado (#68): o interruptor da conta (ligado = cada tela mostra o seu tour uma vez).
    const tour = usePage<{ tour: { ativo: boolean } | null }>().props.tour;
    const tourLigado = tour?.ativo ?? false;

    /**
     * Liga ou desliga o tour guiado na conta (vale no computador e no celular).
     * Ao religar, as telas voltam a mostrar o tour uma vez.
     */
    const alternarTour = () => {
        router.put(
            '/settings/tour',
            { ativo: !tourLigado },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Configurações
                    </Link>
                </DropdownMenuItem>
                {/* Manual de instruções (#68, parte 2): o passo a passo de cada perfil. */}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href="/ajuda"
                        onClick={cleanup}
                    >
                        <BookOpen className="mr-2" />
                        Manual de instruções
                    </Link>
                </DropdownMenuItem>
                {/* Interruptor do tour: role="menuitemcheckbox" + aria-checked dizem ao
                    leitor de tela se ele está ligado ou desligado. */}
                <DropdownMenuItem
                    role="menuitemcheckbox"
                    aria-checked={tourLigado}
                    onSelect={alternarTour}
                    className="cursor-pointer"
                >
                    <CircleHelp className="mr-2" />
                    Tour guiado: {tourLigado ? 'ligado' : 'desligado'}
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Sair
                </Link>
            </DropdownMenuItem>
        </>
    );
}
