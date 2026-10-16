/**
 * PIX COPIA E COLA com QR code (#83), no quadro da matrícula.
 *
 * Mostra o QR code (o aluno aponta a câmera do app do banco), o botão "Copiar
 * código Pix" (para quem paga no mesmo celular), o código também em texto (para
 * leitor de tela e para copiar à mão) e o identificador da matrícula. O código
 * já leva o valor exato: o aluno não digita valor nem chave.
 *
 * Segurança: o aviso pede para conferir o NOME de quem recebe no app do banco
 * antes de confirmar. É a última barreira contra QR falso ou site clonado: o app
 * do banco sempre mostra o favorecido verdadeiro.
 */
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/** O Pix pronto, gerado no servidor (CatalogoController::pixDaMatricula). */
export type PixDaMatricula = {
    // O código "copia e cola". Ex.: '00020126360014br.gov.bcb.pix...6304ABCD'.
    copia_e_cola: string;
    // O desenho do QR code (SVG gerado no nosso servidor, sem site de terceiros).
    qr_svg: string;
    // Identificador da matrícula. Ex.: 'IBTOMAT1234'.
    txid: string;
};

/**
 * @param pix O código, o QR e o identificador.
 * @param favorecido Nome de quem recebe, para o aviso. Ex.: 'Instituto Brasileiro de Treinamento Operacional'.
 * @param chave A chave Pix, para o aviso. Ex.: '35.855.768/0001-21'.
 * @returns O bloco do Pix.
 */
export default function PixCopiaECola({
    pix,
    favorecido,
    chave,
}: {
    pix: PixDaMatricula;
    favorecido: string;
    chave: string;
}) {
    // true por 2 segundos depois de copiar (o botão mostra "Código copiado!").
    const [copiado, setCopiado] = useState(false);

    /**
     * Copia o código para a área de transferência, para colar no app do banco.
     */
    async function copiar() {
        try {
            await navigator.clipboard.writeText(pix.copia_e_cola);
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        } catch {
            // Navegador sem permissão de copiar: o código continua visível abaixo para copiar à mão.
            setCopiado(false);
        }
    }

    return (
        <section
            aria-labelledby="titulo-pix"
            className="flex flex-col items-center gap-3 rounded-lg border border-[#e4dccb] bg-white p-4 text-center"
        >
            <h3 id="titulo-pix" className="text-sm font-semibold">
                Pague com Pix
            </h3>

            {/* QR code: 220 px (acima do mínimo de 200 px); o leitor de tela lê o rótulo. */}
            <div
                role="img"
                aria-label="QR code do Pix com o valor da inscrição"
                className="size-[220px] [&>svg]:size-full"
                dangerouslySetInnerHTML={{ __html: pix.qr_svg }}
            />

            <Button type="button" className="min-h-11 w-full" onClick={copiar}>
                {copiado ? 'Código copiado!' : 'Copiar código Pix'}
            </Button>
            {/* Aviso para leitor de tela quando copia (o texto do botão muda, mas alguns leitores não anunciam). */}
            <span className="sr-only" aria-live="polite">
                {copiado ? 'Código Pix copiado.' : ''}
            </span>

            <p className="text-xs text-[#5c5644]">
                No app do banco, escolha "Pix copia e cola" e cole. O valor já
                vai preenchido.
            </p>

            {/* O código também em texto: para quem não pode copiar ou usa leitor de tela. */}
            <details className="w-full text-left text-xs">
                <summary className="cursor-pointer py-2 font-medium">
                    Ver o código em texto
                </summary>
                <p className="font-mono [overflow-wrap:anywhere]">
                    {pix.copia_e_cola}
                </p>
            </details>

            <p className="text-xs">
                Identificador da sua inscrição:{' '}
                <strong className="font-mono">{pix.txid}</strong>
            </p>

            {/* Segurança: conferir o favorecido no app do banco antes de confirmar. */}
            <p
                role="note"
                className="rounded-md bg-[#efe8d6] p-3 text-left text-xs text-[#3f3a2c]"
            >
                <strong>Antes de confirmar no app do banco</strong>, confira se
                quem recebe é <strong>{favorecido || 'o IBTO'}</strong>
                {chave ? ` (chave ${chave})` : ''}. Se aparecer outro nome, não
                pague e avise o IBTO pelo suporte.
            </p>
        </section>
    );
}
