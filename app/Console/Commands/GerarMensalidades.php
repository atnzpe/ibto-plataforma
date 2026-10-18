<?php

/*
 * GERAR AS MENSALIDADES DO MÊS (#80): todo dia, o agendador roda este comando.
 * Para cada assinatura que vence em até 3 dias, cria a mensalidade do mês (com o
 * Pix) e manda o aviso por e-mail. Não repete: quem já tem a do mês é pulado.
 */

namespace App\Console\Commands;

use App\Actions\AulasRegulares\Assinaturas;
use Illuminate\Console\Command;

class GerarMensalidades extends Command
{
    protected $signature = 'aulas:gerar-mensalidades';

    protected $description = 'Gera a mensalidade das aulas regulares de quem vence em até 3 dias e avisa por e-mail';

    /**
     * Gera e mostra quantas foram geradas.
     *
     * @param  Assinaturas  $assinaturas  As regras das assinaturas.
     * @return int 0 = deu certo.
     */
    public function handle(Assinaturas $assinaturas): int
    {
        $geradas = $assinaturas->gerarMensalidades();
        $this->info("Mensalidades geradas: {$geradas}.");

        return self::SUCCESS;
    }
}
