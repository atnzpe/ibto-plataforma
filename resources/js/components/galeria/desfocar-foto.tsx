/**
 * EDITOR DE DESFOQUE (#90, pedido do Gleyson em 17/10/2026): abre a foto numa
 * janela e deixa marcar retângulos sobre o que deve sumir (rosto, distintivo,
 * placa). No mouse ou no dedo: arraste para desenhar. No teclado: "Nova área"
 * cria uma no centro; as setas movem a área escolhida e Shift + setas mudam o
 * tamanho. "Salvar" manda as áreas para o servidor, que desfoca de verdade.
 * As áreas ficam em frações da foto (0 a 1), então valem em qualquer tamanho de tela.
 */
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { Button } from '@/components/ui/button';
import { desfocar, restaurar } from '@/routes/comando-geral/galeria/fotos';

/** Uma área marcada, em frações da foto. Ex.: { x: 0.1, y: 0.2, w: 0.15, h: 0.2 }. */
export type Area = { x: number; y: number; w: number; h: number };

/** Prende um número entre 0 e 1 (a área não sai da foto). */
const prender = (n: number) => Math.min(1, Math.max(0, n));

/**
 * @param fotoId O número da foto. Ex.: 12.
 * @param url O endereço da foto (a versão atual).
 * @param descricao O texto alternativo da foto.
 * @param areasIniciais As áreas já desfocadas antes ([] = nenhuma).
 * @param temOriginal Se há o original guardado (mostra "Restaurar original").
 * @returns O botão "Desfocar áreas" e a janela do editor.
 */
export default function DesfocarFoto({
    fotoId,
    url,
    descricao,
    areasIniciais,
    temOriginal,
}: {
    fotoId: number;
    url: string;
    descricao: string;
    areasIniciais: Area[];
    temOriginal: boolean;
}) {
    // A janela aberta ou fechada; as áreas marcadas; qual está escolhida (para o teclado).
    const [aberto, setAberto] = useState(false);
    const [areas, setAreas] = useState<Area[]>(areasIniciais);
    const [escolhida, setEscolhida] = useState<number | null>(null);
    const [enviando, setEnviando] = useState(false);
    // A moldura da foto (para converter a posição do mouse em fração) e o ponto onde o arraste começou.
    const moldura = useRef<HTMLDivElement>(null);
    const inicio = useRef<{ x: number; y: number } | null>(null);

    /**
     * Converte a posição do mouse/dedo em frações da foto.
     *
     * @param evento O evento do ponteiro.
     * @returns { x, y } entre 0 e 1.
     */
    function posicao(evento: PointerEvent<HTMLDivElement>) {
        const caixa = moldura.current!.getBoundingClientRect();

        return {
            x: prender((evento.clientX - caixa.left) / caixa.width),
            y: prender((evento.clientY - caixa.top) / caixa.height),
        };
    }

    // Começa a desenhar: guarda o ponto e cria uma área vazia nele.
    function aoApertar(evento: PointerEvent<HTMLDivElement>) {
        evento.currentTarget.setPointerCapture(evento.pointerId);
        const p = posicao(evento);
        inicio.current = p;
        setAreas((atuais) => [...atuais, { x: p.x, y: p.y, w: 0, h: 0 }]);
        setEscolhida(areas.length);
    }

    // Arrastando: a última área cresce até o ponto atual (em qualquer direção).
    function aoMover(evento: PointerEvent<HTMLDivElement>) {
        // Sem arraste em andamento, não faz nada.
        if (!inicio.current) {
            return;
        }
        const p = posicao(evento);
        const a = inicio.current;
        setAreas((atuais) => [
            ...atuais.slice(0, -1),
            {
                x: Math.min(a.x, p.x),
                y: Math.min(a.y, p.y),
                w: Math.abs(p.x - a.x),
                h: Math.abs(p.y - a.y),
            },
        ]);
    }

    // Soltou: área pequena demais (um clique sem arrastar) é descartada.
    function aoSoltar() {
        inicio.current = null;
        setAreas((atuais) => {
            const ultima = atuais[atuais.length - 1];
            // Menor que 1% da foto: foi só um toque, tira.
            if (ultima && (ultima.w < 0.01 || ultima.h < 0.01)) {
                setEscolhida(null);

                return atuais.slice(0, -1);
            }

            return atuais;
        });
    }

    // Teclado: setas movem a área escolhida; Shift + setas mudam o tamanho; Delete apaga.
    function aoTeclar(evento: KeyboardEvent<HTMLDivElement>) {
        // Nenhuma área escolhida: as teclas seguem o comportamento normal da página.
        if (escolhida === null || !areas[escolhida]) {
            return;
        }
        const passo = 0.01;
        const mudar: Record<string, [number, number]> = {
            ArrowLeft: [-passo, 0],
            ArrowRight: [passo, 0],
            ArrowUp: [0, -passo],
            ArrowDown: [0, passo],
        };
        if (evento.key === 'Delete' || evento.key === 'Backspace') {
            evento.preventDefault();
            tirar(escolhida);

            return;
        }
        const delta = mudar[evento.key];
        // Outra tecla: não é com o editor.
        if (!delta) {
            return;
        }
        evento.preventDefault();
        setAreas((atuais) =>
            atuais.map((a, i) => {
                if (i !== escolhida) {
                    return a;
                }

                return evento.shiftKey
                    ? {
                          ...a,
                          w: prender(Math.max(0.01, a.w + delta[0])),
                          h: prender(Math.max(0.01, a.h + delta[1])),
                      }
                    : {
                          ...a,
                          x: prender(Math.min(1 - a.w, a.x + delta[0])),
                          y: prender(Math.min(1 - a.h, a.y + delta[1])),
                      };
            }),
        );
    }

    /**
     * Tira uma área da lista.
     *
     * @param indice A posição da área. Ex.: 0.
     */
    function tirar(indice: number) {
        setAreas((atuais) => atuais.filter((_, i) => i !== indice));
        setEscolhida(null);
    }

    // Cria uma área no centro (para quem usa só o teclado) e já a escolhe.
    function novaArea() {
        setAreas((atuais) => [...atuais, { x: 0.4, y: 0.4, w: 0.2, h: 0.2 }]);
        setEscolhida(areas.length);
    }

    // Manda as áreas para o servidor. Sem áreas e com original guardado: restaura.
    function salvar() {
        setEnviando(true);
        const opcoes = {
            preserveScroll: true,
            onFinish: () => setEnviando(false),
            onSuccess: () => setAberto(false),
        };
        if (areas.length === 0) {
            router.delete(restaurar(fotoId).url, opcoes);

            return;
        }
        router.post(desfocar(fotoId).url, { areas }, opcoes);
    }

    return (
        <>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                data-tour="desfocar"
                className="min-h-11"
                onClick={() => setAberto(true)}
            >
                Desfocar áreas
                {areasIniciais.length > 0 ? ` (${areasIniciais.length})` : ''}
            </Button>

            {aberto && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby={`t-desfoque-${fotoId}`}
                    className="dialog-safe fixed inset-0 z-50 overflow-y-auto bg-black/80 p-4"
                >
                    <div className="mx-auto flex max-w-4xl flex-col gap-3 rounded-lg bg-background p-4">
                        <h2
                            id={`t-desfoque-${fotoId}`}
                            className="text-lg font-semibold"
                        >
                            Desfocar áreas da foto
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Arraste sobre o rosto, o distintivo ou a placa. No
                            teclado: "Nova área", setas movem, Shift + setas
                            mudam o tamanho, Delete apaga.
                        </p>

                        {/* A foto com as áreas por cima. touch-none: no celular, arrastar desenha em vez de rolar a página. */}
                        <div
                            ref={moldura}
                            tabIndex={0}
                            aria-label="Foto para marcar as áreas"
                            onPointerDown={aoApertar}
                            onPointerMove={aoMover}
                            onPointerUp={aoSoltar}
                            onKeyDown={aoTeclar}
                            className="relative cursor-crosshair touch-none outline-offset-2 select-none focus-visible:outline-2"
                        >
                            <img
                                src={url}
                                alt={descricao}
                                className="block w-full"
                                draggable={false}
                            />
                            {areas.map((a, i) => (
                                <div
                                    key={i}
                                    aria-hidden="true"
                                    className={`absolute border-2 bg-white/30 backdrop-blur-md ${i === escolhida ? 'border-yellow-400' : 'border-red-500'}`}
                                    style={{
                                        left: `${a.x * 100}%`,
                                        top: `${a.y * 100}%`,
                                        width: `${a.w * 100}%`,
                                        height: `${a.h * 100}%`,
                                    }}
                                />
                            ))}
                        </div>

                        {/* A lista das áreas: escolher (para o teclado) ou tirar. */}
                        <ul className="flex flex-wrap gap-2 text-sm">
                            {areas.map((_, i) => (
                                <li
                                    key={i}
                                    className="flex items-center gap-1 rounded border px-2"
                                >
                                    <button
                                        type="button"
                                        className="min-h-11 underline-offset-4 hover:underline"
                                        onClick={() => setEscolhida(i)}
                                        aria-pressed={i === escolhida}
                                    >
                                        Área {i + 1}
                                    </button>
                                    <button
                                        type="button"
                                        className="min-h-11 px-2 text-red-700"
                                        onClick={() => tirar(i)}
                                        aria-label={`Tirar a área ${i + 1}`}
                                    >
                                        ✕
                                    </button>
                                </li>
                            ))}
                        </ul>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                className="min-h-11"
                                onClick={novaArea}
                            >
                                Nova área
                            </Button>
                            <Button
                                type="button"
                                className="min-h-11"
                                onClick={salvar}
                                disabled={
                                    enviando ||
                                    (areas.length === 0 && !temOriginal)
                                }
                            >
                                {areas.length === 0 && temOriginal
                                    ? 'Salvar (volta a foto original)'
                                    : 'Salvar desfoque'}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                className="min-h-11"
                                onClick={() => setAberto(false)}
                            >
                                Cancelar
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
