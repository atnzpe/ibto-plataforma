import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            // SafeArea: distância da borda (24 px no computador, 16 px no celular,
            // que são os padrões do sonner) + a margem do entalhe e da barra de gestos.
            offset={{
                bottom: 'calc(24px + env(safe-area-inset-bottom))',
                right: 'calc(24px + env(safe-area-inset-right))',
            }}
            mobileOffset={{
                bottom: 'calc(16px + env(safe-area-inset-bottom))',
                right: 'calc(16px + env(safe-area-inset-right))',
                left: 'calc(16px + env(safe-area-inset-left))',
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
