/**
 * PAINEL FINANCEIRO DAS AULAS REGULARES (17/10/2026), no molde do "Cockpit" do
 * Dojo Manager: de onde viemos, onde estamos e para onde vamos.
 *
 * - Filtros: De/Até (pelo vencimento), programa, instrutor (quem recebe o
 *   repasse), aluno (quem paga) e situação da mensalidade.
 * - Cartões: quem treina, previsto, recebido, a receber, em atraso e a divisão
 *   IBTO/instrutor (com o que já foi repassado e o que falta repassar).
 * - Barra "recebido × previsto", evolução por mês, tabelas por programa e por
 *   instrutor, a lista de quem treina e as mensalidades (com CSV).
 * Tabelas viram cartões abaixo de 1024 px (regra da casa: nada rola de lado).
 */
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import FiltroPeriodo from '@/components/filtro-periodo';
import TabelaRelatorio from '@/components/relatorios/tabela-relatorio';
import type { ColunaRelatorio } from '@/components/relatorios/tabela-relatorio';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatarReais } from '@/lib/dinheiro';

/** As somas de um grupo de mensalidades (todos os valores em centavos). */
type Somas = {
    previsto: number;
    recebido: number;
    a_receber: number;
    atrasado: number;
    // Percentual recebido do previsto; null quando não há previsão.
    percentual_recebido: number | null;
    ibto_previsto: number;
    instrutor_previsto: number;
    ibto_recebido: number;
    instrutor_recebido: number;
    repassado: number;
    a_repassar: number;
    mensalidades: number;
};

/** Um grupo (programa, instrutor ou mês) com as somas. */
type Grupo = Somas & { nome: string; alunos: number };

/** Uma opção de filtro. Ex.: { id: 3, nome: 'Krav Maga' } */
type Opcao = { id: number | string; nome: string };

/** Os filtros aplicados (vindos do endereço). */
type Filtros = {
    programa: number | null;
    instrutor: number | null;
    pagador: string;
    situacao: string | null;
};

/** O que a tela recebe do servidor. */
type Props = {
    totais: Somas & {
        alunos: number;
        alunos_em_dia: number;
        alunos_travados: number;
    };
    por_programa: Grupo[];
    por_instrutor: Grupo[];
    por_mes: Grupo[];
    alunos: Record<string, unknown>[];
    mensalidades: Record<string, unknown>[];
    periodo: { de: string; ate: string };
    filtros: Filtros;
    ve_tudo: boolean;
    programas: Opcao[];
    instrutores: Opcao[];
    situacoes: Opcao[];
};

// Colunas das tabelas por programa e por instrutor (as mesmas somas).
const COLUNAS_GRUPO: ColunaRelatorio[] = [
    { chave: 'nome', rotulo: 'Nome', tipo: 'texto' },
    { chave: 'alunos', rotulo: 'Alunos', tipo: 'numero' },
    { chave: 'previsto', rotulo: 'Previsto', tipo: 'dinheiro' },
    { chave: 'recebido', rotulo: 'Recebido', tipo: 'dinheiro' },
    { chave: 'a_receber', rotulo: 'A receber', tipo: 'dinheiro' },
    { chave: 'atrasado', rotulo: 'Em atraso', tipo: 'dinheiro' },
    { chave: 'ibto_recebido', rotulo: 'IBTO (recebido)', tipo: 'dinheiro' },
    {
        chave: 'instrutor_recebido',
        rotulo: 'Instrutor (recebido)',
        tipo: 'dinheiro',
    },
    { chave: 'repassado', rotulo: 'Já repassado', tipo: 'dinheiro' },
    { chave: 'a_repassar', rotulo: 'Falta repassar', tipo: 'dinheiro' },
];

// Colunas da lista de quem treina.
const COLUNAS_ALUNOS: ColunaRelatorio[] = [
    { chave: 'aluno', rotulo: 'Aluno', tipo: 'texto' },
    { chave: 'programa', rotulo: 'Programa', tipo: 'texto' },
    { chave: 'turma', rotulo: 'Turma', tipo: 'texto' },
    { chave: 'instrutor', rotulo: 'Instrutor', tipo: 'texto' },
    { chave: 'mensalidade', rotulo: 'Mensalidade', tipo: 'dinheiro' },
    { chave: 'acesso_rotulo', rotulo: 'Situação', tipo: 'texto' },
    { chave: 'pago_ate', rotulo: 'Pago até', tipo: 'texto' },
];

// Colunas das mensalidades do período (uma linha por mês de cada aluno).
const COLUNAS_MENSALIDADES: ColunaRelatorio[] = [
    { chave: 'referencia', rotulo: 'Mês', tipo: 'texto' },
    { chave: 'vence_em', rotulo: 'Vencimento', tipo: 'texto' },
    { chave: 'aluno', rotulo: 'Aluno (pagador)', tipo: 'texto' },
    { chave: 'programa', rotulo: 'Programa', tipo: 'texto' },
    { chave: 'instrutor', rotulo: 'Instrutor (recebedor)', tipo: 'texto' },
    { chave: 'valor', rotulo: 'Valor', tipo: 'dinheiro' },
    { chave: 'situacao_rotulo', rotulo: 'Situação', tipo: 'texto' },
    { chave: 'ibto', rotulo: 'IBTO', tipo: 'dinheiro' },
    { chave: 'instrutor_parte', rotulo: 'Instrutor', tipo: 'dinheiro' },
];

/**
 * Soma as colunas de dinheiro e número de uma tabela (texto fica vazio).
 *
 * @param colunas As colunas da tabela.
 * @param linhas As linhas.
 * @returns A linha de totais. Ex.: { nome: null, previsto: 48000 }
 */
function somarColunas(
    colunas: ColunaRelatorio[],
    linhas: Record<string, unknown>[],
): Record<string, unknown> {
    return Object.fromEntries(
        colunas.map((coluna) => [
            coluna.chave,
            coluna.tipo === 'dinheiro' || coluna.tipo === 'numero'
                ? linhas.reduce(
                      (soma, linha) => soma + Number(linha[coluna.chave] ?? 0),
                      0,
                  )
                : null,
        ]),
    );
}

/**
 * @param props Os números do painel, os filtros aplicados e as opções dos filtros.
 * @returns A tela do painel financeiro das aulas regulares.
 */
export default function PainelAulasRegulares({
    totais,
    por_programa,
    por_instrutor,
    por_mes,
    alunos,
    mensalidades,
    periodo,
    filtros,
    ve_tudo,
    programas,
    instrutores,
    situacoes,
}: Props) {
    // O nome do aluno digitado (só busca ao clicar em "Buscar" ou apertar Enter).
    const [pagador, setPagador] = useState(filtros.pagador);

    /**
     * Recarrega a tela com o período e os filtros novos (o que não mudou continua).
     *
     * @param mudancas O que mudou. Ex.: { programa: '3' }
     */
    const filtrar = (mudancas: Record<string, string>) => {
        const atual: Record<string, string> = {
            de: periodo.de,
            ate: periodo.ate,
            programa: filtros.programa?.toString() ?? '',
            instrutor: filtros.instrutor?.toString() ?? '',
            pagador: filtros.pagador,
            situacao: filtros.situacao ?? '',
        };
        // Tira do endereço os filtros vazios (endereço limpo e fácil de compartilhar).
        const novo = Object.fromEntries(
            Object.entries({ ...atual, ...mudancas }).filter(
                ([, valor]) => valor !== '',
            ),
        );
        router.get('/relatorios/aulas-regulares', novo, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // O endereço do CSV com os mesmos filtros da tela.
    const csv = `/relatorios/aulas-regulares/csv?${new URLSearchParams(
        Object.entries({
            de: periodo.de,
            ate: periodo.ate,
            programa: filtros.programa?.toString() ?? '',
            instrutor: filtros.instrutor?.toString() ?? '',
            pagador: filtros.pagador,
            situacao: filtros.situacao ?? '',
        }).filter(([, valor]) => valor !== ''),
    ).toString()}`;

    // Os cartões do topo: o que importa primeiro (onde estamos).
    const destaques = [
        {
            rotulo: 'Quem treina',
            valor: String(totais.alunos),
            dica: `${totais.alunos_em_dia} em dia · ${totais.alunos_travados} com aulas travadas`,
        },
        {
            rotulo: 'Previsto',
            valor: formatarReais(totais.previsto),
            dica: `${totais.mensalidades} mensalidades no período`,
        },
        {
            rotulo: 'Recebido',
            valor: formatarReais(totais.recebido),
            dica:
                totais.percentual_recebido === null
                    ? 'sem previsão no período'
                    : `${totais.percentual_recebido}% do previsto`,
        },
        {
            rotulo: 'A receber',
            valor: formatarReais(totais.a_receber),
            dica: 'ainda no prazo',
        },
        {
            rotulo: 'Em atraso',
            valor: formatarReais(totais.atrasado),
            dica: 'vencidas e não pagas',
        },
    ];

    // A divisão do dinheiro: quanto é do IBTO e quanto é do instrutor.
    const divisao = [
        {
            rotulo: 'Parte do IBTO',
            valor: formatarReais(totais.ibto_recebido),
            dica: `de ${formatarReais(totais.ibto_previsto)} previstos`,
        },
        {
            rotulo: 'Parte dos instrutores',
            valor: formatarReais(totais.instrutor_recebido),
            dica: `de ${formatarReais(totais.instrutor_previsto)} previstos`,
        },
        {
            rotulo: 'Já repassado',
            valor: formatarReais(totais.repassado),
            dica: 'pago aos instrutores (baixa no livro-caixa)',
        },
        {
            rotulo: 'Falta repassar',
            valor: formatarReais(totais.a_repassar),
            dica: 'recebido do aluno, ainda não pago ao instrutor',
        },
    ];

    // O maior previsto entre os meses: a barra mais longa ocupa 100%.
    const maiorMes = Math.max(1, ...por_mes.map((mes) => mes.previsto));

    return (
        <>
            <Head title="Aulas regulares (financeiro)" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Aulas regulares (financeiro)
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {ve_tudo
                                ? 'Todos os programas: quem treina, quanto entra e como se divide entre o IBTO e os instrutores.'
                                : 'Os seus programas: quem treina, quanto entra e quanto é seu.'}
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="min-h-11 w-full sm:w-auto"
                    >
                        <a href={csv} data-tour="csv-aulas">
                            Exportar CSV
                        </a>
                    </Button>
                </div>

                <FiltroPeriodo
                    de={periodo.de}
                    ate={periodo.ate}
                    prefixo="aulas"
                    aoMudar={(novo) => filtrar(novo)}
                />

                {/* Os outros filtros: programa, instrutor, aluno e situação. */}
                <div
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    data-tour="filtros-aulas"
                >
                    <div className="grid gap-1">
                        <Label htmlFor="filtro-programa">Programa</Label>
                        <select
                            id="filtro-programa"
                            value={filtros.programa ?? ''}
                            onChange={(evento) =>
                                filtrar({ programa: evento.target.value })
                            }
                            className="min-h-11 rounded-md border bg-transparent px-2"
                        >
                            <option value="">Todos</option>
                            {programas.map((opcao) => (
                                <option key={opcao.id} value={opcao.id}>
                                    {opcao.nome}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="filtro-instrutor">
                            Instrutor (recebedor)
                        </Label>
                        <select
                            id="filtro-instrutor"
                            value={filtros.instrutor ?? ''}
                            onChange={(evento) =>
                                filtrar({ instrutor: evento.target.value })
                            }
                            className="min-h-11 rounded-md border bg-transparent px-2"
                        >
                            <option value="">Todos</option>
                            {instrutores.map((opcao) => (
                                <option key={opcao.id} value={opcao.id}>
                                    {opcao.nome}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="filtro-situacao">Situação</Label>
                        <select
                            id="filtro-situacao"
                            value={filtros.situacao ?? ''}
                            onChange={(evento) =>
                                filtrar({ situacao: evento.target.value })
                            }
                            className="min-h-11 rounded-md border bg-transparent px-2"
                        >
                            <option value="">Todas</option>
                            {situacoes.map((opcao) => (
                                <option key={opcao.id} value={opcao.id}>
                                    {opcao.nome}
                                </option>
                            ))}
                        </select>
                    </div>
                    {/* Aluno (pagador): busca por parte do nome, ao enviar. */}
                    <form
                        className="grid gap-1"
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            filtrar({ pagador: pagador.trim() });
                        }}
                    >
                        <Label htmlFor="filtro-pagador">Aluno (pagador)</Label>
                        <div className="flex gap-2">
                            <input
                                id="filtro-pagador"
                                type="search"
                                value={pagador}
                                maxLength={100}
                                onChange={(evento) =>
                                    setPagador(evento.target.value)
                                }
                                placeholder="Parte do nome"
                                className="min-h-11 w-full min-w-0 rounded-md border bg-transparent px-2"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                className="min-h-11"
                            >
                                Buscar
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Onde estamos: os cartões do topo. */}
                <ul
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                    aria-label="Totais do período"
                    data-tour="cartoes-aulas"
                >
                    {destaques.map((destaque) => (
                        <li
                            key={destaque.rotulo}
                            className="flex flex-col gap-1 rounded-md border border-border p-4"
                        >
                            <span className="text-xs text-muted-foreground">
                                {destaque.rotulo}
                            </span>
                            <span className="text-xl font-semibold [overflow-wrap:anywhere]">
                                {destaque.valor}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {destaque.dica}
                            </span>
                        </li>
                    ))}
                </ul>

                {/* Barra: quanto do previsto já entrou, quanto está no prazo e quanto atrasou. */}
                {totais.previsto > 0 && (
                    <section aria-label="Recebido em relação ao previsto">
                        <div
                            className="flex h-4 w-full overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`Recebido ${formatarReais(totais.recebido)}, a receber ${formatarReais(totais.a_receber)}, em atraso ${formatarReais(totais.atrasado)}, de ${formatarReais(totais.previsto)} previstos`}
                        >
                            <span
                                className="bg-emerald-600"
                                style={{
                                    width: `${(totais.recebido / totais.previsto) * 100}%`,
                                }}
                            />
                            <span
                                className="bg-[#c9bd94]"
                                style={{
                                    width: `${(totais.a_receber / totais.previsto) * 100}%`,
                                }}
                            />
                            <span
                                className="bg-red-600"
                                style={{
                                    width: `${(totais.atrasado / totais.previsto) * 100}%`,
                                }}
                            />
                        </div>
                        <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span>■ verde: recebido</span>
                            <span>■ dourado: a receber</span>
                            <span>■ vermelho: em atraso</span>
                        </p>
                    </section>
                )}

                {/* Para onde vai o dinheiro: IBTO, instrutores e repasses. */}
                <section aria-labelledby="titulo-divisao">
                    <h2
                        id="titulo-divisao"
                        className="mb-2 text-base font-semibold"
                    >
                        Divisão: IBTO e instrutores
                    </h2>
                    <ul
                        className="grid grid-cols-2 gap-3 lg:grid-cols-4"
                        data-tour="divisao-aulas"
                    >
                        {divisao.map((item) => (
                            <li
                                key={item.rotulo}
                                className="flex flex-col gap-1 rounded-md border border-border p-4"
                            >
                                <span className="text-xs text-muted-foreground">
                                    {item.rotulo}
                                </span>
                                <span className="text-lg font-semibold [overflow-wrap:anywhere]">
                                    {item.valor}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {item.dica}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                {/* De onde viemos e para onde vamos: previsto × recebido por mês. */}
                <section aria-labelledby="titulo-meses">
                    <h2
                        id="titulo-meses"
                        className="mb-2 text-base font-semibold"
                    >
                        Mês a mês
                    </h2>
                    {por_mes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nenhuma mensalidade no período.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {por_mes.map((mes) => (
                                <li
                                    key={mes.nome}
                                    className="grid gap-1 text-sm"
                                >
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <strong>{mes.nome}</strong>
                                        <span className="text-muted-foreground">
                                            {formatarReais(mes.recebido)} de{' '}
                                            {formatarReais(mes.previsto)}
                                        </span>
                                    </div>
                                    {/* Barra cinza = previsto; verde por cima = recebido. */}
                                    <div
                                        className="h-3 overflow-hidden rounded-full bg-muted"
                                        style={{
                                            width: `${(mes.previsto / maiorMes) * 100}%`,
                                        }}
                                        aria-hidden="true"
                                    >
                                        <div
                                            className="h-full bg-emerald-600"
                                            style={{
                                                width: `${mes.previsto > 0 ? (mes.recebido / mes.previsto) * 100 : 0}%`,
                                            }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="titulo-programas">
                    <h2
                        id="titulo-programas"
                        className="mb-2 text-base font-semibold"
                    >
                        Por programa
                    </h2>
                    <TabelaRelatorio
                        colunas={COLUNAS_GRUPO}
                        linhas={por_programa}
                        totais={somarColunas(COLUNAS_GRUPO, por_programa)}
                        titulo="Por programa"
                    />
                </section>

                <section aria-labelledby="titulo-instrutores">
                    <h2
                        id="titulo-instrutores"
                        className="mb-2 text-base font-semibold"
                    >
                        Por instrutor (recebedor)
                    </h2>
                    <TabelaRelatorio
                        colunas={COLUNAS_GRUPO}
                        linhas={por_instrutor}
                        totais={somarColunas(COLUNAS_GRUPO, por_instrutor)}
                        titulo="Por instrutor"
                    />
                </section>

                <section
                    aria-labelledby="titulo-alunos"
                    data-tour="alunos-aulas"
                >
                    <h2
                        id="titulo-alunos"
                        className="mb-2 text-base font-semibold"
                    >
                        Quem treina
                    </h2>
                    <TabelaRelatorio
                        colunas={COLUNAS_ALUNOS}
                        linhas={alunos}
                        totais={somarColunas(COLUNAS_ALUNOS, alunos)}
                        titulo="Quem treina"
                    />
                </section>

                <section aria-labelledby="titulo-mensalidades">
                    <h2
                        id="titulo-mensalidades"
                        className="mb-2 text-base font-semibold"
                    >
                        Mensalidades do período
                    </h2>
                    <TabelaRelatorio
                        colunas={COLUNAS_MENSALIDADES}
                        linhas={mensalidades}
                        totais={somarColunas(
                            COLUNAS_MENSALIDADES,
                            mensalidades,
                        )}
                        titulo="Mensalidades do período"
                    />
                </section>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
PainelAulasRegulares.layout = {
    breadcrumbs: [
        { title: 'Relatórios', href: '/relatorios' },
        { title: 'Aulas regulares', href: '/relatorios/aulas-regulares' },
    ],
};
