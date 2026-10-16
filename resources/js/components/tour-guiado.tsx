/**
 * TOUR GUIADO (#68): o botão "?" do topo da tela e a abertura automática.
 *
 * - Com o tour LIGADO na conta, cada tela mostra o seu tour sozinha uma vez
 *   (na primeira visita). Depois, só quando a pessoa clica no "?".
 * - O "?" funciona sempre, mesmo com o tour desligado.
 * - Ao fechar ou terminar, a tela é marcada como vista na conta (vale no
 *   computador e no celular).
 *
 * Os balões são da biblioteca driver.js (MIT). Os textos vêm de lib/tours.ts,
 * que são textos FIXOS do código: nenhum texto digitado por usuário entra no
 * balão (o driver.js desenha o texto como HTML, então isso importa).
 * Teclado: setas avançam/voltam e Esc fecha.
 */
import { router, usePage } from '@inertiajs/react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { CircleHelp } from 'lucide-react';
import { useCallback, useEffect, useRef } from 'react';
import { abreSozinho, passosDaTela, temTour } from '@/lib/tours';

// Os dados do tour que o servidor manda para toda tela com login (HandleInertiaRequests).
type DadosDoTour = { ativo: boolean; vistos: string[] } | null;

// Espera antes de abrir sozinho (milissegundos): a tela termina de desenhar
// e os elementos que o tour aponta já existem.
const ESPERA_PARA_ABRIR_MS = 700;

/**
 * O botão "?" e a abertura automática do tour da tela atual.
 *
 * @returns O botão "?" (só nas telas que têm tour), ou nada.
 */
export function TourGuiado() {
    // page.component é o nome da tela. Ex.: 'comando-geral' ou 'cursos/matriculas'.
    const pagina = usePage<{ tour: DadosDoTour }>();
    const tela = pagina.component;
    const dados = pagina.props.tour;
    // A última tela cujo tour abriu sozinho: evita abrir duas vezes na mesma visita.
    const ultimaAberta = useRef<string | null>(null);

    /**
     * Abre o tour da tela atual.
     */
    const abrir = useCallback(() => {
        // Primeiro tour da vida da pessoa: entra a apresentação do menu, idioma e conta.
        const primeiroTour = (dados?.vistos.length ?? 0) === 0;
        const passos = passosDaTela(
            tela,
            primeiroTour,
            (seletor) => document.querySelector(seletor) !== null,
        );
        // Sem passos (tela sem tour): não abre nada.
        if (passos.length === 0) {
            return;
        }

        const tour = driver({
            showProgress: true,
            // Textos dos botões em português (o padrão da biblioteca é inglês).
            progressText: '{{current}} de {{total}}',
            nextBtnText: 'Próximo',
            prevBtnText: 'Voltar',
            doneBtnText: 'Concluir',
            // Teclado: setas e Esc (acessibilidade).
            allowKeyboardControl: true,
            // Pode fechar pelo "x", pelo Esc ou clicando fora do balão (a tela é marcada como vista).
            allowClose: true,
            popoverClass: 'tour-ibto',
            steps: passos.map((passo) => ({
                element: passo.elemento,
                popover: { title: passo.titulo, description: passo.texto },
            })),
            // Fechou ou terminou: marca a tela como vista na conta (sem recarregar a tela).
            onDestroyed: () => {
                // Sem login (dados nulos): não há conta onde guardar.
                if (!dados) {
                    return;
                }
                router.post(
                    '/settings/tour/vistos',
                    { tela },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        only: ['tour'],
                    },
                );
            },
        });
        tour.drive();
    }, [dados, tela]);

    // Ao chegar numa tela: abre sozinho se o tour está ligado e a tela ainda não foi vista.
    useEffect(() => {
        // Sem login, tour desligado, tela sem tour, já vista ou já aberta nesta visita: não abre.
        if (
            !dados ||
            ultimaAberta.current === tela ||
            !abreSozinho(tela, dados.ativo, dados.vistos)
        ) {
            return;
        }
        ultimaAberta.current = tela;
        const relogio = window.setTimeout(abrir, ESPERA_PARA_ABRIR_MS);

        // Saiu da tela antes do tempo: cancela a abertura.
        return () => window.clearTimeout(relogio);
    }, [abrir, dados, tela]);

    // Tela sem tour: sem botão.
    if (!temTour(tela)) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={abrir}
            data-tour="ajuda"
            // aria-label: o leitor de tela fala o nome do botão (o "?" sozinho não diz nada).
            aria-label="Ajuda: ver o tour desta tela"
            title="Ajuda: ver o tour desta tela"
            className="inline-flex size-11 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-2 focus-visible:outline-ring"
        >
            <CircleHelp className="size-5" aria-hidden="true" />
        </button>
    );
}
