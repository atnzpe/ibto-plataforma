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
    // Vínculos (18/10/2026): o tipo de movimento e a quem se refere. null = sem vínculo.
    movimento: string;
    pessoa: string | null;
    curso: string | null;
    equipe: string | null;
    forma: string | null;
};

/** Os cartões do topo, somados SOBRE O FILTRO (centavos; vencidos e quantidade são contagens). */
export type CartoesFinanceiro = {
    recebido: number;
    a_receber: number;
    pago: number;
    a_pagar: number;
    // Saldo em caixa no filtro: recebido − pago.
    saldo: number;
    vencidos: number;
    quantidade: number;
};

/** Uma linha da visão sintética: os cartões somados de um grupo (pessoa, categoria...). */
export type LinhaSintetica = CartoesFinanceiro & { grupo: string };

/** Uma opção de filtro. Ex.: { id: 'fotografo', nome: 'Fotógrafo' } */
export type OpcaoFiltro = { id: number | string; nome: string };

/** Os filtros da lista (18/10/2026). Vazio/null = sem esse filtro. */
export type FiltrosFinanceiro = {
    // Período: data inicial e final (AAAA-MM-DD).
    de: string;
    ate: string;
    visao: 'todos' | 'receber' | 'pagar' | 'vencidos';
    // Qual data o período usa: o vencimento ou o dia do pagamento.
    data_base: 'vencimento' | 'pagamento';
    situacao: string | null;
    movimento: string | null;
    modalidade: string | null;
    categoria: number | null;
    forma: number | null;
    equipe: number | null;
    curso: number | null;
    programa: number | null;
    pessoa_id: number | null;
    // Parte do nome ou do e-mail.
    pessoa: string;
    papel: string | null;
    // Nº do lançamento, da matrícula/pedido/mensalidade, ou o código da página de pagamento.
    codigo: string;
    // Em reais, como digitado. Ex.: '150,50'.
    valor_min: string;
    valor_max: string;
    modo: 'analitico' | 'sintetico';
    agrupar: string;
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
