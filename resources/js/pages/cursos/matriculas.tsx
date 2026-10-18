/**
 * MATRÍCULAS no Comando Geral (#41, bloco C5c): a secretaria/tesouraria
 * confere o comprovante do PIX e CONFIRMA o pagamento (dia + forma), ou
 * CANCELA com motivo (sem apagar). LGPD (10/10/2026): a lista mostra só o
 * REGISTRO; o nome e o e-mail ficam na ficha. Filtros: situação, curso, registro e
 * período "De / Até" pela data da matrícula.
 *
 * Sem rolagem lateral: um cartão por matrícula (1 coluna no celular, 2 na
 * tela larga) e os botões em grade de colunas iguais.
 */
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import MatriculaPainelController from '@/actions/App/Http/Controllers/Cursos/MatriculaPainelController';
import FiltroPeriodo from '@/components/filtro-periodo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';
import { dataBr } from '@/pages/catalogo/index';
import {
    cancelar,
    ficha,
    index,
    recusarComprovante,
} from '@/routes/comando-geral/matriculas';

/** Uma matrícula na lista. */
type MatriculaLista = {
    id: number;
    curso: string;
    curso_id: number;
    // LGPD: o registro do filiado (null = não filiado); o nome só na ficha.
    registro: string | null;
    // Identificador do Pix (#83), para conferir no extrato. Ex.: 'IBTOMAT1234'.
    txid: string;
    // Ex.: "Equipe Vulcan", "Sem equipe" ou "Não filiado".
    equipe: string;
    situacao: 'aguardando_pagamento' | 'confirmada' | 'cancelada';
    situacao_rotulo: string;
    valor_centavos: number;
    criada_em: string | null;
    confirmada_em: string | null;
    motivo_cancelamento: string | null;
    // Link protegido do comprovante, ou null (ainda não enviado).
    comprovante_url: string | null;
    // Quando o comprovante chegou (ex.: '18/10/2026 09:30'); null = sem comprovante.
    comprovante_enviado_em: string | null;
    // O motivo da última recusa do comprovante; null = nunca recusado.
    comprovante_recusa_motivo: string | null;
    // Receita no livro-caixa (referência do pagamento), ou null.
    lancamento_id: number | null;
};

/** Os filtros atuais (vazios = sem filtro). */
type Filtros = {
    situacao: string;
    curso: number | '';
    busca: string;
    de: string;
    ate: string;
};

// Cor e texto de cada situação (escritos, não só cor).
const SELO: Record<MatriculaLista['situacao'], string> = {
    aguardando_pagamento:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    confirmada:
        'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
    cancelada: 'bg-muted text-muted-foreground',
};

/**
 * @param matriculas A página atual (20 por página).
 * @param filtros Os filtros escolhidos.
 * @param aguardando Quantas aguardam pagamento (em todas as páginas).
 * @param cursos Os cursos que têm matrícula (para o filtro).
 * @param formas As formas de pagamento ativas (PIX, Dinheiro...).
 * @param hoje A data de hoje (padrão do "pago em"). Ex.: '2026-10-12'
 * @returns A tela das matrículas.
 */
export default function MatriculasPainel({
    matriculas,
    filtros,
    aguardando,
    cursos,
    formas,
    hoje,
}: {
    matriculas: {
        data: MatriculaLista[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filtros: Filtros;
    aguardando: number;
    cursos: { id: number; titulo: string }[];
    formas: { id: number; nome: string }[];
    hoje: string;
}) {
    const { sucesso, errors } = usePage().props;
    // A forma "PIX" já vem escolhida (é a do piloto); se não existir, a primeira.
    const formaPadrao =
        formas.find((forma) => forma.nome === 'PIX')?.id ?? formas[0]?.id;

    /**
     * Recarrega a lista com um filtro trocado (só manda os que têm valor).
     *
     * @param novo Ex.: { situacao: 'aguardando_pagamento' }
     */
    const filtrar = (novo: Partial<Filtros>) => {
        const tudo = { ...filtros, ...novo };
        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(tudo).filter(([, valor]) => valor !== ''),
            ),
            { preserveScroll: true, preserveState: true },
        );
    };

    /**
     * Cancela com motivo (pergunta o motivo numa janela do navegador).
     *
     * @param matricula A matrícula a cancelar.
     */
    const cancelarMatricula = (matricula: MatriculaLista) => {
        const motivo = window.prompt(
            `Cancelar a matrícula nº ${matricula.id} (${matricula.registro ?? 'não filiado'})? Escreva o motivo:`,
        );
        // Fechou a janela ou deixou em branco: não cancela.
        if (motivo === null || motivo.trim() === '') {
            return;
        }
        router.post(
            cancelar(matricula.id).url,
            { motivo },
            { preserveScroll: true },
        );
    };

    /**
     * Recusa o comprovante (ilegível, valor errado...) sem cancelar a matrícula:
     * pergunta o motivo, que vai por e-mail ao aluno; a vaga segue reservada.
     *
     * @param matricula A matrícula com o comprovante.
     */
    const recusar = (matricula: MatriculaLista) => {
        const motivo = window.prompt(
            `Recusar o comprovante da matrícula nº ${matricula.id}? O aluno recebe o motivo por e-mail e envia outro. Escreva o motivo:`,
        );
        // Fechou a janela ou deixou em branco: não recusa.
        if (motivo === null || motivo.trim() === '') {
            return;
        }
        router.post(
            recusarComprovante(matricula.id).url,
            { motivo },
            { preserveScroll: true },
        );
    };

    // Visual das caixas de filtro (44 px de altura).
    const caixa =
        'h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm';

    return (
        <>
            <Head title="Matrículas" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Matrículas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {aguardando > 0
                            ? `${aguardando} aguardando pagamento. Confira o comprovante e confirme.`
                            : 'Nenhuma matrícula aguardando pagamento.'}
                    </p>
                </div>

                {sucesso && (
                    <p
                        role="status"
                        className="rounded-md border border-green-600/40 bg-green-600/10 px-4 py-2 text-sm"
                    >
                        {sucesso}
                    </p>
                )}
                {(errors.matricula || errors.motivo) && (
                    <p
                        role="alert"
                        className="rounded-md border border-red-600/40 bg-red-600/10 px-4 py-2 text-sm"
                    >
                        {errors.matricula ?? errors.motivo}
                    </p>
                )}

                {/* Filtros: 2 colunas no celular, 4 na tela larga. */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className="grid gap-1">
                        <label
                            htmlFor="f-situacao"
                            className="text-sm font-medium"
                        >
                            Situação
                        </label>
                        <select
                            id="f-situacao"
                            value={filtros.situacao}
                            onChange={(evento) =>
                                filtrar({ situacao: evento.target.value })
                            }
                            className={caixa}
                        >
                            <option value="">Todas</option>
                            <option value="aguardando_pagamento">
                                Aguardando pagamento
                            </option>
                            <option value="confirmada">Confirmada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <label
                            htmlFor="f-curso"
                            className="text-sm font-medium"
                        >
                            Curso
                        </label>
                        <select
                            id="f-curso"
                            value={filtros.curso}
                            onChange={(evento) =>
                                filtrar({
                                    curso:
                                        evento.target.value === ''
                                            ? ''
                                            : Number(evento.target.value),
                                })
                            }
                            className={caixa}
                        >
                            <option value="">Todos</option>
                            {cursos.map((curso) => (
                                <option key={curso.id} value={curso.id}>
                                    {curso.titulo}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="col-span-2 grid gap-1">
                        <label
                            htmlFor="f-busca"
                            className="text-sm font-medium"
                        >
                            Registro ou nº da matrícula
                        </label>
                        <input
                            id="f-busca"
                            type="search"
                            defaultValue={filtros.busca}
                            className={caixa}
                            onKeyDown={(evento) =>
                                evento.key === 'Enter' &&
                                filtrar({ busca: evento.currentTarget.value })
                            }
                            onBlur={(evento) =>
                                evento.target.value !== filtros.busca &&
                                filtrar({ busca: evento.target.value })
                            }
                        />
                    </div>
                </div>
                <FiltroPeriodo
                    de={filtros.de}
                    ate={filtros.ate}
                    prefixo="matriculas"
                    aoMudar={(periodo) => filtrar(periodo)}
                />

                {matriculas.data.length === 0 && (
                    <p className="rounded-md border border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Nenhuma matrícula com estes filtros.
                    </p>
                )}

                {/* Um cartão por matrícula. */}
                <ul className="grid gap-3 lg:grid-cols-2">
                    {matriculas.data.map((matricula) => (
                        <li
                            key={matricula.id}
                            className="flex flex-col gap-3 rounded-md border border-border p-4 text-sm"
                        >
                            <div className="flex flex-col gap-1">
                                <span className="flex flex-wrap items-center gap-2">
                                    {/* LGPD: o registro (ou o nº da matrícula), nunca o nome na lista. */}
                                    <strong className="font-mono text-base">
                                        {matricula.registro ??
                                            `Matrícula nº ${matricula.id}`}
                                    </strong>
                                    <span
                                        className={`rounded px-1.5 py-0.5 text-xs font-medium ${SELO[matricula.situacao]}`}
                                    >
                                        {matricula.situacao_rotulo}
                                    </span>
                                    {/* Identificador do Pix (#83): o mesmo que aparece no extrato do banco. */}
                                    <span className="font-mono text-xs text-muted-foreground">
                                        Pix: {matricula.txid}
                                    </span>
                                </span>
                                <span className="text-muted-foreground">
                                    {matricula.equipe} ·{' '}
                                    {/* O nome e o e-mail só na ficha (o acesso fica registrado). */}
                                    <Link
                                        href={ficha(matricula.id)}
                                        className="underline underline-offset-4"
                                    >
                                        Abrir ficha
                                    </Link>
                                </span>
                                <span>
                                    {matricula.curso} · nº {matricula.id} ·{' '}
                                    {matricula.valor_centavos === 0
                                        ? 'Gratuito'
                                        : formatarReais(
                                              matricula.valor_centavos,
                                          )}
                                </span>
                                <span className="text-muted-foreground">
                                    Matrícula em{' '}
                                    {matricula.criada_em
                                        ? dataBr(matricula.criada_em)
                                        : '—'}
                                    {matricula.confirmada_em &&
                                        ` · confirmada em ${dataBr(matricula.confirmada_em)}`}
                                </span>
                                {matricula.motivo_cancelamento && (
                                    <span className="text-muted-foreground">
                                        Motivo: {matricula.motivo_cancelamento}
                                    </span>
                                )}
                                {/* Comprovante: abre numa aba nova; sem ele, o aviso. */}
                                {matricula.situacao ===
                                    'aguardando_pagamento' &&
                                    (matricula.comprovante_url ? (
                                        <span className="flex flex-wrap gap-x-3 gap-y-1">
                                            <a
                                                href={matricula.comprovante_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="underline underline-offset-4"
                                            >
                                                Ver o comprovante
                                            </a>
                                            {matricula.comprovante_enviado_em && (
                                                <span className="text-muted-foreground">
                                                    enviado em{' '}
                                                    {
                                                        matricula.comprovante_enviado_em
                                                    }
                                                </span>
                                            )}
                                            {/* Recusar sem cancelar: o aluno envia outro. */}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    recusar(matricula)
                                                }
                                                className="min-h-11 text-red-800 underline underline-offset-4 dark:text-red-300"
                                                aria-label={`Recusar o comprovante da matrícula nº ${matricula.id}`}
                                            >
                                                Recusar o comprovante
                                            </button>
                                        </span>
                                    ) : (
                                        <span className="text-amber-800 dark:text-amber-200">
                                            {matricula.comprovante_recusa_motivo
                                                ? `Comprovante recusado (${matricula.comprovante_recusa_motivo}); aguardando outro.`
                                                : 'Comprovante ainda não enviado.'}
                                        </span>
                                    ))}
                                {/* Referência do pagamento no financeiro. */}
                                {matricula.lancamento_id !== null && (
                                    <span className="text-muted-foreground">
                                        Receita nº {matricula.lancamento_id} no
                                        Financeiro.
                                    </span>
                                )}
                            </div>

                            {/* Confirmar o pagamento: dia + forma, em grade (2 colunas) e o botão na largura toda. */}
                            {matricula.situacao === 'aguardando_pagamento' && (
                                <Form
                                    {...MatriculaPainelController.confirmar.form(
                                        matricula.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="grid grid-cols-2 gap-2"
                                >
                                    {({ processing, errors: erros }) => (
                                        <>
                                            <label className="grid gap-1 text-xs">
                                                Pago em
                                                <input
                                                    type="date"
                                                    name="pago_em"
                                                    defaultValue={hoje}
                                                    max={hoje}
                                                    required
                                                    className={caixa}
                                                />
                                            </label>
                                            <label className="grid gap-1 text-xs">
                                                Forma
                                                <select
                                                    name="forma_pagamento_id"
                                                    defaultValue={formaPadrao}
                                                    required
                                                    className={caixa}
                                                >
                                                    {formas.map((forma) => (
                                                        <option
                                                            key={forma.id}
                                                            value={forma.id}
                                                        >
                                                            {forma.nome}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <InputError
                                                className="col-span-2"
                                                message={
                                                    erros.pago_em ??
                                                    erros.forma_pagamento_id
                                                }
                                            />
                                            <Button
                                                className="col-span-2 min-h-11"
                                                disabled={processing}
                                            >
                                                Confirmar pagamento
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}

                            {/* Cancelar (aguardando ou confirmada), com motivo. */}
                            {matricula.situacao !== 'cancelada' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => cancelarMatricula(matricula)}
                                    aria-label={`Cancelar a matrícula nº ${matricula.id}`}
                                >
                                    Cancelar matrícula
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>

                {/* Páginas (só aparece quando há mais de 20). */}
                {matriculas.links.length > 3 && (
                    <nav aria-label="Páginas" className="flex flex-wrap gap-2">
                        {matriculas.links.map((link, posicao) =>
                            link.url ? (
                                <Link
                                    key={posicao}
                                    href={link.url}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className="rounded border border-border px-3 py-1 text-sm aria-[current=page]:font-semibold"
                                    // O Laravel manda os rótulos com HTML (ex.: "&laquo; Anterior").
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : null,
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
MatriculasPainel.layout = {
    breadcrumbs: [{ title: 'Matrículas', href: index() }],
};
