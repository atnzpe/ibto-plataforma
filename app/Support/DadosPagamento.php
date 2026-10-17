<?php

/*
 * QUAIS DADOS PARA PAGAMENTO valem para um curso (#41; pedido do Gleyson em
 * 10/10/2026: "as duas opções e uma chave liga/desliga"):
 * - chave "por curso" DESLIGADA: sempre os dados do IBTO;
 * - chave LIGADA e o curso com chave PIX própria: os dados do CURSO;
 * - chave LIGADA, mas o curso sem dados próprios: os do IBTO.
 * Como o preço na etiqueta do PDV: se o produto tem preço promocional e a
 * promoção está ligada, vale ele; senão, vale o preço de tabela.
 */

namespace App\Support;

use App\Models\Curso;
use App\Models\PagamentoConfig;

class DadosPagamento
{
    /**
     * Os dados que o aluno vê para pagar este curso.
     *
     * @param  Curso  $curso  O curso. Ex.: curso de equipe com PIX próprio.
     * @return array{origem: string, pix_chave: string, favorecido: string, banco: string, agencia: string, conta: string, instrucoes: string}
     *                                                                                                                                         origem: 'curso' (dados do curso) ou 'ibto'. Vazio = não cadastrado.
     */
    public static function doCurso(Curso $curso): array
    {
        $ibto = PagamentoConfig::atual();

        // Chave ligada E o curso tem PIX próprio: vale o do curso.
        if ($ibto->por_curso && filled($curso->pag_pix_chave)) {
            return [
                'origem' => 'curso',
                'pix_chave' => (string) $curso->pag_pix_chave,
                'favorecido' => (string) $curso->pag_favorecido,
                'banco' => (string) $curso->pag_banco,
                'agencia' => (string) $curso->pag_agencia,
                'conta' => (string) $curso->pag_conta,
                // As instruções são sempre as do IBTO (o passo a passo é o mesmo).
                'instrucoes' => (string) $ibto->instrucoes,
            ];
        }

        // Senão (chave desligada ou curso sem dados próprios): os do IBTO.
        return self::doIbto();
    }

    /**
     * Os dados para pagamento do IBTO (tela "Dados para pagamento"). Usada também
     * na venda de fotos (#90), que recebe sempre na conta do IBTO.
     *
     * @return array{origem: string, pix_chave: string, favorecido: string, banco: string, agencia: string, conta: string, instrucoes: string}
     */
    public static function doIbto(): array
    {
        $ibto = PagamentoConfig::atual();

        return [
            'origem' => 'ibto',
            'pix_chave' => (string) $ibto->pix_chave,
            'favorecido' => (string) $ibto->favorecido,
            'banco' => (string) $ibto->banco,
            'agencia' => (string) $ibto->agencia,
            'conta' => (string) $ibto->conta,
            'instrucoes' => (string) $ibto->instrucoes,
        ];
    }
}
