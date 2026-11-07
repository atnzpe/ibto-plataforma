/**
 * CAMPO DE SENHA com o botão de olho DENTRO da caixa, para mostrar ou esconder
 * o que foi digitado. Usado em todas as telas que pedem senha (entrar, cadastro,
 * convite, trocar e redefinir a senha, confirmar a senha e anonimizar filiado).
 * Textos em português (tradução das telas do kit do Laravel, 14/10/2026, antes do piloto).
 *
 * Acessibilidade (07/11/2026): o olho agora pode ser alcançado pela tecla Tab
 * (antes era pulado, e quem não usa mouse não conseguia mostrar a senha) e avisa
 * ao leitor de tela se a senha está à mostra ("aria-pressed").
 */
import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Caixa de senha com o olho.
 *
 * @param className Classes de estilo extras para a caixa. Ex.: 'mt-1'.
 * @param ref Referência para a caixa, quando a tela precisa dar foco nela.
 * @param props Os demais atributos de uma caixa comum (id, name, required...),
 *              menos o "type", que este componente controla. Ex.: { id: 'password', name: 'password' }.
 * @returns A caixa de senha com o botão de olho no canto direito.
 */
export default function PasswordInput({
    className,
    ref,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & { ref?: Ref<HTMLInputElement> }) {
    // Guarda se a senha está à mostra (true) ou escondida (false, o começo).
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="relative">
            {/* A caixa troca de "password" (bolinhas) para "text" (letras) conforme o olho.
                O espaço à direita (pr-10) evita que o texto passe por baixo do botão. */}
            <Input
                type={showPassword ? 'text' : 'password'}
                className={cn('pr-10', className)}
                ref={ref}
                {...props}
            />
            {/* O botão de olho: cada toque inverte mostrar/esconder. "type=button" impede
                que ele envie o formulário. Sem tabIndex=-1: a tecla Tab chega até ele. */}
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                // Nome falado pelo leitor de tela, conforme o que o toque vai fazer.
                aria-label={
                    showPassword ? 'Esconder a senha' : 'Mostrar a senha'
                }
                // Diz ao leitor de tela se o botão está "ligado" (senha à mostra).
                aria-pressed={showPassword}
            >
                {/* Olho riscado quando a senha está à mostra; olho aberto quando escondida. */}
                {showPassword ? (
                    <EyeOff className="size-4" aria-hidden="true" />
                ) : (
                    <Eye className="size-4" aria-hidden="true" />
                )}
            </button>
        </div>
    );
}
