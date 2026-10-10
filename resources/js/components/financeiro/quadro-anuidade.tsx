/**
 * QUADRO DA ANUIDADE na ficha do filiado (#37 + #59): a situação do ano
 * (em dia, vencida, isenta), os pagamentos e o formulário "Registrar
 * pagamento", que grava o pagamento E a receita no financeiro. As regras de
 * verdade ficam no servidor (App\Services\Anuidades).
 * Responsivo: lista simples e formulário em grade, sem rolagem lateral.
 */
import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatarReais } from '@/lib/dinheiro';
import { anuidade as rotaAnuidade } from '@/routes/cadastros/filiados';

/** O quadro como o servidor manda (null quando a pessoa não vê o financeiro). */
export type QuadroAnuidade = {
    ano: number;
    situacao: 'em_dia' | 'vencida' | 'isenta';
    valor_centavos: number | null;
    pagamentos: {
        id: number;
        ano: number;
        valor_centavos: number;
        pago_em: string;
        forma: string | null;
        lancamento_id: number;
        estornado: boolean;
    }[];
    pode_registrar: boolean;
    anos: number[];
    formas: { id: number; nome: string }[];
};

// Situação do ano → texto (escrito, não só cor) e classe de cor.
const SITUACOES: Record<
    QuadroAnuidade['situacao'],
    { texto: string; classe: string }
> = {
    em_dia: { texto: 'Em dia', classe: 'text-green-700 dark:text-green-400' },
    // Ano com valor e sem pagamento válido (Gleyson): em vermelho e escrito.
    vencida: {
        texto: 'Vencida',
        classe: 'font-medium text-red-700 dark:text-red-400',
    },
    isenta: { texto: 'Isenta', classe: 'text-muted-foreground' },
};

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

/**
 * '2027-03-15' → '15/03/2027'.
 */
function dataBr(data: string): string {
    const [ano, mes, dia] = data.split('-');

    return `${dia}/${mes}/${ano}`;
}

/**
 * @param filiadoId O número do filiado da ficha. Ex.: 7
 * @param quadro A situação, os pagamentos e as opções do formulário.
 * @param erro A mensagem de regra do servidor (ex.: "... (ANUIDADE_JA_PAGA)"), ou nada.
 * @returns A seção "Anuidade".
 */
export default function QuadroAnuidadeFiliado({
    filiadoId,
    quadro,
    erro,
}: {
    filiadoId: number;
    quadro: QuadroAnuidade;
    erro?: string;
}) {
    // Hoje no formato do campo de data. Ex.: '2027-03-15'.
    const hoje = new Date().toLocaleDateString('sv-SE');
    // Ano sugerido: o de hoje, se puder ser pago; senão, o primeiro da lista.
    const anoSugerido = quadro.anos.includes(quadro.ano)
        ? quadro.ano
        : quadro.anos[0];

    return (
        <section
            aria-labelledby="titulo-anuidade"
            className="flex flex-col gap-4 rounded-md border border-border p-4"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="titulo-anuidade" className="text-base font-semibold">
                    Anuidade {quadro.ano}
                </h2>
                <span
                    className={`text-sm ${SITUACOES[quadro.situacao].classe}`}
                >
                    {SITUACOES[quadro.situacao].texto}
                    {quadro.valor_centavos !== null &&
                        quadro.situacao !== 'isenta' &&
                        ` · ${formatarReais(quadro.valor_centavos)}`}
                </span>
            </div>

            {/* Pagamentos: lista simples (cabe no celular). */}
            {quadro.pagamentos.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nenhum pagamento registrado.
                </p>
            ) : (
                <ul
                    aria-label="Pagamentos da anuidade"
                    className="flex flex-col divide-y divide-border text-sm"
                >
                    {quadro.pagamentos.map((pagamento) => (
                        <li
                            key={pagamento.id}
                            className="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"
                        >
                            <span
                                className={
                                    pagamento.estornado
                                        ? 'text-muted-foreground line-through'
                                        : 'font-medium'
                                }
                            >
                                {pagamento.ano} ·{' '}
                                {formatarReais(pagamento.valor_centavos)}
                            </span>
                            <span className="text-muted-foreground">
                                {dataBr(pagamento.pago_em)}
                                {pagamento.forma && ` · ${pagamento.forma}`}
                                {pagamento.estornado && ' · estornado'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {/* Formulário: só para quem pode lançar no financeiro e se houver ano para pagar. */}
            {quadro.pode_registrar && quadro.anos.length > 0 && (
                <Form
                    {...rotaAnuidade.form(filiadoId)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-3 border-t border-border pt-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <h3 className="text-sm font-medium">
                                Registrar pagamento
                            </h3>
                            {/* Celular: 1 coluna; tela média: 3 colunas iguais. */}
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="anuidade-ano">Ano</Label>
                                    <select
                                        id="anuidade-ano"
                                        name="ano"
                                        defaultValue={anoSugerido}
                                        className={classeSelect}
                                    >
                                        {quadro.anos.map((ano) => (
                                            <option key={ano} value={ano}>
                                                {ano}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.ano} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="anuidade-pago-em">
                                        Dia do pagamento
                                    </Label>
                                    <Input
                                        id="anuidade-pago-em"
                                        name="pago_em"
                                        type="date"
                                        defaultValue={hoje}
                                        max={hoje}
                                    />
                                    <InputError message={errors.pago_em} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="anuidade-forma">
                                        Forma
                                    </Label>
                                    <select
                                        id="anuidade-forma"
                                        name="forma_pagamento_id"
                                        defaultValue=""
                                        className={classeSelect}
                                    >
                                        <option value="" disabled>
                                            Escolha
                                        </option>
                                        {quadro.formas.map((forma) => (
                                            <option
                                                key={forma.id}
                                                value={forma.id}
                                            >
                                                {forma.nome}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.forma_pagamento_id}
                                    />
                                </div>
                            </div>
                            {/* Erro de regra do servidor (ex.: já paga), com o código. */}
                            <InputError message={erro} />
                            <Button
                                disabled={processing}
                                className="min-h-11 w-full sm:w-auto sm:self-start"
                            >
                                Registrar e lançar no financeiro
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
