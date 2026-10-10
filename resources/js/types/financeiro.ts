/**
 * Formatos dos dados do FINANCEIRO (#59) que o servidor manda para as telas.
 */

/** Uma linha do livro-caixa, como a lista mostra. */
export type Lancamento = {
    id: number;
    tipo: 'receita' | 'despesa';
    categoria: string | null;
    descricao: string;
    // Valor em centavos (9000 = R$ 90,00).
    valor_centavos: number;
    // Datas no formato '2027-03-31'.
    vencimento: string;
    pago_em: string | null;
    // 'vencido' = pendente cujo vencimento já passou (calculado no servidor).
    situacao: 'pendente' | 'vencido' | 'pago' | 'cancelado';
    // true quando a linha é o estorno de outro lançamento.
    estorno: boolean;
    // true quando este lançamento JÁ foi estornado (não pode estornar de novo).
    estornado: boolean;
    // Parcela "3 de 10" (null = à vista).
    parcela_numero: number | null;
    parcela_total: number | null;
    // Quantas parcelas do mesmo título ainda estão em aberto (0 = à vista).
    parcelas_em_aberto: number;
};

/** Totais do mês, no topo da lista (todos em centavos, menos "vencidos"). */
export type TotaisFinanceiro = {
    saldo_centavos: number;
    a_receber_centavos: number;
    a_pagar_centavos: number;
    // Quantos lançamentos estão vencidos (de qualquer mês).
    vencidos: number;
};

/** Os filtros da lista. Ex.: { mes: '2027-03', visao: 'pagar' } */
export type FiltrosFinanceiro = {
    mes: string;
    visao: 'todos' | 'receber' | 'pagar' | 'vencidos';
};

/** Um lançamento pendente aberto para EDIÇÃO (valor já no formato do campo). */
export type LancamentoEdicao = {
    id: number;
    tipo: 'receita' | 'despesa';
    categoria_id: number;
    descricao: string;
    // Ex.: '12,50'.
    valor: string;
    // Ex.: '2027-03-31'.
    vencimento: string;
    equipe_id: number | null;
};

/** Uma categoria para o formulário. */
export type CategoriaFinanceira = {
    id: number;
    nome: string;
    tipo: 'receita' | 'despesa';
};
