/**
 * Interpreta a resposta da rota /api/saude para a página inicial.
 *
 * Fica separada do componente para poder ser testada sem navegador
 * (tests/js/saude.test.ts). Cada falha tem um código estável, que aparece
 * na tela e ajuda a achar o problema sem expor detalhes internos.
 */

export type EstadoSaude =
    | { estado: 'ok'; mensagem: string }
    | { estado: 'erro'; mensagem: string; codigo: string };

export const CODIGO_SEM_RESPOSTA = 'SAUDE_SEM_RESPOSTA';
export const CODIGO_RESPOSTA_INVALIDA = 'SAUDE_RESPOSTA_INVALIDA';

/**
 * @param httpStatus status HTTP recebido, ou null quando a requisição nem chegou ao servidor
 * @param corpo JSON já lido da resposta (ou null se não era JSON)
 */
export function interpretarSaude(
    httpStatus: number | null,
    corpo: unknown,
): EstadoSaude {
    if (httpStatus === null) {
        return {
            estado: 'erro',
            mensagem: 'Não foi possível falar com o servidor.',
            codigo: CODIGO_SEM_RESPOSTA,
        };
    }

    const dados =
        typeof corpo === 'object' && corpo !== null
            ? (corpo as Record<string, unknown>)
            : {};

    if (httpStatus === 200 && dados.status === 'ok' && dados.banco === 'ok') {
        return {
            estado: 'ok',
            mensagem: 'Plataforma no ar e banco de dados conectado.',
        };
    }

    if (typeof dados.codigo === 'string' && dados.codigo !== '') {
        return {
            estado: 'erro',
            mensagem: 'A plataforma está no ar, mas com problema.',
            codigo: dados.codigo,
        };
    }

    return {
        estado: 'erro',
        mensagem: 'O servidor respondeu de um jeito inesperado.',
        codigo: CODIGO_RESPOSTA_INVALIDA,
    };
}
