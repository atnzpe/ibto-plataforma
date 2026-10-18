import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    // O grupo do menu lateral (18/10/2026): 'inicio', 'minha', 'ensino', 'pessoas',
    // 'financeiro', 'comunicacao' ou 'cadastros'. Sem grupo: entra em "Plataforma".
    grupo?: string;
};
