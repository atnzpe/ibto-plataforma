/**
 * LINK DO SUPORTE TÉCNICO pelo WhatsApp (15/10/2026), num arquivo só dele
 * (sem tela), para poder ser testado sozinho. Quem usa: o botão
 * "Suporte técnico" (resources/js/components/botao-suporte.tsx).
 *
 * A mensagem padronizada diz a quem atende de qual sistema e de qual tela a
 * pessoa está falando, e quem ela é.
 */

/**
 * Monta o link do WhatsApp com a mensagem padronizada.
 *
 * @param numero O número do suporte, só números. Ex.: '5581999990000'
 * @param tela O endereço da tela atual. Ex.: 'https://cursos.ib-to.org/blog'
 * @param usuario O nome de quem pede, ou null para visitante.
 * @returns Ex.: 'https://wa.me/5581999990000?text=Ol%C3%A1!...'
 */
export function linkDoSuporte(
    numero: string,
    tela: string,
    usuario: string | null,
): string {
    // As linhas da mensagem (o "\n" vira uma quebra de linha no WhatsApp).
    const mensagem = [
        'Olá! Preciso de suporte técnico.',
        'Sistema: Plataforma de Cursos do IBTO',
        `Tela: ${tela}`,
        `Usuário: ${usuario ?? 'visitante, sem login'}`,
        'O que aconteceu: ',
    ].join('\n');

    // encodeURIComponent: transforma acentos, espaços e quebras de linha em
    // códigos que podem ir num endereço (ex.: espaço vira %20).
    return `https://wa.me/${numero}?text=${encodeURIComponent(mensagem)}`;
}
