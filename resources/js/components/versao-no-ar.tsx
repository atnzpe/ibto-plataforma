/**
 * VERSÃO NO AR (#75): "v0.2.0 · a1b2c3d" no rodapé do menu e da vitrine.
 * O número vem do arquivo VERSION (versionamento semântico) e o código curto do
 * commit vem do deploy. Ajuda o suporte a saber exatamente o que está no ar
 * quando alguém relata um problema. Texto pequeno e discreto.
 */
import { usePage } from '@inertiajs/react';

/**
 * Não recebe argumentos: lê a versão compartilhada pelo servidor.
 *
 * @returns Uma linha pequena com a versão (e o commit, quando houver).
 */
export default function VersaoNoAr() {
    // A versão no ar. Ex.: { numero: '0.2.0', commit: 'a1b2c3d' }.
    const { versao } = usePage<{
        versao?: { numero: string; commit: string | null };
    }>().props;

    // Sem versão (situação rara): não mostra nada.
    if (!versao) {
        return null;
    }

    return (
        <p
            className="px-2 text-[11px] text-muted-foreground"
            aria-label={`Versão da plataforma ${versao.numero}`}
        >
            v{versao.numero}
            {versao.commit ? ` · ${versao.commit}` : ''}
        </p>
    );
}
