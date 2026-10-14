/**
 * EMBLEMA do IBTO (o escudo redondo com a caveira, igual ao ícone do site
 * público). Substitui o logo do Laravel que veio no kit inicial (pedido do
 * Gleyson, 10/10/2026: cores e logo do IBTO). Usado onde cabe um quadrado:
 * menu lateral recolhido, cabeçalho do celular.
 *
 * O arquivo fica em public/images/ibto-emblema.png (copiado do site).
 */
import type { ImgHTMLAttributes } from 'react';

/**
 * @param props Atributos da imagem; o tamanho vem do className. Ex.: className="size-8"
 * @returns A imagem do emblema, redonda.
 */
export default function AppLogoIcon({
    className = '',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/ibto-emblema.png"
            // alt vazio: o nome "IBTO" já aparece escrito ao lado (o leitor de tela não repete).
            alt=""
            aria-hidden="true"
            className={`rounded-full object-cover ${className}`}
            {...props}
        />
    );
}
