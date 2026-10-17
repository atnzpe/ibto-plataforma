<?php

/*
 * MÁSCARA DE DADOS PESSOAIS NO LOG (#74, LGPD).
 *
 * Toda linha do log passa por aqui antes de ser gravada. Campos com nome de
 * dado pessoal ou secreto (CPF, e-mail, telefone, nascimento, endereço, senha,
 * token) viram "[oculto]"; e, no texto da mensagem, um CPF ou um e-mail escrito
 * por engano também é escondido. É a última barreira: o código já evita mandar
 * esses dados, mas, se alguém esquecer, eles não chegam ao arquivo.
 * Como a tarja preta num documento antes de ele ir para o arquivo morto.
 */

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class MascararDadosPessoais implements ProcessorInterface
{
    /**
     * Nomes de campo (ou pedaços do nome) que nunca podem ir para o log.
     * Ex.: 'cpf' pega 'cpf' e 'cpf_hash'; 'senha' pega 'nova_senha'.
     */
    private const CAMPOS_PROIBIDOS = ['cpf', 'email', 'e-mail', 'telefone', 'celular', 'whatsapp', 'nascimento', 'endereco', 'logradouro', 'senha', 'password', 'token', 'secret', 'chave_pix'];

    /**
     * Nomes curtos que só valem como nome EXATO do campo (como pedaço, 'cep'
     * esconderia 'excepcional' e 'rg' esconderia 'cargo').
     */
    private const CAMPOS_PROIBIDOS_EXATOS = ['cep', 'rg'];

    /** O que aparece no lugar do dado escondido. */
    private const OCULTO = '[oculto]';

    /**
     * Esconde os dados pessoais de uma linha do log.
     *
     * @param  LogRecord  $registro  A linha (mensagem + contexto + extras).
     * @return LogRecord A mesma linha, já mascarada.
     */
    public function __invoke(LogRecord $registro): LogRecord
    {
        return $registro->with(
            message: $this->limparTexto($registro->message),
            context: $this->limparLista($registro->context),
            extra: $this->limparLista($registro->extra),
        );
    }

    /**
     * Passa por cada campo da lista (e pelas listas de dentro dela).
     *
     * @param  array<mixed>  $lista  Ex.: ['usuario_id' => 7, 'cpf' => '529.982.247-25'].
     * @return array<mixed> Ex.: ['usuario_id' => 7, 'cpf' => '[oculto]'].
     */
    private function limparLista(array $lista): array
    {
        foreach ($lista as $chave => $valor) {
            // Nome proibido: esconde o valor inteiro, seja ele qual for.
            if (is_string($chave) && $this->nomeProibido($chave)) {
                $lista[$chave] = self::OCULTO;

                continue;
            }
            // Lista dentro da lista: limpa também. Texto: procura CPF e e-mail. O resto (números) fica.
            if (is_array($valor)) {
                $lista[$chave] = $this->limparLista($valor);
            } elseif (is_string($valor)) {
                $lista[$chave] = $this->limparTexto($valor);
            }
        }

        return $lista;
    }

    /**
     * O nome do campo tem algum pedaço proibido? (sem diferença de maiúsculas)
     *
     * @param  string  $chave  Ex.: 'cpf_hash'.
     * @return bool true = esconder; false = pode gravar.
     */
    private function nomeProibido(string $chave): bool
    {
        $chave = strtolower($chave);
        // Nome curto (cep, rg): só esconde se for exatamente ele.
        if (in_array($chave, self::CAMPOS_PROIBIDOS_EXATOS, true)) {
            return true;
        }
        // Os outros: basta ter o pedaço no nome (ex.: 'cpf_hash' tem 'cpf').
        foreach (self::CAMPOS_PROIBIDOS as $pedaco) {
            if (str_contains($chave, $pedaco)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Esconde CPF (com ou sem pontos) e e-mail dentro de um texto.
     *
     * @param  string  $texto  Ex.: 'falhou para fulano@ibto.org, CPF 529.982.247-25'.
     * @return string Ex.: 'falhou para [oculto], CPF [oculto]'.
     */
    private function limparTexto(string $texto): string
    {
        // CPF: 000.000.000-00 ou 11 números seguidos (sem número colado antes ou depois).
        $texto = (string) preg_replace('/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/', self::OCULTO, $texto);

        // E-mail: algo@algo.algo.
        return (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', self::OCULTO, $texto);
    }
}
