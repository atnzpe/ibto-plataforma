/**
 * OS 3 PASSOS DA COMPRA DE FOTOS (#90, 17/10/2026): o Mestre Eduardo achou a
 * venda "complicadinha"; então a tela do aluno mostra sempre onde ele está:
 * 1) Escolha as fotos  2) Pague o Pix  3) Baixe as fotos.
 * O passo atual fica em destaque (cor e "você está aqui" para o leitor de tela).
 * Quebra linha no celular; nada de rolagem lateral.
 */

/** Os 3 passos, na ordem. */
const PASSOS = ['Escolha as fotos', 'Pague o Pix', 'Baixe as fotos'];

/**
 * @param atual O passo em que a pessoa está: 1, 2 ou 3.
 * @returns A faixa dos 3 passos.
 */
export default function PassosCompra({ atual }: { atual: 1 | 2 | 3 }) {
    return (
        <ol
            aria-label="Passos da compra"
            data-tour="passos-compra"
            className="flex flex-wrap gap-2 text-sm"
        >
            {PASSOS.map((passo, i) => {
                const numero = i + 1;
                // Já feito, o atual ou ainda por vir: muda a cor e o texto escondido.
                const feito = numero < atual;
                const agora = numero === atual;

                return (
                    <li
                        key={passo}
                        aria-current={agora ? 'step' : undefined}
                        className={`flex min-h-11 items-center gap-2 rounded-md border px-3 ${agora ? 'border-[#6b5d3a] bg-[#6b5d3a] font-semibold text-white' : feito ? 'border-green-700 text-green-800 dark:text-green-300' : 'border-border text-muted-foreground'}`}
                    >
                        <span aria-hidden="true">{feito ? '✓' : numero}</span>
                        {passo}
                        {agora && (
                            <span className="sr-only">(você está aqui)</span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
