import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
// Menu de bandeiras que abre a página traduzida pelo Google (#53).
import MenuIdiomas from '@/components/menu-idiomas';
import { interpretarSaude } from '@/lib/saude';
import type { EstadoSaude } from '@/lib/saude';
import { dashboard, login, register } from '@/routes';

// Página inicial provisória: confirma que o front (React) conversa com o
// back (Laravel) e o banco. O layout de verdade da plataforma vem na #38.
export default function Welcome() {
    const { auth } = usePage().props;
    const [saude, setSaude] = useState<EstadoSaude | null>(null);

    useEffect(() => {
        const controle = new AbortController();

        fetch('/api/saude', {
            headers: { Accept: 'application/json' },
            signal: controle.signal,
        })
            .then(async (resposta) => {
                const corpo: unknown = await resposta.json().catch(() => null);
                setSaude(interpretarSaude(resposta.status, corpo));
            })
            .catch(() => {
                if (!controle.signal.aborted) {
                    setSaude(interpretarSaude(null, null));
                }
            });

        return () => controle.abort();
    }, []);

    const linkClasse =
        'rounded-sm border border-[#c9bd94]/40 px-5 py-1.5 text-sm text-[#f4f1e8] hover:border-[#c9bd94] focus-visible:outline-2 focus-visible:outline-[#c9bd94]';

    return (
        <>
            <Head title="Plataforma de cursos" />
            <div className="flex min-h-screen flex-col items-center bg-black px-6 py-6 text-[#f4f1e8] lg:justify-center">
                <header className="mb-10 flex w-full max-w-2xl flex-wrap items-center justify-end gap-4">
                    {/* Menu de idiomas pelo Google Tradutor (#53), antes da conta. */}
                    <MenuIdiomas />
                    <nav
                        aria-label="Conta"
                        className="flex items-center justify-end gap-4"
                    >
                        {auth.user ? (
                            <Link href={dashboard()} className={linkClasse}>
                                Painel
                            </Link>
                        ) : (
                            <>
                                <Link href={login()} className={linkClasse}>
                                    Entrar
                                </Link>
                                <Link href={register()} className={linkClasse}>
                                    Criar conta
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="w-full max-w-2xl">
                    <p className="text-sm font-semibold tracking-widest text-[#c9bd94] uppercase">
                        Instituto Brasileiro de Treinamento Operacional
                    </p>
                    <h1 className="mt-2 text-4xl font-bold">
                        Plataforma de cursos online
                    </h1>
                    <p className="mt-4 text-[#f4f1e8]/80">
                        Em construção. Em breve, os cursos online do IBTO
                        estarão disponíveis aqui.
                    </p>

                    <section
                        aria-labelledby="titulo-saude"
                        className="mt-10 rounded-md border border-[#c9bd94]/30 p-5"
                    >
                        <h2
                            id="titulo-saude"
                            className="text-sm font-semibold text-[#c9bd94]"
                        >
                            Situação do sistema
                        </h2>
                        <div role="status" aria-live="polite" className="mt-2">
                            {saude === null && <p>Verificando…</p>}
                            {saude?.estado === 'ok' && (
                                <p>
                                    <span aria-hidden="true">✅ </span>
                                    {saude.mensagem}
                                </p>
                            )}
                            {saude?.estado === 'erro' && (
                                <p>
                                    <span aria-hidden="true">⚠️ </span>
                                    {saude.mensagem}{' '}
                                    <span className="font-mono text-sm text-[#c9bd94]">
                                        Código: {saude.codigo}
                                    </span>
                                </p>
                            )}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
