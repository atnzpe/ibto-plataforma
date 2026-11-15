<?php

/*
 * RODA A CARGA DO CONGRESSO NACIONAL IBTO (15/11/2026) uma vez, ao publicar.
 *
 * O servidor da HostGator não tem SSH: a forma de rodar um comando lá é pela
 * migration (que o Cron aplica no pós-deploy). O comando não duplica nada
 * (pula pelo título).
 *
 * TRAVA DA PRODUÇÃO: a homologação usa APP_ENV=production, então o ambiente não
 * serve para separar os dois servidores. A trava é o ENDEREÇO: só roda se o
 * APP_URL tiver "homolog" (cursos-homolog.ib-to.org) ou "localhost"/".test"
 * (computador do desenvolvedor). Na produção (cursos.ib-to.org), não faz nada.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Cadastra o congresso de exemplo, só na homologação ou no computador local.
     */
    public function up(): void
    {
        // Testes automáticos: pula (o comando tem o teste próprio e cada teste começa com o banco limpo).
        if (app()->environment('testing')) {
            return;
        }

        // O endereço da plataforma neste servidor. Ex.: 'https://cursos-homolog.ib-to.org'.
        $endereco = (string) config('app.url');

        // Não é homologação nem computador local (ex.: produção): sai sem cadastrar nada.
        if (! Str::contains($endereco, ['homolog', 'localhost', '127.0.0.1', '.test'])) {
            return;
        }

        Artisan::call('plataforma:carregar-congresso');
    }

    /**
     * Desfaz: nada (o conteúdo "[TESTE]" sai pela limpeza antes da produção, #56).
     */
    public function down(): void {}
};
