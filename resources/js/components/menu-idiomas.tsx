/**
 * Menu de idiomas com bandeira e nome (#53). Tem dois modos:
 * - 'google' (telas públicas): cada idioma estrangeiro abre a mesma página
 *   traduzida pelo Google Tradutor (endereço translate.goog); o português
 *   volta para a página original.
 * - 'navegador' (telas com login): explica como usar o tradutor do próprio
 *   navegador. O script antigo do Google, testado em 07/10/2026, não traduziu
 *   as telas em React, e o link do Google faria a pessoa perder o login.
 * Usa <details>/<summary>, que o próprio navegador abre e fecha: funciona pelo
 * teclado (Tab e Enter) mesmo sem nenhum código extra. O código abaixo só
 * fecha o menu ao clicar fora ou ao apertar Esc.
 * Mesmo método do site da CBSA; a lógica dos links está em lib/traducao.ts.
 */
import { useEffect, useRef } from 'react';
import {
    IDIOMAS,
    buscaSemGoogle,
    hostOriginal,
    idiomaAtual,
    linkTraducao,
} from '@/lib/traducao';
import type { CodigoIdioma } from '@/lib/traducao';

// Bandeira de cada idioma, desenhada em SVG (formato 3:2, simplificada para
// ficar nítida pequena). Desenho dentro do código: não depende de arquivo de
// imagem, então não quebra na página traduzida pelo Google.
// pt = Brasil, en = Estados Unidos, es = Espanha (mesmos desenhos da CBSA).
const BANDEIRAS: Record<CodigoIdioma, React.ReactNode> = {
    // Brasil: fundo verde, losango amarelo, círculo azul com a faixa branca.
    pt: (
        <>
            <rect width="30" height="20" fill="#009c3b" />
            <path d="M15 2 28 10 15 18 2 10z" fill="#ffdf00" />
            <circle cx="15" cy="10" r="4.6" fill="#002776" />
            <path
                d="M10.6 9.1q4.6-1.2 8.8 1.6"
                stroke="#fff"
                strokeWidth=".9"
                fill="none"
            />
        </>
    ),
    // Estados Unidos: listras vermelhas e brancas, quadrado azul com estrelas (pontos).
    en: (
        <>
            <rect width="30" height="20" fill="#fff" />
            <path
                d="M0 0h30v1.54H0zm0 3.08h30v1.54H0zm0 3.08h30v1.54H0zm0 3.08h30v1.54H0zm0 3.08h30v1.54H0zm0 3.08h30v1.54H0zm0 3.08h30v1.54H0z"
                fill="#b22234"
            />
            <rect width="12" height="10.77" fill="#3c3b6e" />
            <g fill="#fff">
                <circle cx="2" cy="2" r=".6" />
                <circle cx="6" cy="2" r=".6" />
                <circle cx="10" cy="2" r=".6" />
                <circle cx="4" cy="5.4" r=".6" />
                <circle cx="8" cy="5.4" r=".6" />
                <circle cx="2" cy="8.8" r=".6" />
                <circle cx="6" cy="8.8" r=".6" />
                <circle cx="10" cy="8.8" r=".6" />
            </g>
        </>
    ),
    // Espanha: vermelho, amarelo (faixa do meio, mais larga) e vermelho.
    es: (
        <>
            <rect width="30" height="20" fill="#aa151b" />
            <rect y="5" width="30" height="10" fill="#f1bf00" />
        </>
    ),
};

/**
 * Desenha a bandeira de um idioma. É decorativa (aria-hidden): quem usa leitor
 * de tela ouve o nome do idioma, que está escrito ao lado.
 *
 * @param codigo Código do idioma. Ex.: 'es'
 * @returns O desenho <svg> da bandeira, com 21 x 14 pixels.
 */
function Bandeira({ codigo }: { codigo: CodigoIdioma }) {
    return (
        <svg
            viewBox="0 0 30 20"
            aria-hidden="true"
            focusable="false"
            className="h-3.5 w-[21px] shrink-0 rounded-[2px]"
        >
            {BANDEIRAS[codigo]}
        </svg>
    );
}

// Dica do modo "navegador", escrita nos três idiomas (cada pessoa lê a sua).
// Chrome e Edge oferecem "Traduzir" no clique com o botão direito do mouse;
// no celular, ficam no menu de três pontinhos.
const DICAS_NAVEGADOR: readonly { codigo: CodigoIdioma; texto: string }[] = [
    {
        codigo: 'pt',
        texto: 'Para traduzir esta tela, use o tradutor do seu navegador (Chrome ou Edge: botão direito do mouse → Traduzir).',
    },
    {
        codigo: 'en',
        texto: "To translate this screen, use your browser's translator (Chrome or Edge: right-click → Translate).",
    },
    {
        codigo: 'es',
        texto: 'Para traducir esta pantalla, usa el traductor de tu navegador (Chrome o Edge: clic derecho → Traducir).',
    },
];

/** Opções do menu de idiomas. */
type MenuIdiomasProps = {
    // 'google' (padrão): links para a página traduzida pelo Google; só para
    //   telas públicas, porque na página do Google a pessoa perde o login.
    // 'navegador': dica para usar o tradutor do navegador, que mantém o login;
    //   para as telas com login e as de entrar/cadastrar (o formulário precisa
    //   ser enviado do nosso domínio).
    modo?: 'google' | 'navegador';
};

/**
 * O menu de idiomas: um botão que abre a lista dos três idiomas.
 * Lê o endereço da página atual direto no navegador.
 *
 * @param modo 'google' ou 'navegador' (ver MenuIdiomasProps). Ex.: <MenuIdiomas modo="navegador" />
 * @returns O menu pronto para o cabeçalho de uma tela.
 */
export default function MenuIdiomas({ modo = 'google' }: MenuIdiomasProps) {
    // Referência ao <details>, para o código conseguir fechá-lo.
    const menu = useRef<HTMLDetailsElement>(null);

    // Endereço da página atual (lido no navegador; a plataforma não usa SSR).
    const { hostname, host, pathname, search, protocol } = window.location;

    // Idioma em que a página está agora: 'pt' no nosso site; na do Google, o escolhido.
    const atual = idiomaAtual(hostname, search);

    // O NOSSO domínio, mesmo quando a pessoa está na página do Google.
    // No nosso site mantém a porta (ex.: "localhost:8000"); na do Google, desfaz o apelido.
    const nossoHost = atual === 'pt' ? host : hostOriginal(hostname);

    // Caminho da página sem as sobras da tradução (_x_tr_...). Ex.: "/cursos?aba=2".
    const caminho = `${pathname}${buscaSemGoogle(search)}`;

    // Dados do idioma atual, para mostrar no botão (bandeira + nome).
    const idiomaDoBotao =
        IDIOMAS.find((idioma) => idioma.codigo === atual) ?? IDIOMAS[0];

    // Fecha o menu ao clicar fora dele ou ao apertar Esc (bom para quem usa teclado).
    useEffect(() => {
        // Clique em qualquer lugar: se não foi dentro do menu, fecha.
        const aoClicar = (evento: MouseEvent) => {
            if (menu.current && !menu.current.contains(evento.target as Node)) {
                menu.current.open = false;
            }
        };

        // Esc com o menu aberto: fecha e devolve o foco ao botão.
        const aoTeclar = (evento: KeyboardEvent) => {
            // Outra tecla, ou menu já fechado: não faz nada.
            if (evento.key !== 'Escape' || !menu.current?.open) {
                return;
            }

            menu.current.open = false;
            menu.current.querySelector('summary')?.focus();
        };

        document.addEventListener('click', aoClicar);
        document.addEventListener('keydown', aoTeclar);

        // Ao sair da tela, desliga os "ouvintes" para não ficarem acumulando.
        return () => {
            document.removeEventListener('click', aoClicar);
            document.removeEventListener('keydown', aoTeclar);
        };
    }, []);

    /**
     * Monta o link de um idioma.
     *
     * @param codigo Idioma de destino. Ex.: 'en'
     * @returns Português: a página original; outro: a página traduzida pelo Google.
     */
    const linkDo = (codigo: CodigoIdioma): string => {
        // Português: a página original no nosso domínio. Na página do Google o
        // endereço é sempre https; no nosso site, usa o mesmo de agora (http no computador).
        if (codigo === 'pt') {
            const esquema = atual === 'pt' ? protocol : 'https:';

            return `${esquema}//${nossoHost}${caminho}`;
        }

        // Outro idioma: a mesma página, traduzida pelo Google.
        return linkTraducao(codigo, nossoHost, caminho);
    };

    /**
     * Leva ao idioma escolhido. Na página traduzida, o Google "embrulha" os links
     * num endereço dele, que não troca de idioma direito; por isso o clique vai
     * direto ao destino que nós montamos (o mesmo cuidado do site da CBSA).
     *
     * @param evento O clique no link. Ex.: clique em "English"
     * @param destino Endereço montado por linkDo. Ex.: 'https://cursos-ib--to-org.translate.goog/?...'
     */
    const irPara = (
        evento: React.MouseEvent<HTMLAnchorElement>,
        destino: string,
    ) => {
        // Impede o navegador de seguir o link (talvez já trocado pelo Google)...
        evento.preventDefault();
        // ...e abre o endereço certo.
        window.location.assign(destino);
    };

    // Modo "navegador" (telas com login e as de entrar/cadastrar): em vez de
    // links, o menu explica como usar o tradutor do próprio navegador, que
    // traduz sem sair da página e por isso mantém o login. Usa as cores do
    // tema da plataforma (claro ou escuro), e não as da página inicial.
    if (modo === 'navegador') {
        return (
            <details ref={menu} className="relative" translate="no">
                <summary
                    className="flex cursor-pointer list-none items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm text-foreground hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                    aria-label="Idioma / Language / Idioma"
                >
                    <Bandeira codigo="pt" />
                    <span>Idioma · Language</span>
                    <span aria-hidden="true" className="text-xs">
                        ▾
                    </span>
                </summary>

                <div className="absolute right-0 z-50 mt-2 w-72 space-y-3 rounded-md border border-border bg-popover p-3 text-sm text-popover-foreground shadow-lg">
                    {DICAS_NAVEGADOR.map((dica) => (
                        // lang: o leitor de tela lê cada dica com a pronúncia do idioma dela.
                        <p
                            key={dica.codigo}
                            lang={dica.codigo === 'pt' ? 'pt-BR' : dica.codigo}
                            className="flex gap-2"
                        >
                            <Bandeira codigo={dica.codigo} />
                            <span>{dica.texto}</span>
                        </p>
                    ))}
                </div>
            </details>
        );
    }

    // Modo "google" (padrão, telas públicas): links para a página traduzida.
    return (
        // translate="no": o Google não traduz o próprio menu (os nomes já estão
        // escritos no idioma de cada um).
        <details ref={menu} className="relative" translate="no">
            <summary
                className="flex cursor-pointer list-none items-center gap-2 rounded-sm border border-[#c9bd94]/40 px-3 py-1.5 text-sm text-[#f4f1e8] hover:border-[#c9bd94] focus-visible:outline-2 focus-visible:outline-[#c9bd94] [&::-webkit-details-marker]:hidden"
                aria-label={`Idioma / Language / Idioma: ${idiomaDoBotao.nome}`}
            >
                <Bandeira codigo={idiomaDoBotao.codigo} />
                <span>{idiomaDoBotao.nome}</span>
                <span aria-hidden="true" className="text-xs text-[#c9bd94]">
                    ▾
                </span>
            </summary>

            <ul className="absolute right-0 z-10 mt-2 min-w-40 rounded-md border border-[#c9bd94]/40 bg-black py-1 shadow-lg">
                {IDIOMAS.map((idioma) => (
                    <li key={idioma.codigo}>
                        <a
                            href={linkDo(idioma.codigo)}
                            onClick={(evento) =>
                                irPara(evento, linkDo(idioma.codigo))
                            }
                            lang={
                                idioma.codigo === 'pt' ? 'pt-BR' : idioma.codigo
                            }
                            aria-label={idioma.aviso}
                            // Marca o idioma atual para o leitor de tela ("atual").
                            aria-current={
                                idioma.codigo === atual ? 'true' : undefined
                            }
                            className="flex items-center gap-2 px-3 py-2 text-sm text-[#f4f1e8] hover:bg-[#c9bd94]/15 focus-visible:outline-2 focus-visible:outline-[#c9bd94] aria-[current=true]:font-semibold aria-[current=true]:text-[#c9bd94]"
                        >
                            <Bandeira codigo={idioma.codigo} />
                            {idioma.nome}
                        </a>
                    </li>
                ))}
            </ul>
        </details>
    );
}
