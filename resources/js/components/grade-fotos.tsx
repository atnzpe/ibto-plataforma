/**
 * GRADE DE FOTOS da galeria (#61, bloco B2), usada na página do álbum e na
 * página do post ligado. Cada foto com a DESCRIÇÃO (para o leitor de tela) e o
 * crédito embaixo; clicar abre a foto inteira numa aba nova. 1 coluna no
 * celular, 2 no tablet e 3 na tela larga: nenhuma tela rola de lado.
 */
// Foto inteira, sem cortar o rosto, com o fundo desfocado (#105).
import FotoInteira from '@/components/foto-inteira';

/** Uma foto como a tela mostra. */
export type FotoGaleria = {
    id: number;
    url: string;
    descricao: string | null;
    credito: string;
};

/**
 * @param fotos As fotos, na ordem. Ex.: [{ id: 31, url: '/imagens/galeria/31', descricao: 'Turma', credito: 'Divulgação / IBTO' }]
 * @returns A grade (ou nada, sem fotos).
 */
export default function GradeFotos({ fotos }: { fotos: FotoGaleria[] }) {
    // Sem fotos: não mostra nada.
    if (fotos.length === 0) {
        return null;
    }

    return (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {fotos.map((foto) => (
                <li key={foto.id}>
                    <figure className="flex flex-col gap-1">
                        {/* Link comum: abre a foto inteira numa aba nova. */}
                        <a href={foto.url} target="_blank" rel="noopener">
                            {/* Foto inteira na moldura 4:3: a grade fica simétrica e ninguém tem o rosto cortado. */}
                            <FotoInteira
                                src={foto.url}
                                alt={foto.descricao ?? ''}
                                className="rounded-lg"
                            />
                        </a>
                        <figcaption className="text-xs text-[#5c5644]">
                            {foto.descricao} · Foto: {foto.credito}
                        </figcaption>
                    </figure>
                </li>
            ))}
        </ul>
    );
}
