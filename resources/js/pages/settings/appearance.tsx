/**
 * CONFIGURAÇÕES · APARÊNCIA: tema claro, escuro ou o do aparelho.
 * Textos em português (tradução das telas do kit do Laravel, 14/10/2026, antes do piloto).
 */
import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Aparência" />

            <h1 className="sr-only">Aparência</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Aparência"
                    description="Escolha o tema da plataforma neste aparelho"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Aparência',
            href: editAppearance(),
        },
    ],
};
