import { describe, expect, test } from 'bun:test';
import {
    CODIGO_RESPOSTA_INVALIDA,
    CODIGO_SEM_RESPOSTA,
    interpretarSaude,
} from '../../resources/js/lib/saude';

describe('interpretarSaude', () => {
    test('200 com banco ok vira estado ok', () => {
        expect(interpretarSaude(200, { status: 'ok', banco: 'ok' })).toEqual({
            estado: 'ok',
            mensagem: 'Plataforma no ar e banco de dados conectado.',
        });
    });

    test('503 repassa o código enviado pelo servidor', () => {
        const resultado = interpretarSaude(503, {
            status: 'erro',
            codigo: 'SAUDE_BANCO_INDISPONIVEL',
        });

        expect(resultado.estado).toBe('erro');
        expect(resultado).toMatchObject({ codigo: 'SAUDE_BANCO_INDISPONIVEL' });
    });

    test('sem resposta do servidor usa o código próprio', () => {
        expect(interpretarSaude(null, null)).toMatchObject({
            estado: 'erro',
            codigo: CODIGO_SEM_RESPOSTA,
        });
    });

    test('resposta que não é o JSON esperado é tratada como inválida', () => {
        for (const [status, corpo] of [
            [200, null],
            [200, { status: 'ok' }],
            [500, 'Internal Server Error'],
            [404, { codigo: '' }],
        ] as const) {
            expect(interpretarSaude(status, corpo)).toMatchObject({
                estado: 'erro',
                codigo: CODIGO_RESPOSTA_INVALIDA,
            });
        }
    });
});
