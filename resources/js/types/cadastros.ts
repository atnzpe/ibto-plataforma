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
