<?php

/*
 * RODA A CARGA DO CONTEÚDO EXTRA DO PILOTO (17/10/2026) uma vez, ao publicar.
 *
 * O servidor da HostGator não tem SSH: a forma de rodar um comando lá é pela
 * migration (que o Cron aplica no pós-deploy). O comando não duplica nada
 * (pula pelo título). Nos TESTES automáticos não roda, para cada teste começar
 * com o banco limpo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Carrega o Curso Tático Avançado e os posts e álbuns de teste.
     */
    public function up(): void
    {
        // Testes automáticos: pula (o comando tem o teste próprio).
        if (app()->environment('testing')) {
            return;
        }

        Artisan::call('plataforma:carregar-conteudo-piloto');
    }

    /**
     * Desfaz: nada (o conteúdo de teste sai pela limpeza antes da produção).
     */
    public function down(): void {}
};
