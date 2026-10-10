/**
 * Painel "COMANDO GERAL" (#59 bloco 4 / #45): os indicadores do IBTO, no
 * desenho aprovado pelo Gleyson em 09/10/2026 — 4 totais do mês, entradas ×
 * saídas de 6 meses, anuidade do ano, próximos vencimentos, filiados e a
 * régua de cobrança (só o REGISTRO, sem nome: LGPD).
 * Responsivo: tudo em grade que vira uma coluna no celular, sem rolagem lateral.
 */
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatarReais } from '@/lib/dinheiro';
import { edit as editarFiliado } from '@/routes/cadastros/filiados';
import { historico } from '@/routes/financeiro';
import {
    create as novoLancamento,
    index as livroCaixa,
} from '@/routes/financeiro/lancamentos';
import { comandoGeral } from '@/routes';

/** O que o servidor manda (App\Services\ComandoGeral). */
type Painel = {
    mes: string;
    totais: {
        saldo_centavos: number;
        a_receber_centavos: number;
        a_pagar_centavos: number;
        vencidos: number;
    };
    fluxo: {
        mes: string;
        entradas_centavos: number;
        saidas_centavos: number;
    }[];
    anuidade: {
        ano: number;
        isenta: boolean;
        total: number;
        em_dia: number;
        percentual: number;
        recebido_centavos: number;
        em_aberto: number;
        regua: {
            id: number;
            registro: string;
            equipe: string | null;
            dias: number;
        }[];
    };
    vencimentos: {
        id: number;
        vencimento: string;
        descricao: string;
        tipo: 'receita' | 'despesa';
        valor_centavos: number;
    }[];
    filiados: {
        ativos: number;
        equipes: number;
        convites_pendentes: number;
        por_categoria: { categoria: string; quantidade: number }[];
    };
    pode_lancar: boolean;
    pode_ver_financeiro: boolean;
};

// Nomes curtos dos meses, para o gráfico. Ex.: '2027-10' → 'out'.
const MESES = [
    'jan',
    'fev',
    'mar',
    'abr',
    'mai',
    'jun',
    'jul',
    'ago',
    'set',
    'out',
    'nov',
    'dez',
];

// Classe comum dos blocos (cartões grandes).
const bloco = 'flex flex-col gap-4 rounded-xl border border-border bg-card p-5';

/**
 * '2027-10-15' → '15/10'.
 */
function diaMes(data: string): string {
    const [, mes, dia] = data.split('-');

    return `${dia}/${mes}`;
}

/**
 * @param props Os números do painel (ver o tipo Painel).
 * @returns A tela do Comando Geral.
 */
export default function ComandoGeral(props: Painel) {
    const {
        totais,
        fluxo,
        anuidade,
        vencimentos,
        filiados,
        pode_lancar,
        pode_ver_financeiro,
    } = props;

    // Nome do mês do painel. Ex.: '2027-10' → 'outubro de 2027'.
    const [ano, mes] = props.mes.split('-').map(Number);
    const nomeDoMes = new Date(ano, mes - 1, 1).toLocaleDateString('pt-BR', {
        month: 'long',
        year: 'numeric',
    });

    // Maior valor do gráfico: as barras são proporcionais a ele (mínimo 1 para não dividir por zero).
    const maior = Math.max(
        1,
        ...fluxo.flatMap((m) => [m.entradas_centavos, m.saidas_centavos]),
    );
    // Maior categoria: as barrinhas de filiados são proporcionais a ela.
    const maiorCategoria = Math.max(
        1,
        ...filiados.por_categoria.map((c) => c.quantidade),
    );

    // Os 4 cartões do topo.
    const cartoes = [
        {
            titulo: 'Saldo do mês',
            valor: formatarReais(totais.saldo_centavos),
            detalhe: 'Entradas menos saídas pagas',
            alerta: totais.saldo_centavos < 0,
        },
        {
            titulo: 'A receber',
            valor: formatarReais(totais.a_receber_centavos),
            detalhe: 'Pendentes que vencem no mês',
            alerta: false,
        },
        {
            titulo: 'A pagar',
            valor: formatarReais(totais.a_pagar_centavos),
            detalhe: 'Pendentes que vencem no mês',
            alerta: false,
        },
        {
            titulo: 'Vencidos',
            valor: String(totais.vencidos),
            detalhe: 'De todos os meses',
            alerta: totais.vencidos > 0,
        },
    ];

    return (
        <>
            <Head title="Comando Geral" />

            <div className="flex flex-col gap-5 p-4">
                {/* Topo: título e atalhos (no celular, botões na largura toda). */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground first-letter:uppercase">
                            {nomeDoMes}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Comando Geral
                        </h1>
                    </div>
                    <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        {pode_ver_financeiro && (
                            <Button
                                asChild
                                variant="outline"
                                className={pode_lancar ? '' : 'col-span-2'}
                            >
                                <Link href={livroCaixa()}>Financeiro</Link>
                            </Button>
                        )}
                        {pode_lancar && (
                            <Button asChild>
                                <Link href={novoLancamento()}>
                                    Novo lançamento
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* 4 cartões: 1 coluna no celular, 2 no tablet, 4 na tela larga. */}
                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {cartoes.map((cartao) => (
                        <div
                            key={cartao.titulo}
                            className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4"
                        >
                            <dt className="text-sm text-muted-foreground">
                                {cartao.titulo}
                            </dt>
                            <dd
                                className={`text-2xl font-semibold ${cartao.alerta ? 'text-red-700 dark:text-red-400' : ''}`}
                            >
                                {cartao.valor}
                            </dd>
                            <dd className="text-xs text-muted-foreground">
                                {cartao.detalhe}
                            </dd>
                        </div>
                    ))}
                </dl>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Gráfico de barras feito com caixas (sem biblioteca), com legenda escrita. */}
                    <section aria-labelledby="t-fluxo" className={bloco}>
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2
                                id="t-fluxo"
                                className="text-base font-semibold"
                            >
                                Entradas × saídas (6 meses)
                            </h2>
                            <div className="flex gap-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden="true"
                                        className="size-3 rounded-sm bg-[#6b5d3a]"
                                    />
                                    Entradas
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden="true"
                                        className="size-3 rounded-sm border border-[#a8996c] bg-[#d8cca6]"
                                    />
                                    Saídas
                                </span>
                            </div>
                        </div>
                        <div className="grid h-44 grid-cols-6 items-end gap-2 border-b border-border">
                            {fluxo.map((m) => (
                                // Cada mês: duas barras; o texto escondido descreve os valores para o leitor de tela.
                                <div
                                    key={m.mes}
                                    className="flex h-full items-end justify-center gap-1"
                                >
                                    <span className="sr-only">
                                        {m.mes}: entradas{' '}
                                        {formatarReais(m.entradas_centavos)},
                                        saídas{' '}
                                        {formatarReais(m.saidas_centavos)}
                                    </span>
                                    <div
                                        aria-hidden="true"
                                        className="w-2/5 max-w-6 rounded-t bg-[#6b5d3a]"
                                        style={{
                                            height: `${(Math.max(0, m.entradas_centavos) / maior) * 100}%`,
                                        }}
                                    />
                                    <div
                                        aria-hidden="true"
                                        className="w-2/5 max-w-6 rounded-t border border-b-0 border-[#a8996c] bg-[#d8cca6]"
                                        style={{
                                            height: `${(Math.max(0, m.saidas_centavos) / maior) * 100}%`,
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                        <div
                            aria-hidden="true"
                            className="grid grid-cols-6 gap-2 text-center text-xs text-muted-foreground"
                        >
                            {fluxo.map((m) => (
                                <span key={m.mes}>
                                    {MESES[Number(m.mes.split('-')[1]) - 1]}
                                </span>
                            ))}
                        </div>
                    </section>

                    {/* Anuidade do ano. */}
                    <section aria-labelledby="t-anuidade" className={bloco}>
                        <h2 id="t-anuidade" className="text-base font-semibold">
                            Anuidade {anuidade.ano}
                        </h2>
                        {anuidade.isenta ? (
                            <p className="text-sm text-muted-foreground">
                                {anuidade.ano} é isento: todos os{' '}
                                {anuidade.total} filiados ativos estão em dia.
                            </p>
                        ) : (
                            <>
                                <div className="flex items-baseline gap-2">
                                    <strong className="text-4xl font-semibold">
                                        {anuidade.percentual}%
                                    </strong>
                                    <span className="text-sm text-muted-foreground">
                                        dos filiados ativos em dia
                                    </span>
                                </div>
                                <div
                                    role="img"
                                    aria-label={`${anuidade.percentual}% em dia`}
                                    className="h-3 overflow-hidden rounded-full border border-[#d8cca6] bg-muted"
                                >
                                    <div
                                        className="h-full bg-[#6b5d3a]"
                                        style={{
                                            width: `${anuidade.percentual}%`,
                                        }}
                                    />
                                </div>
                                <dl className="grid grid-cols-3 gap-3">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Em dia
                                        </dt>
                                        <dd className="text-lg font-semibold">
                                            {anuidade.em_dia}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Em aberto
                                        </dt>
                                        <dd className="text-lg font-semibold text-red-700 dark:text-red-400">
                                            {anuidade.em_aberto}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Recebido
                                        </dt>
                                        <dd className="text-lg font-semibold">
                                            {formatarReais(
                                                anuidade.recebido_centavos,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </>
                        )}
                    </section>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Próximos vencimentos: lista simples (cabe no celular). */}
                    <section aria-labelledby="t-venc" className={bloco}>
                        <h2 id="t-venc" className="text-base font-semibold">
                            Próximos vencimentos
                        </h2>
                        {vencimentos.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhuma conta pendente daqui para a frente.
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y divide-border text-sm">
                                {vencimentos.map((v) => (
                                    <li
                                        key={v.id}
                                        className="flex items-baseline justify-between gap-3 py-2"
                                    >
                                        <span className="flex min-w-0 gap-3">
                                            <span className="text-muted-foreground">
                                                {diaMes(v.vencimento)}
                                            </span>
                                            <span className="truncate">
                                                {v.descricao}
                                            </span>
                                        </span>
                                        {/* "+" receita, "−" despesa: escrito, não só cor. */}
                                        <span className="font-medium whitespace-nowrap">
                                            {v.tipo === 'receita' ? '+ ' : '− '}
                                            {formatarReais(v.valor_centavos)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {pode_ver_financeiro && (
                            <Link
                                href={historico()}
                                className="text-sm underline underline-offset-4"
                            >
                                Ver o histórico do financeiro
                            </Link>
                        )}
                    </section>

                    {/* Filiados. */}
                    <section aria-labelledby="t-filiados" className={bloco}>
                        <h2 id="t-filiados" className="text-base font-semibold">
                            Filiados
                        </h2>
                        <dl className="grid grid-cols-3 gap-3">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Ativos
                                </dt>
                                <dd className="text-xl font-semibold">
                                    {filiados.ativos}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Equipes ativas
                                </dt>
                                <dd className="text-xl font-semibold">
                                    {filiados.equipes}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Convites pendentes
                                </dt>
                                <dd className="text-xl font-semibold">
                                    {filiados.convites_pendentes}
                                </dd>
                            </div>
                        </dl>
                        <h3 className="text-sm text-muted-foreground">
                            Por categoria
                        </h3>
                        <ul className="flex flex-col gap-2 text-sm">
                            {filiados.por_categoria.map((c) => (
                                <li
                                    key={c.categoria}
                                    className="grid grid-cols-[minmax(0,8rem)_minmax(0,1fr)_2rem] items-center gap-2"
                                >
                                    <span className="truncate">
                                        {c.categoria}
                                    </span>
                                    <span
                                        aria-hidden="true"
                                        className="h-2.5 rounded-full bg-muted"
                                    >
                                        <span
                                            className="block h-full rounded-full bg-[#6b5d3a]"
                                            style={{
                                                width: `${(c.quantidade / maiorCategoria) * 100}%`,
                                            }}
                                        />
                                    </span>
                                    <span className="text-right font-medium">
                                        {c.quantidade}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Régua de cobrança: só o registro (LGPD), com o atalho para a ficha. */}
                    <section aria-labelledby="t-regua" className={bloco}>
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2
                                id="t-regua"
                                className="text-base font-semibold"
                            >
                                Régua de cobrança
                            </h2>
                            {!anuidade.isenta && (
                                <span className="text-sm font-medium text-red-700 dark:text-red-400">
                                    {anuidade.em_aberto} anuidade(s) em aberto
                                </span>
                            )}
                        </div>
                        {anuidade.regua.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {anuidade.isenta
                                    ? 'Ano isento: ninguém a cobrar.'
                                    : 'Todos em dia.'}
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y divide-border text-sm">
                                {anuidade.regua.map((r) => (
                                    <li
                                        key={r.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span className="flex flex-col">
                                            <span className="font-medium">
                                                {r.registro}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {r.equipe ?? 'Sem equipe'}
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-3">
                                            <span className="font-medium text-red-700 dark:text-red-400">
                                                {r.dias} dias
                                            </span>
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="min-h-11"
                                            >
                                                <Link
                                                    href={editarFiliado(r.id)}
                                                    aria-label={`Abrir a ficha do filiado ${r.registro}`}
                                                >
                                                    Abrir ficha
                                                </Link>
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    {/* Cursos: espaço reservado até existirem os cursos (#39 e #41). */}
                    <section
                        aria-labelledby="t-cursos"
                        className="flex flex-col gap-3 rounded-xl border border-dashed border-border p-5"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2
                                id="t-cursos"
                                className="text-base font-semibold"
                            >
                                Cursos
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                Chega com as issues #39 e #41
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Turmas abertas, vagas livres, inscritos e
                            certificados do mês aparecem aqui quando os cursos
                            existirem na plataforma.
                        </p>
                    </section>
                </div>
            </div>
        </>
    );
}

// Caminho mostrado no topo da tela.
ComandoGeral.layout = {
    breadcrumbs: [{ title: 'Comando Geral', href: comandoGeral() }],
};
