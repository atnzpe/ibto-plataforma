/**
 * REDE DE PROTEÇÃO das telas (#58, bloco B): se algum pedaço da tela React
 * quebrar, em vez de uma tela BRANCA a pessoa vê "Algo deu errado", com um
 * código para informar e um botão para recarregar. O erro é avisado ao
 * servidor (lib/erros-tela.ts).
 *
 * No React, quem "segura" erros de tela é um componente de classe com
 * getDerivedStateFromError/componentDidCatch (o chamado "error boundary").
 */
import { Component } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { avisarErro } from '@/lib/erros-tela';

type Props = { children: ReactNode };
type Estado = { quebrou: boolean };

export default class RedeDeProtecao extends Component<Props, Estado> {
    // Começa sem erro: mostra a tela normalmente.
    state: Estado = { quebrou: false };

    /**
     * O React chama quando algum componente de dentro quebra: troca para a tela de aviso.
     *
     * @returns O novo estado (quebrou = true).
     */
    static getDerivedStateFromError(): Estado {
        return { quebrou: true };
    }

    /**
     * O React chama logo depois, com o erro: avisa o servidor (nada em silêncio).
     *
     * @param erro O erro que quebrou a tela.
     * @param _info Detalhes do React (não usados: podem ser grandes demais).
     */
    componentDidCatch(erro: Error, _info: ErrorInfo): void {
        avisarErro(erro, 'tela');
    }

    render(): ReactNode {
        // Sem erro: a tela de sempre.
        if (!this.state.quebrou) {
            return this.props.children;
        }

        // Com erro: aviso simples, com código e botão (sem detalhes técnicos na tela).
        return (
            <main className="flex min-h-svh flex-col items-center justify-center gap-4 p-6 text-center">
                <h1 className="text-xl font-semibold">Algo deu errado</h1>
                <p className="max-w-md text-sm text-muted-foreground">
                    Esta tela encontrou um problema. Nós já fomos avisados.
                    Tente recarregar; se continuar, informe o código{' '}
                    <span className="font-mono">ERRO_TELA</span> à equipe do
                    IBTO.
                </p>
                <button
                    type="button"
                    onClick={() => window.location.reload()}
                    className="rounded-md border border-border px-4 py-2 text-sm hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                >
                    Recarregar a página
                </button>
            </main>
        );
    }
}
