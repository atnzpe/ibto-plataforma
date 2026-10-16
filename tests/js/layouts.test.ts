/**
 * Teste dos LAYOUTS das telas: nenhuma tela desenha o layout por conta
 * própria. O layout (menu lateral, topo, idiomas e o "?") já vem do app.tsx
 * para toda tela; se a tela o desenhar de novo, tudo aparece DUPLICADO (foi
 * o defeito da página Ajuda em 17/10/2026). Roda sem navegador: `bun test tests/js`.
 */
import { describe, expect, test } from 'bun:test';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

// A pasta das telas.
const PASTA_DAS_TELAS = join(
    import.meta.dir,
    '..',
    '..',
    'resources',
    'js',
    'pages',
);

/**
 * Lista todos os arquivos .tsx de uma pasta e das pastas dentro dela.
 *
 * @param pasta O caminho da pasta. Ex.: '.../resources/js/pages'
 * @returns Os caminhos completos dos arquivos.
 */
function telas(pasta: string): string[] {
    return readdirSync(pasta).flatMap((nome) => {
        const caminho = join(pasta, nome);
        // Pasta: entra nela. Arquivo .tsx: é uma tela. Outro arquivo: ignora.
        if (statSync(caminho).isDirectory()) {
            return telas(caminho);
        }
        return caminho.endsWith('.tsx') ? [caminho] : [];
    });
}

describe('layouts das telas', () => {
    test('nenhuma tela importa o layout principal (ele já vem do app.tsx)', () => {
        const comLayoutDuplicado = telas(PASTA_DAS_TELAS).filter((arquivo) =>
            readFileSync(arquivo, 'utf8').includes('layouts/app-layout'),
        );

        expect(comLayoutDuplicado).toEqual([]);
    });
});
