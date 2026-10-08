/**
 * Tipos dos cadastros de apoio (#37), iguais ao que o servidor envia para as telas.
 */

/** Uma área de atuação. Ex.: { id: 4, nome: 'Krav Maga', ativa: true } */
export type AreaAtuacao = {
    // Número da área no banco.
    id: number;
    // Nome mostrado nas listas. Ex.: 'Krav Maga'.
    nome: string;
    // true = aparece nas listas de escolha; false = desativada.
    ativa: boolean;
    // Endereço da imagem da área, ou null se ainda não tem imagem.
    imagem_url: string | null;
};

/** Um nível. Ex.: { id: 9, categoria: 'Instrutor Expert', grau: 4, nome: 'Instrutor Expert IV', ativo: true } */
export type Nivel = {
    id: number;
    // Uma das 4 categorias (Praticante, Monitor, Instrutor, Instrutor Expert).
    categoria: string;
    // Grau de 1 a 5, ou null na categoria sem grau (Praticante).
    grau: number | null;
    // Nome pronto para a tela, montado pelo servidor. Ex.: 'Instrutor Expert IV'.
    nome: string;
    // true = aparece nas listas de escolha; false = desativado.
    ativo: boolean;
};

/** Uma categoria de nível e o maior grau que ela aceita (0 = sem grau). */
export type CategoriaNivel = {
    valor: string;
    grau_maximo: number;
};
