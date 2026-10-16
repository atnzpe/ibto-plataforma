/**
 * FOTO DE UMA PESSOA numa lista (#78): chamada e inscritos do curso.
 *
 * Mostra a foto de perfil (endereço protegido, que só abre para quem pode ver)
 * ou, sem foto, um ícone neutro. Nessas listas o nome não aparece (LGPD: só o
 * registro e a equipe), por isso o ícone, e não as iniciais.
 */
import { UserRound } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

/**
 * @param foto Endereço da foto. Ex.: '/fotos/usuarios/12?v=1760630400'; null = sem foto ou sem permissão.
 * @param rotulo Texto para leitor de tela. Ex.: 'Foto do registro IBTO-0042'.
 * @returns O círculo com a foto ou o ícone.
 */
export default function FotoPessoa({
    foto,
    rotulo,
}: {
    foto: string | null;
    rotulo: string;
}) {
    return (
        <Avatar className="size-10 shrink-0 overflow-hidden rounded-full">
            {/* Com foto: a imagem; se não carregar, cai no ícone abaixo. */}
            {foto && <AvatarImage src={foto} alt={rotulo} />}
            {/* Sem foto: ícone neutro, escondido do leitor de tela (não traz informação). */}
            <AvatarFallback className="rounded-full bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                <UserRound className="size-5" aria-hidden="true" />
            </AvatarFallback>
        </Avatar>
    );
}
