/**
 * AÇÕES de um lançamento na lista do financeiro (#59): Dar baixa, Cancelar e
 * Estornar, cada uma numa janela (diálogo) com o seu formulário.
 *
 * Quais botões aparecem depende da situação (pendente, vencido, pago...). Isso
 * só ajuda quem usa: a regra de verdade é conferida no servidor (LivroCaixa).
 */
import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import LancamentoController from '@/actions/App/Http/Controllers/Financeiro/LancamentoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatarReais } from '@/lib/dinheiro';
// Endereço da tela de edição de um lançamento. Ex.: edit(7) → /financeiro/lancamentos/7/editar
import { edit } from '@/routes/financeiro/lancamentos';
import type { Lancamento } from '@/types/financeiro';

// As três ações possíveis.
type Acao = 'pagar' | 'cancelar' | 'estornar';

// Visual comum das caixas de escolha (select), igual ao dos campos de texto.
const classeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

// Hoje no formato do campo de data. Ex.: '2027-03-15'.
const hoje = () => new Date().toLocaleDateString('sv-SE');

/**
 * @param lancamento A linha da lista. Ex.: { id: 7, situacao: 'pendente', ... }
 * @param formas As formas de pagamento ativas, para a baixa. Ex.: [{ id: 1, nome: 'PIX' }]
 * @returns Os botões das ações permitidas (ou nada).
 */
export default function AcoesLancamento({
    lancamento,
    formas,
}: {
    lancamento: Lancamento;
    formas: { id: number; nome: string }[];
}) {
    // Qual janela está aberta agora (null = nenhuma).
    const [aberta, setAberta] = useState<Acao | null>(null);

    // Ainda não pago (no prazo ou atrasado): pode dar baixa ou cancelar.
    const emAberto =
        lancamento.situacao === 'pendente' || lancamento.situacao === 'vencido';

    // Só estorna o que está PAGO, não é ele mesmo um estorno e ainda não foi estornado
    // (Gleyson). Pendente e cancelado não entram: pendente se cancela.
    const podeEstornar =
        lancamento.situacao === 'pago' &&
        !lancamento.estorno &&
        !lancamento.estornado;

    // Nada a fazer (cancelado, estorno ou já estornado): não mostra botão nenhum.
    if (!emAberto && !podeEstornar) {
        return null;
    }

    // Texto que identifica a linha para o leitor de tela. Ex.: 'Tarifa (R$ 12,50)'.
    const nome = `${lancamento.descricao} (${formatarReais(lancamento.valor_centavos)})`;

    /**
     * Abre ou fecha uma janela.
     *
     * @param acao Qual janela. Ex.: 'pagar'
     * @returns A função que o Dialog chama ao abrir (true) ou fechar (false).
     */
    const controlar = (acao: Acao) => (abrir: boolean) =>
        setAberta(abrir ? acao : null);

    return (
        // Uma linha só (sem quebrar): a coluna da tabela cresce para caber os botões.
        <div className="flex flex-nowrap justify-end gap-2">
            {emAberto && (
                <>
                    {/* Editar: abre o formulário preenchido (só pendente). */}
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={edit(lancamento.id)}
                            aria-label={`Editar ${nome}`}
                        >
                            Editar
                        </Link>
                    </Button>
                    <Dialog
                        open={aberta === 'pagar'}
                        onOpenChange={controlar('pagar')}
                    >
                        <DialogTrigger asChild>
                            <Button
                                size="sm"
                                aria-label={`Dar baixa em ${nome}`}
                            >
                                Dar baixa
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Dar baixa</DialogTitle>
                            <DialogDescription>
                                {nome}. Depois de pago, o lançamento não se
                                altera; para corrigir, só com estorno.
                            </DialogDescription>
                            <Form
                                {...LancamentoController.pagar.form(
                                    lancamento.id,
                                )}
                                options={{ preserveScroll: true }}
                                // Deu certo: fecha a janela (a lista já volta atualizada).
                                onSuccess={() => setAberta(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`pago_em-${lancamento.id}`}
                                            >
                                                Dia do pagamento
                                            </Label>
                                            <Input
                                                id={`pago_em-${lancamento.id}`}
                                                name="pago_em"
                                                type="date"
                                                defaultValue={hoje()}
                                                max={hoje()}
                                            />
                                            <InputError
                                                message={errors.pago_em}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`forma-${lancamento.id}`}
                                            >
                                                Forma de pagamento
                                            </Label>
                                            <select
                                                id={`forma-${lancamento.id}`}
                                                name="forma_pagamento_id"
                                                defaultValue=""
                                                className={classeSelect}
                                            >
                                                <option value="" disabled>
                                                    Escolha
                                                </option>
                                                {formas.map((forma) => (
                                                    <option
                                                        key={forma.id}
                                                        value={forma.id}
                                                    >
                                                        {forma.nome}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={
                                                    errors.forma_pagamento_id
                                                }
                                            />
                                        </div>
                                        {/* Erro de regra do servidor (ex.: já estava pago). */}
                                        <InputError message={errors.acao} />
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                >
                                                    Voltar
                                                </Button>
                                            </DialogClose>
                                            <Button disabled={processing}>
                                                Confirmar baixa
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <JanelaMotivo
                        acao="cancelar"
                        lancamento={lancamento}
                        nome={nome}
                        aberta={aberta === 'cancelar'}
                        aoMudar={controlar('cancelar')}
                        aoTerminar={() => setAberta(null)}
                    />
                </>
            )}

            {podeEstornar && (
                <JanelaMotivo
                    acao="estornar"
                    lancamento={lancamento}
                    nome={nome}
                    aberta={aberta === 'estornar'}
                    aoMudar={controlar('estornar')}
                    aoTerminar={() => setAberta(null)}
                />
            )}
        </div>
    );
}

/**
 * Janela de CANCELAR ou ESTORNAR: as duas pedem um motivo; o estorno pede
 * também o dia em que o dinheiro voltou.
 *
 * @param acao 'cancelar' ou 'estornar'.
 * @param lancamento A linha da lista.
 * @param nome Texto que identifica a linha. Ex.: 'Tarifa (R$ 12,50)'
 * @param aberta true se esta janela está aberta.
 * @param aoMudar Chamada pelo Dialog ao abrir ou fechar.
 * @param aoTerminar Chamada quando o servidor aceita (fecha a janela).
 * @returns O botão e a janela.
 */
function JanelaMotivo({
    acao,
    lancamento,
    nome,
    aberta,
    aoMudar,
    aoTerminar,
}: {
    acao: 'cancelar' | 'estornar';
    lancamento: Lancamento;
    nome: string;
    aberta: boolean;
    aoMudar: (abrir: boolean) => void;
    aoTerminar: () => void;
}) {
    // true na janela de estorno (muda textos e mostra o campo de data).
    const estorno = acao === 'estornar';

    // Para onde o formulário envia: a rota de cancelar ou a de estornar.
    const destino = estorno
        ? LancamentoController.estornar.form(lancamento.id)
        : LancamentoController.cancelar.form(lancamento.id);

    return (
        <Dialog open={aberta} onOpenChange={aoMudar}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    aria-label={`${estorno ? 'Estornar' : 'Cancelar'} ${nome}`}
                >
                    {estorno ? 'Estornar' : 'Cancelar'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {estorno
                        ? 'Estornar lançamento pago'
                        : 'Cancelar lançamento'}
                </DialogTitle>
                <DialogDescription>
                    {nome}.{' '}
                    {estorno
                        ? 'O original continua no histórico; um lançamento de estorno, ligado a ele, desconta o valor do saldo. Só pode ser feito uma vez.'
                        : 'A conta deixa de existir: fica no histórico, mas sai dos totais e dos vencidos.'}
                </DialogDescription>
                <Form
                    {...destino}
                    options={{ preserveScroll: true }}
                    onSuccess={aoTerminar}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`motivo-${acao}-${lancamento.id}`}
                                >
                                    Motivo
                                </Label>
                                <Input
                                    id={`motivo-${acao}-${lancamento.id}`}
                                    name="motivo"
                                    maxLength={300}
                                    placeholder={
                                        estorno
                                            ? 'Ex.: valor lançado errado'
                                            : 'Ex.: lançado em duplicidade'
                                    }
                                />
                                <InputError message={errors.motivo} />
                            </div>
                            {/* Só o estorno pede o dia da devolução. */}
                            {estorno && (
                                <div className="grid gap-2">
                                    <Label htmlFor={`data-${lancamento.id}`}>
                                        Dia do estorno
                                    </Label>
                                    <Input
                                        id={`data-${lancamento.id}`}
                                        name="data"
                                        type="date"
                                        defaultValue={hoje()}
                                        max={hoje()}
                                    />
                                    <InputError message={errors.data} />
                                </div>
                            )}
                            <InputError message={errors.acao} />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Voltar
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {estorno
                                        ? 'Confirmar estorno'
                                        : 'Confirmar cancelamento'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
