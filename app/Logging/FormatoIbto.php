<?php

/*
 * FORMATO DO LOG DO IBTO (#74): cada linha do log vira um JSON (uma "ficha" com
 * campos fixos: data, nível, código do erro, contexto e o identificador do pedido),
 * e passa antes pela máscara de dados pessoais.
 *
 * Por que JSON? Dá para filtrar por campo (ex.: todos os "PIX_FALHOU" do pedido
 * 9f3c...) em vez de ler texto solto. Ligado em config/logging.php pelo "tap"
 * (um gancho que o Laravel chama ao montar cada canal de log).
 */

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;

class FormatoIbto
{
    /**
     * Aplica o JSON e a máscara em todos os "escritores" (handlers) do canal.
     *
     * @param  Logger  $logger  O canal de log que o Laravel está montando (ex.: 'daily').
     */
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        // Só o Monolog tem "handlers"; outro tipo de logger fica como está.
        if (! $monolog instanceof \Monolog\Logger) {
            return;
        }

        foreach ($monolog->getHandlers() as $escritor) {
            // Uma linha JSON por registro (o arquivo continua fácil de ler e de baixar pelo FTP).
            if ($escritor instanceof FormattableHandlerInterface) {
                $escritor->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true));
            }
            // A máscara roda por último antes de gravar, inclusive sobre o que outros acrescentaram.
            if ($escritor instanceof ProcessableHandlerInterface) {
                $escritor->pushProcessor(new MascararDadosPessoais);
            }
        }
    }
}
