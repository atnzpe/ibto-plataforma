/**
 * FOTO INTEIRA numa moldura de tamanho fixo (#105; Gleyson, 24/10/2026: "foto
 * cortada fica feio; devemos ser belos e simétricos"). Igual ao componente
 * FotoInteira do site (landpage), para as duas casas terem o mesmo padrão.
 *
 * - A moldura tem sempre a mesma proporção (ex.: 4:3): todos os cartões de uma
 *   grade ficam do mesmo tamanho, alinhados e simétricos.
 * - A foto aparece INTEIRA dentro dela (nunca corta rosto, cabeça ou pés).
 * - A sobra da moldura (quando a foto tem outro formato) é preenchida com a
 *   PRÓPRIA foto, ampliada e desfocada, em vez de faixas pretas.
 *
 * As duas imagens usam o mesmo endereço: o navegador baixa a foto uma vez só.
 */

/**
 * A moldura com a foto inteira.
 *
 * @param src Endereço da foto. Ex.: '/imagens/blog/3?v=1759870000'
 * @param alt Descrição da foto para quem usa leitor de tela. Ex.: 'Turma do curso de Krav Maga'. Vazio = foto só de enfeite.
 * @param proporcao Classe do Tailwind com a proporção da moldura. Ex.: 'aspect-[4/3]' (padrão) ou 'aspect-video'.
 * @param className Classes extras da moldura (cantos, borda, largura). Ex.: 'rounded-xl'.
 * @param largura Largura da foto em pixels, para o navegador reservar o espaço antes de baixar. Ex.: 800
 * @param altura Altura da foto em pixels. Ex.: 600
 * @param carregar 'lazy' (padrão: só baixa quando chega perto da tela) ou 'eager' (já baixa, para a foto do topo).
 * @param como 'div' (padrão) ou 'span': use 'span' quando a foto fica DENTRO de um botão
 *             (o HTML não aceita <div> dentro de <button>). Ex.: a vitrine de fotos.
 * @returns A moldura com a foto inteira dentro (um <div> ou um <span>).
 */
export default function FotoInteira({
    src,
    alt,
    proporcao = 'aspect-[4/3]',
    className = '',
    largura = 800,
    altura = 600,
    carregar = 'lazy',
    como = 'div',
}: {
    src: string;
    alt: string;
    proporcao?: string;
    className?: string;
    largura?: number;
    altura?: number;
    carregar?: 'lazy' | 'eager';
    como?: 'div' | 'span';
}) {
    // A etiqueta da moldura: <div> ou <span> (os dois desenham igual, por causa do "block").
    const Moldura = como;

    return (
        // "relative" + "overflow-hidden": o fundo desfocado fica preso dentro da moldura.
        <Moldura
            className={`relative block w-full overflow-hidden bg-black/40 ${proporcao} ${className}`}
        >
            {/* FUNDO: a mesma foto, cobrindo a moldura toda, desfocada e um pouco escura.
                É só enfeite: alt vazio e aria-hidden, para o leitor de tela não ler duas vezes. */}
            <img
                src={src}
                alt=""
                aria-hidden="true"
                loading={carregar}
                className="absolute inset-0 size-full scale-110 object-cover opacity-60 blur-xl"
            />
            {/* FRENTE: a foto INTEIRA (object-contain = encaixa sem cortar), centralizada. */}
            <img
                src={src}
                alt={alt}
                loading={carregar}
                width={largura}
                height={altura}
                className="relative size-full object-contain"
            />
        </Moldura>
    );
}
