<?php

/*
 * RODA A CARGA DO CONTEÚDO EXTRA DO PILOTO (17/10/2026) uma vez, ao publicar.
 *
 * O servidor da HostGator não tem SSH: a forma de rodar um comando lá é pela
 * migration (que o Cron aplica no pós-deploy). O comando não duplica nada
 * (pula pelo título). Nos TESTES automáticos não roda, para cada teste começar
 * com o banco limpo.
 *
 * TRAVA DA PRODUÇÃO (15/11/2026): a homologação usa APP_ENV=production, então
 * a trava é o ENDEREÇO: só roda se o APP_URL tiver "homolog", "localhost",
 * "127.0.0.1" ou ".test". Sem isso, o banco NOVO da produção (#56) nasceria
 * com os posts e álbuns "[TESTE]" no blog público.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

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

        // O endereço da plataforma neste servidor. Ex.: 'https://cursos-homolog.ib-to.org'.
        $endereco = (string) config('app.url');

        // Não é homologação nem computador local (ex.: produção): sai sem cadastrar nada.
        // Na homologação esta migration já rodou em 17/10/2026, então a trava não muda nada lá.
        if (! Str::contains($endereco, ['homolog', 'localhost', '127.0.0.1', '.test'])) {
            return;
        }

        Artisan::call('plataforma:carregar-conteudo-piloto');
    }

    /**
     * Desfaz: nada (o conteúdo de teste sai pela limpeza antes da produção).
     */
    public function down(): void {}
};
