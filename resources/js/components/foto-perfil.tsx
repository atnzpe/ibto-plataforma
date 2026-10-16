/**
 * FOTO DE PERFIL (#78), em Configurações → Perfil.
 *
 * Mostra a foto atual (ou as iniciais), um botão para escolher uma foto nova
 * (JPG, PNG ou WebP até 5 MB) e um para tirar a foto. Ao escolher, a foto já é
 * enviada: a plataforma recorta em quadrado, reduz para 400 px e grava em pasta
 * privada. A foto aparece no menu da conta, nos inscritos e na chamada (ajuda o
 * instrutor a reconhecer o aluno). Acessível: botões com texto, foco visível,
 * mensagem de erro ligada ao campo e aviso para leitor de tela.
 */
import { router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import FotoController from '@/actions/App/Http/Controllers/Settings/FotoController';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import type { Auth } from '@/types';

/**
 * Componente da foto de perfil. Não recebe argumentos: lê a conta logada da página.
 *
 * @returns A seção "Foto" pronta para a tela de perfil.
 */
export default function FotoPerfil() {
    // A conta logada (nome e avatar) e os erros que o servidor devolveu (ex.: foto grande demais).
    const { auth, errors } = usePage<{
        auth: Auth;
        errors: Record<string, string>;
    }>().props;
    // Gera as iniciais do nome. Ex.: 'Gleyson Silva' → 'GS'.
    const getInitials = useInitials();
    // Referência ao campo de arquivo escondido: o botão "Escolher foto" abre ele.
    const campo = useRef<HTMLInputElement>(null);
    // true enquanto a foto está sendo enviada ou tirada (desliga os botões).
    const [enviando, setEnviando] = useState(false);

    /**
     * Envia a foto escolhida. Chamada quando a pessoa escolhe um arquivo.
     *
     * @param arquivo A foto escolhida no computador ou no celular, ou undefined se cancelou.
     */
    function enviar(arquivo: File | undefined) {
        // Cancelou a escolha: nada a enviar.
        if (!arquivo) {
            return;
        }

        router.post(
            FotoController.update.url(),
            { foto: arquivo },
            {
                // Arquivo precisa ir como formulário com anexo (multipart).
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setEnviando(true),
                // Limpa o campo no fim, para poder escolher a mesma foto de novo se quiser.
                onFinish: () => {
                    setEnviando(false);

                    if (campo.current) {
                        campo.current.value = '';
                    }
                },
            },
        );
    }

    /**
     * Tira a foto (volta para as iniciais).
     */
    function tirar() {
        router.delete(FotoController.destroy.url(), {
            preserveScroll: true,
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
        });
    }

    return (
        <section aria-labelledby="titulo-foto" className="space-y-3">
            <h2 id="titulo-foto" className="text-sm font-medium">
                Foto
            </h2>

            <div className="flex flex-wrap items-center gap-4">
                {/* Foto atual em tamanho maior; sem foto, as iniciais do nome. */}
                <Avatar className="size-20 overflow-hidden rounded-full">
                    <AvatarImage
                        src={auth.user.avatar ?? undefined}
                        alt={`Foto de ${auth.user.name}`}
                    />
                    <AvatarFallback className="rounded-full bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="flex flex-wrap gap-2">
                    {/* Campo de arquivo escondido; o botão abaixo abre a escolha. */}
                    <input
                        ref={campo}
                        id="foto"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        aria-describedby="foto-dica"
                        onChange={(evento) => enviar(evento.target.files?.[0])}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={enviando}
                        onClick={() => campo.current?.click()}
                    >
                        {enviando
                            ? 'Enviando…'
                            : auth.user.avatar
                              ? 'Trocar foto'
                              : 'Escolher foto'}
                    </Button>

                    {/* "Tirar foto" só aparece quando há uma foto. */}
                    {auth.user.avatar && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={enviando}
                            onClick={tirar}
                        >
                            Tirar foto
                        </Button>
                    )}
                </div>
            </div>

            <p id="foto-dica" className="text-sm text-muted-foreground">
                JPG, PNG ou WebP, até 5 MB. A foto é recortada em quadrado. Só a
                veem você, o Comando Geral, o admin da sua equipe e os
                instrutores dos seus cursos.
            </p>

            {/* Erro do servidor (tipo ou tamanho), lido pelo leitor de tela. */}
            <div role="alert">
                <InputError message={errors.foto} />
            </div>
        </section>
    );
}
