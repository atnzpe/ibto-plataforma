<?php

/*
 * GERADOR DO PIX "COPIA E COLA" (#83), o mesmo texto que vai dentro do QR code.
 *
 * Segue o BR Code do Banco Central (padrão EMV): uma fila de campos, cada um
 * escrito como ID (2 dígitos) + TAMANHO (2 dígitos) + VALOR. Ex.: o campo 58
 * com "BR" vira "5802BR". No fim vem o campo 63, o "dígito verificador" (CRC16):
 * se qualquer letra mudar no caminho, o app do banco recusa o código, como o
 * dígito do CPF.
 *
 * Este é o Pix ESTÁTICO: a plataforma calcula sozinha, sem banco, sem contrato e
 * sem internet (milissegundos, custo zero). A confirmação continua manual. O Pix
 * dinâmico (confirmação automática pelo banco) fica para a #42.
 *
 * Nenhum dado pessoal do aluno entra no código: só a chave do IBTO, o valor, o
 * nome do favorecido, a cidade e o identificador da matrícula (txid).
 */

namespace App\Support;

use Illuminate\Support\Str;

class PixCopiaECola
{
    /**
     * Monta o código "copia e cola".
     *
     * @param  string  $chave  A chave Pix de quem recebe. Ex.: '35.855.768/0001-21' (CNPJ), 'pix@ib-to.org' ou uma chave aleatória.
     * @param  string  $favorecido  Nome de quem recebe (no máximo 25 letras; acentos saem). Ex.: 'Instituto Brasileiro de Treinamento Operacional'.
     * @param  string  $cidade  Cidade de quem recebe (no máximo 15 letras). Ex.: 'Recife'.
     * @param  int|null  $valorCentavos  O valor exato em centavos (ex.: 15000 = R$ 150,00), ou null para o pagador digitar.
     * @param  string  $txid  Identificador da cobrança (só letras e números, até 25). Ex.: 'IBTOMAT1234'. '***' = sem identificador.
     * @return string O código pronto. Ex.: '00020126360014br.gov.bcb.pix0114358557680001215204...6304ABCD'.
     */
    public static function gerar(string $chave, string $favorecido, string $cidade, ?int $valorCentavos, string $txid): string
    {
        // Campo 26 (dados da conta Pix): o "nome" do arranjo Pix + a chave, já no formato que os bancos esperam.
        $contaPix = self::campo('00', 'br.gov.bcb.pix').self::campo('01', self::normalizarChave($chave));

        $codigo = self::campo('00', '01')                      // versão do padrão (sempre 01)
            .self::campo('26', $contaPix)                      // a conta Pix de quem recebe
            .self::campo('52', '0000')                         // categoria do comércio (0000 = não informada)
            .self::campo('53', '986');                         // moeda: 986 = real brasileiro

        // Valor exato: "150.00" (ponto como separador, como o padrão pede). Sem valor, o campo não vai
        // e o app do banco pede para a pessoa digitar.
        if ($valorCentavos !== null && $valorCentavos > 0) {
            $codigo .= self::campo('54', number_format($valorCentavos / 100, 2, '.', ''));
        }

        $codigo .= self::campo('58', 'BR')                     // país
            .self::campo('59', self::texto($favorecido, 25))   // nome de quem recebe
            .self::campo('60', self::texto($cidade, 15))       // cidade de quem recebe
            .self::campo('62', self::campo('05', self::txid($txid))); // identificador da cobrança

        // Campo 63 (CRC16): calculado sobre TUDO, incluindo o próprio "6304" do começo dele.
        $codigo .= '6304';

        return $codigo.self::crc16($codigo);
    }

    /**
     * Escreve um campo no formato ID + TAMANHO + VALOR.
     *
     * @param  string  $id  O número do campo, com 2 dígitos. Ex.: '58'.
     * @param  string  $valor  O conteúdo. Ex.: 'BR'.
     * @return string Ex.: '5802BR'.
     */
    private static function campo(string $id, string $valor): string
    {
        return $id.str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT).$valor;
    }

    /**
     * Deixa a chave no formato que os bancos aceitam: CPF e CNPJ só com números,
     * e-mail em minúsculas, telefone com +55. Chave aleatória fica como está.
     *
     * @param  string  $chave  Ex.: '35.855.768/0001-21'.
     * @return string Ex.: '35855768000121'.
     */
    public static function normalizarChave(string $chave): string
    {
        $chave = trim($chave);

        // E-mail: minúsculas (o Pix não diferencia, mas alguns bancos recusam maiúsculas).
        if (str_contains($chave, '@')) {
            return Str::lower($chave);
        }

        // Chave aleatória (formato 8-4-4-4-12, com letras e hífens): fica igual, em minúsculas.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $chave) === 1) {
            return Str::lower($chave);
        }

        // Telefone já com "+": só o "+" e os números. Ex.: '+55 (81) 99999-0000' → '+5581999990000'.
        if (str_starts_with($chave, '+')) {
            return '+'.preg_replace('/\D/', '', $chave);
        }

        // Senão é CPF (11 dígitos) ou CNPJ (14): só os números, sem pontos, barra e traço.
        return (string) preg_replace('/\D/', '', $chave);
    }

    /**
     * Texto sem acentos e com tamanho máximo (o padrão só aceita letras simples).
     *
     * @param  string  $texto  Ex.: 'Instituto Brasileiro de Treinamento Operacional'.
     * @param  int  $maximo  Quantas letras cabem. Ex.: 25.
     * @return string Ex.: 'Instituto Brasileiro de T'.
     */
    private static function texto(string $texto, int $maximo): string
    {
        // Tira acentos (ã → a, ç → c) e qualquer caractere fora do básico.
        $simples = (string) preg_replace('/[^A-Za-z0-9 .\-]/', '', Str::ascii(trim($texto)));

        // Corta no tamanho e tira espaço que sobrar no fim.
        return rtrim(substr($simples, 0, $maximo));
    }

    /**
     * Identificador da cobrança: só letras e números, até 25. Vazio vira '***' (sem identificador).
     *
     * @param  string  $txid  Ex.: 'IBTOMAT1234'.
     * @return string Ex.: 'IBTOMAT1234'.
     */
    private static function txid(string $txid): string
    {
        // '***' é o valor oficial para "sem identificador": passa como está.
        if ($txid === '***') {
            return $txid;
        }

        $limpo = substr((string) preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25);

        return $limpo === '' ? '***' : $limpo;
    }

    /**
     * O dígito verificador do código: CRC16-CCITT (polinômio 0x1021, começo 0xFFFF),
     * em 4 letras/números maiúsculos. É a conta que o app do banco refaz para
     * conferir se o código chegou inteiro.
     *
     * @param  string  $texto  O código até o "6304".
     * @return string Ex.: '1D3D'.
     */
    public static function crc16(string $texto): string
    {
        $crc = 0xFFFF;

        // Para cada letra, mistura o código dela no resultado, bit a bit.
        foreach (str_split($texto) as $letra) {
            $crc ^= ord($letra) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                // Bit mais alto ligado: desloca e aplica o polinômio; senão, só desloca.
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                // Mantém só 16 bits (o PHP guardaria números maiores).
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Identificador da matrícula para o Pix e para conferir no extrato.
     *
     * @param  int  $matriculaId  O número da matrícula. Ex.: 1234.
     * @return string Ex.: 'IBTOMAT1234'.
     */
    public static function txidDaMatricula(int $matriculaId): string
    {
        return 'IBTOMAT'.$matriculaId;
    }
}
