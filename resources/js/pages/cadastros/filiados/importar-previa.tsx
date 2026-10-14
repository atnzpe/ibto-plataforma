/**
 * Importação de filiados, TELA 4 de 4 (#37, F4b): prévia e confirmação.
 *
 * Mostra três grupos de linhas:
 * - certas: entram e cada pessoa recebe o convite por e-mail;
 * - incompletas: só FALTA algum dado (ex.: sem e-mail). Entram apenas se a
 *   caixa "Importar mesmo assim" for marcada (pedido do Gleyson, 10/10/2026);
 *   o cadastro fica com o selo "Incompleto" para completar depois;
 * - recusadas: dado errado ou repetido. Não entram; corrija na planilha.
 *
 * LGPD: só o NÚMERO da linha (como no Excel) e o motivo, sem nome, CPF ou e-mail.
 * Sem rolagem lateral: cada linha é um cartão, empilhado no celular.
 */
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ImportacaoFiliadosController from '@/actions/App/Http/Controllers/Cadastros/ImportacaoFiliadosController';
import { Button } from '@/components/ui/button';
import { colunas, create, valores } from '@/routes/cadastros/filiados/importar';

/**
 * Lista de linhas com os seus avisos, em cartões (2 colunas na tela larga).
 *
 * @param titulo Ex.: 'Linhas recusadas'
 * @param linhas Ex.: [{ linha: 4, avisos: ['CPF inválido.'] }]
 * @returns A lista, ou nada quando vazia.
 */
function ListaDeLinhas({
    titulo,
    linhas,
}: {
    titulo: string;
    linhas: { linha: number; avisos: string[] }[];
}) {
    // Sem linhas: não mostra o bloco.
    if (linhas.length === 0) {
        return null;
    }

    return (
        <section aria-label={titulo} className="flex flex-col gap-2">
            <h2 className="text-sm font-semibold">{titulo}</h2>
            <ul className="grid gap-2 sm:grid-cols-2">
                {linhas.map((item) => (
                    <li
                        key={item.linha}
                        className="rounded-md border border-border p-3 text-sm"
                    >
                        <strong className="font-mono">
                            Linha {item.linha}
                        </strong>
                        <ul className="mt-1 list-disc pl-4">
                            {item.avisos.map((aviso) => (
                                <li key={aviso}>{aviso}</li>
                            ))}
                        </ul>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * @param codigo O código desta importação.
 * @param validas Quantas linhas estão certas e vão entrar.
 * @param incompletas As linhas a que só falta dado: número da linha e o que falta.
 * @param recusadas As linhas recusadas: número da linha e motivos.
 * @param teve_valores Se houve a etapa de relacionar os valores.
 * @returns A tela da prévia.
 */
export default function ImportarPrevia({
    codigo,
    validas,
    incompletas,
    recusadas,
    teve_valores,
}: {
    codigo: string;
    validas: number;
    incompletas: { linha: number; faltas: string[] }[];
    recusadas: { linha: number; motivos: string[] }[];
    // true quando houve a etapa "relacionar os valores" (para o botão de voltar).
    teve_valores: boolean;
}) {
    // Caixa "Importar mesmo assim" marcada? Começa desmarcada: entrar incompleto é escolha consciente.
    const [mesmoAssim, setMesmoAssim] = useState(false);
    // Quantas entram ao confirmar: as certas e, com a caixa marcada, as incompletas.
    const vaoEntrar = validas + (mesmoAssim ? incompletas.length : 0);

    return (
        <>
            <Head title="Prévia da importação" />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Prévia da importação
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Passo 4 de 4: confira antes de confirmar.
                    </p>
                </div>

                <ul className="space-y-1 text-sm">
                    <li>
                        <strong>{validas}</strong> linha(s) certa(s): vão ser
                        cadastradas, e cada pessoa recebe o convite por e-mail.
                    </li>
                    <li>
                        <strong>{incompletas.length}</strong> linha(s)
                        incompleta(s): falta algum dado. Entram só se você
                        marcar "Importar mesmo assim".
                    </li>
                    <li>
                        <strong>{recusadas.length}</strong> linha(s)
                        recusada(s): corrija na planilha e importe de novo
                        depois.
                    </li>
                </ul>

                <ListaDeLinhas
                    titulo="Linhas incompletas (falta dado)"
                    linhas={incompletas.map((item) => ({
                        linha: item.linha,
                        avisos: item.faltas,
                    }))}
                />
                <ListaDeLinhas
                    titulo="Linhas recusadas (dado errado ou repetido)"
                    linhas={recusadas.map((item) => ({
                        linha: item.linha,
                        avisos: item.motivos,
                    }))}
                />

                <Form {...ImportacaoFiliadosController.confirmar.form(codigo)}>
                    {({ processing }) => (
                        <div className="flex flex-col gap-4">
                            {/* A caixa só aparece quando há linha incompleta. */}
                            {incompletas.length > 0 && (
                                <label className="flex min-h-11 items-start gap-2 rounded-md border border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-100">
                                    <input
                                        type="checkbox"
                                        name="mesmo_assim"
                                        value="1"
                                        checked={mesmoAssim}
                                        onChange={(evento) =>
                                            setMesmoAssim(evento.target.checked)
                                        }
                                        className="mt-0.5 size-4"
                                    />
                                    <span>
                                        <strong>Importar mesmo assim</strong> as{' '}
                                        {incompletas.length} linha(s)
                                        incompleta(s). O cadastro fica com o
                                        selo "Incompleto" para completar depois.
                                        Quem está sem e-mail só recebe o convite
                                        quando o e-mail for preenchido.
                                    </span>
                                </label>
                            )}

                            {/* Botões em grade: largura toda no celular. */}
                            <div className="grid gap-3 sm:flex sm:flex-wrap sm:items-center">
                                {/* Nada para entrar: botão desligado. */}
                                <Button
                                    disabled={processing || vaoEntrar === 0}
                                >
                                    Confirmar e cadastrar {vaoEntrar} filiado(s)
                                </Button>
                                {/* Voltar para a etapa anterior: os valores (se houve) ou as colunas. */}
                                {teve_valores && (
                                    <a
                                        href={valores(codigo).url}
                                        className="text-sm underline underline-offset-4"
                                    >
                                        Voltar aos valores
                                    </a>
                                )}
                                <a
                                    href={colunas(codigo).url}
                                    className="text-sm underline underline-offset-4"
                                >
                                    Voltar às colunas
                                </a>
                                <a
                                    href={create().url}
                                    className="text-sm underline underline-offset-4"
                                >
                                    Enviar outro arquivo
                                </a>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

// Caminho no topo: volta para o passo 1.
ImportarPrevia.layout = {
    breadcrumbs: [{ title: 'Importar filiados', href: create() }],
};
