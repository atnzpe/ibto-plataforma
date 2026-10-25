/**
 * FOTO 3x4 DO FILIADO e AUTORIZAÇÃO DE IMAGEM (#78).
 *
 * Usado na ficha do filiado (Comando Geral e admin da equipe) e em
 * Configurações → Perfil (a própria pessoa, quando é filiada).
 * - A foto é a da carteirinha (#46). Enviar troca a antiga; "Tirar foto" apaga.
 * - A caixa "Autorizo o uso da minha foto na Rede Nacional do site" começa
 *   DESMARCADA (LGPD). Marcar grava a data; quem marcou fica na auditoria.
 * - FOTO DA REDE NACIONAL (#11, 23/10/2026): a foto personalizada (ex.: de uniforme)
 *   que aparece na página Rede Nacional do site, diferente da 3x4. Só aparece no site
 *   quem é monitor ou instrutor, está ativo e em dia, autorizou e enviou esta foto.
 * Acessível: botões com texto, caixa com rótulo clicável e erro ligado ao campo.
 */
import { router, usePage } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { useRef, useState } from 'react';
import type { RefObject } from 'react';
import FiliadoFotoController from '@/actions/App/Http/Controllers/Cadastros/FiliadoFotoController';
import FiliadoFotoRedeController from '@/actions/App/Http/Controllers/Cadastros/FiliadoFotoRedeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

/** O que a tela recebe do servidor sobre a foto do filiado. */
export type FotoDoFiliado = {
    // Número do filiado (para montar os endereços).
    id: number;
    // Endereço protegido da foto 3x4, ou null sem foto.
    foto_url: string | null;
    // true = autorizou a foto na Rede Nacional do site.
    autoriza_imagem_rede: boolean;
    // Quando autorizou. Ex.: '16/10/2026 14:30'; null se não autorizou.
    autoriza_imagem_em: string | null;
    // Endereço protegido da foto da Rede Nacional (#11), ou null sem foto.
    foto_rede_url?: string | null;
    // true = já aparece na página Rede Nacional do site (cumpre todas as regras).
    na_rede_nacional?: boolean;
};

/**
 * @param filiado Os dados da foto e da autorização (ver FotoDoFiliado).
 * @param primeiraPessoa true na tela da própria pessoa ("minha foto"); false na ficha do Comando Geral.
 * @returns A seção "Foto 3x4 e autorização de imagem".
 */
export default function FotoFiliado({
    filiado,
    primeiraPessoa = false,
}: {
    filiado: FotoDoFiliado;
    primeiraPessoa?: boolean;
}) {
    // Erros devolvidos pelo servidor (ex.: foto grande demais).
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    // Campo de arquivo escondido: o botão "Escolher foto 3x4" abre ele.
    const campo = useRef<HTMLInputElement>(null);
    // Campo de arquivo escondido da foto da Rede Nacional (o botão dela abre ele).
    const campoRede = useRef<HTMLInputElement>(null);
    // true enquanto envia, tira a foto ou grava a autorização (desliga os botões).
    const [ocupado, setOcupado] = useState(false);
    // Opções comuns a todos os envios: não pula a tela para o topo e controla o "ocupado".
    const opcoes = {
        preserveScroll: true,
        onStart: () => setOcupado(true),
        onFinish: () => setOcupado(false),
    };

    /**
     * Envia uma foto escolhida (a 3x4 ou a da Rede Nacional).
     *
     * @param arquivo A foto escolhida, ou undefined se a pessoa cancelou.
     * @param endereco Para onde enviar. Ex.: FiliadoFotoController.update.url(7).
     * @param origem O campo de arquivo usado (limpo no fim, para poder escolher a mesma foto de novo).
     */
    function enviar(
        arquivo: File | undefined,
        endereco: string,
        origem: RefObject<HTMLInputElement | null>,
    ) {
        // Cancelou: nada a enviar.
        if (!arquivo) {
            return;
        }

        router.post(
            endereco,
            { foto: arquivo },
            {
                ...opcoes,
                forceFormData: true,
                // Limpa o campo para poder escolher a mesma foto de novo.
                onFinish: () => {
                    setOcupado(false);

                    if (origem.current) {
                        origem.current.value = '';
                    }
                },
            },
        );
    }

    return (
        <section
            aria-labelledby="titulo-foto-3x4"
            className="space-y-3 rounded-md border border-border p-4"
        >
            <h2 id="titulo-foto-3x4" className="text-sm font-medium">
                Foto 3x4 do filiado
            </h2>

            <div className="flex flex-wrap items-center gap-4">
                {/* A foto em formato de documento; sem foto, um ícone neutro. */}
                <div className="flex h-28 w-24 items-center justify-center overflow-hidden rounded-md bg-neutral-200 dark:bg-neutral-700">
                    {filiado.foto_url ? (
                        <img
                            src={filiado.foto_url}
                            alt="Foto 3x4 do filiado"
                            className="size-full object-cover"
                        />
                    ) : (
                        <UserRound
                            className="size-10 text-neutral-500"
                            aria-hidden="true"
                        />
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <input
                        ref={campo}
                        id="foto-3x4"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        aria-describedby="foto-3x4-dica"
                        onChange={(evento) =>
                            enviar(
                                evento.target.files?.[0],
                                FiliadoFotoController.update.url(filiado.id),
                                campo,
                            )
                        }
                    />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={ocupado}
                        onClick={() => campo.current?.click()}
                    >
                        {filiado.foto_url
                            ? 'Trocar foto 3x4'
                            : 'Escolher foto 3x4'}
                    </Button>
                    {filiado.foto_url && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={ocupado}
                            onClick={() =>
                                router.delete(
                                    FiliadoFotoController.destroy.url(
                                        filiado.id,
                                    ),
                                    opcoes,
                                )
                            }
                        >
                            Tirar foto
                        </Button>
                    )}
                </div>
            </div>

            <p id="foto-3x4-dica" className="text-sm text-muted-foreground">
                Foto de rosto, fundo claro. JPG, PNG ou WebP, até 5 MB. É a foto
                da carteirinha.
            </p>

            <div role="alert">
                <InputError message={errors.foto} />
            </div>

            {/* Autorização de imagem (LGPD): desmarcada até a pessoa autorizar. */}
            <label className="flex min-h-11 cursor-pointer items-start gap-3 text-sm">
                <input
                    type="checkbox"
                    className="mt-0.5 size-5"
                    checked={filiado.autoriza_imagem_rede}
                    disabled={ocupado}
                    onChange={(evento) =>
                        router.put(
                            FiliadoFotoController.autorizacao.url(filiado.id),
                            { autoriza: evento.target.checked },
                            opcoes,
                        )
                    }
                />
                <span>
                    {primeiraPessoa
                        ? 'Autorizo o uso da minha foto na Rede Nacional do site do IBTO.'
                        : 'O filiado autorizou o uso da foto na Rede Nacional do site do IBTO (autorização assinada).'}
                    <span className="block text-muted-foreground">
                        {filiado.autoriza_imagem_em
                            ? `Autorizado em ${filiado.autoriza_imagem_em}.`
                            : 'Sem autorização, a foto fica só dentro da plataforma.'}
                    </span>
                </span>
            </label>

            {/* FOTO DA REDE NACIONAL (#11): a personalizada, que aparece no site. */}
            <div className="space-y-3 border-t border-border pt-4">
                <h3 className="text-sm font-medium">
                    Foto da Rede Nacional (site do IBTO)
                </h3>
                <div className="flex flex-wrap items-center gap-4">
                    {/* Em pé (3:4), como no cartão do site, e INTEIRA (object-contain: encaixa
                        sem cortar, nem o rosto nem os pés). Sem foto, um ícone neutro. */}
                    <div className="flex aspect-[3/4] w-24 items-center justify-center overflow-hidden rounded-md bg-neutral-200 dark:bg-neutral-700">
                        {filiado.foto_rede_url ? (
                            <img
                                src={filiado.foto_rede_url}
                                alt="Foto da Rede Nacional"
                                className="size-full object-contain"
                            />
                        ) : (
                            <UserRound
                                className="size-10 text-neutral-500"
                                aria-hidden="true"
                            />
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <input
                            ref={campoRede}
                            id="foto-rede"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="sr-only"
                            aria-describedby="foto-rede-dica"
                            onChange={(evento) =>
                                enviar(
                                    evento.target.files?.[0],
                                    FiliadoFotoRedeController.update.url(
                                        filiado.id,
                                    ),
                                    campoRede,
                                )
                            }
                        />
                        <Button
                            type="button"
                            variant="outline"
                            disabled={ocupado}
                            onClick={() => campoRede.current?.click()}
                        >
                            {filiado.foto_rede_url
                                ? 'Trocar foto da Rede Nacional'
                                : 'Escolher foto da Rede Nacional'}
                        </Button>
                        {/* Com foto: o botão de tirar (a pessoa sai do site). */}
                        {filiado.foto_rede_url && (
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={ocupado}
                                onClick={() =>
                                    router.delete(
                                        FiliadoFotoRedeController.destroy.url(
                                            filiado.id,
                                        ),
                                        opcoes,
                                    )
                                }
                            >
                                Tirar foto da Rede Nacional
                            </Button>
                        )}
                    </div>
                </div>
                <p
                    id="foto-rede-dica"
                    className="text-sm text-muted-foreground"
                >
                    Foto personalizada (ex.: de uniforme), recortada em
                    quadrado. JPG, PNG ou WebP, até 5 MB.
                </p>
                {/* Situação no site: aparece, ou o que falta para aparecer. */}
                <p className="text-sm" role="status">
                    {filiado.na_rede_nacional
                        ? 'Aparece na página Rede Nacional do site.'
                        : 'Ainda não aparece no site. Para aparecer: ser monitor ou instrutor, estar ativo e com a anuidade em dia, ter a UF na ficha, marcar a autorização acima e enviar esta foto.'}
                </p>
            </div>
        </section>
    );
}
