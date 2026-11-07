/**
 * GUARDA DAS FOTOS INTEIRAS (#105): nenhuma tela da plataforma volta a cortar
 * foto com "object-cover" sem querer. O recorte só vale nas EXCEÇÕES da issue
 * (cada uma com o motivo). Foto nova: use o componente FotoInteira.
 */
import { describe, expect, test } from 'bun:test';
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';

// Pasta com as telas e os componentes (o código do navegador).
const RAIZ = join(import.meta.dir, '..', '..', 'resources', 'js');

// Onde o recorte é o CERTO, com o motivo (lista da issue #105).
const EXCECOES: Record<string, string> = {
    // O próprio componente usa o recorte só no FUNDO desfocado (a frente é inteira).
    'components/foto-inteira.tsx': 'fundo desfocado do próprio componente',
    'components/app-logo-icon.tsx': 'logo redondo do menu',
    'components/foto-filiado.tsx': 'foto 3x4, padrão de documento',
    'pages/carteirinha/minha.tsx':
        'foto 3x4 da carteirinha, padrão de documento',
    'pages/carteirinha/validar.tsx':
        'foto 3x4 da carteirinha, padrão de documento',
    // Sem altura fixa: a capa aparece no tamanho natural, inteira.
    'pages/blog/post.tsx': 'capa sem altura fixa (não corta)',
};

/**
 * Lista todos os arquivos .tsx de uma pasta, entrando nas subpastas.
 *
 * @param pasta O caminho da pasta. Ex.: RAIZ.
 * @returns Os caminhos completos dos arquivos .tsx.
 */
function arquivosTsx(pasta: string): string[] {
    return readdirSync(pasta, { withFileTypes: true }).flatMap((item) => {
        const caminho = join(pasta, item.name);

        // Subpasta: entra nela. Arquivo .tsx: entra na lista. Outro arquivo: fica de fora.
        if (item.isDirectory()) {
            return arquivosTsx(caminho);
        }

        return item.name.endsWith('.tsx') ? [caminho] : [];
    });
}

describe('fotos inteiras (#105)', () => {
    test('só as exceções da issue usam object-cover', () => {
        const comRecorte = arquivosTsx(RAIZ)
            // Caminho curto com barras normais. Ex.: 'pages/galeria/index.tsx'.
            .map((caminho) => relative(RAIZ, caminho).replaceAll('\\', '/'))
            .filter(
                (curto) =>
                    readFileSync(join(RAIZ, curto), 'utf8').includes(
                        'object-cover',
                    ) && !(curto in EXCECOES),
            );

        // A mensagem do erro lista as telas que cortam foto (para quem for corrigir).
        expect(comRecorte).toEqual([]);
    });
});
