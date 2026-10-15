/**
 * LAYOUT DA VITRINE (#38, bloco C4a): a moldura das telas públicas de cursos
 * (catálogo, página do curso, trilhas), no desenho aprovado pelo Gleyson em
 * 10/10/2026: cabeçalho escuro com o "IBTO" dourado, fundo areia, Audiowide
 * nos títulos e Poppins no texto (a identidade do site do IBTO).
 * O menu de idiomas é o do Google (telas públicas, regra de 07/10/2026).
 * Responsivo: o menu quebra linha no celular; nada de rolagem lateral.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BotaoSuporte from '@/components/botao-suporte';
import { index as blog } from '@/routes/blog';
import { index as eventos } from '@/routes/galeria';
import MenuIdiomas from '@/components/menu-idiomas';
import { dashboard, login } from '@/routes';
import { index as catalogo } from '@/routes/catalogo';

/**
 * @param children A tela de dentro (catálogo, curso, trilha).
 * @returns A tela com o cabeçalho e o rodapé da vitrine.
 */
export default function VitrineLayout({ children }: { children: ReactNode }) {
    // Quem está logado (null = visitante): muda o botão do canto ("Entrar" ou "Minha área").
    const { auth } = usePage().props;

    return (
        <div className="min-h-svh bg-[#f4f1e8] font-[Poppins,sans-serif] text-[#1c1b17]">
            {/* As fontes da identidade do IBTO (as mesmas do site). */}
            <Head>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Audiowide&family=Poppins:wght@400;500;600&display=swap"
                />
            </Head>

            <header className="bg-[#1c1b17] text-[#f4f1e8]">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <Link
                        href={catalogo()}
                        className="font-[Audiowide,sans-serif] text-2xl tracking-wide text-[#c9bd94] no-underline"
                    >
                        IBTO
                    </Link>
                    {/* Menu: quebra linha no celular; cada item com 44 px de altura (bom para o dedo). */}
                    <nav
                        aria-label="Menu da vitrine"
                        className="flex flex-wrap items-center gap-1 text-sm"
                    >
                        <Link
                            href={catalogo()}
                            className="inline-flex min-h-11 items-center rounded-lg px-3 text-[#e9e2cc] no-underline hover:bg-white/10"
                        >
                            Cursos
                        </Link>
                        {/* Blog (#61): notícias e eventos do IBTO. */}
                        <Link
                            href={blog()}
                            className="inline-flex min-h-11 items-center rounded-lg px-3 text-[#e9e2cc] no-underline hover:bg-white/10"
                        >
                            Blog
                        </Link>
                        {/* Galeria de eventos (#61, B2): os álbuns de fotos. */}
                        <Link
                            href={eventos()}
                            className="inline-flex min-h-11 items-center rounded-lg px-3 text-[#e9e2cc] no-underline hover:bg-white/10"
                        >
                            Eventos
                        </Link>
                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="inline-flex min-h-11 items-center rounded-lg bg-[#6b5d3a] px-4 font-medium text-white no-underline hover:bg-[#5a4e30]"
                        >
                            {auth.user ? 'Minha área' : 'Entrar'}
                        </Link>
                        <MenuIdiomas />
                    </nav>
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl px-4 pt-6 pb-12">
                {children}
            </main>

            <footer className="flex flex-col items-center gap-2 border-t border-[#e4dccb] py-6 text-center text-xs text-[#5c5644]">
                Instituto Brasileiro de Treinamento Operacional · Instruções com
                propósito. Treinamento com resultados.
                {/* Suporte técnico pelo WhatsApp (15/10/2026). */}
                <BotaoSuporte />
            </footer>
        </div>
    );
}
