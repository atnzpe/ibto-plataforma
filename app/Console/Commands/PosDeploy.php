<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Termina a publicação no servidor, que não tem SSH (HostGator).
 *
 * O deploy (FTPS) grava o commit publicado em VERSAO_DEPLOY, na raiz do
 * projeto. O Cron Job roda o agendador a cada poucos minutos e ele chama este
 * comando: se a versão publicada for diferente da última aplicada, roda as
 * migrations e refaz os caches; senão, não faz nada. Se algo falhar, a versão
 * não é marcada como aplicada e a próxima rodada tenta de novo.
 */
class PosDeploy extends Command
{
    public const CODIGO_FALHA = 'POS_DEPLOY_FALHOU';

    protected $signature = 'plataforma:pos-deploy';

    protected $description = 'Roda as migrations e refaz os caches quando chega uma versão nova pelo deploy';

    public function handle(): int
    {
        $arquivoPublicado = base_path('VERSAO_DEPLOY');
        $arquivoAplicado = storage_path('app/versao-aplicada');

        if (! File::exists($arquivoPublicado)) {
            $this->info('Sem VERSAO_DEPLOY: nada a fazer (ambiente local).');

            return self::SUCCESS;
        }

        $publicada = trim(File::get($arquivoPublicado));
        $aplicada = File::exists($arquivoAplicado) ? trim(File::get($arquivoAplicado)) : '';

        if ($publicada === '' || $publicada === $aplicada) {
            return self::SUCCESS;
        }

        try {
            Artisan::call('migrate', ['--force' => true], $this->output);
            Artisan::call('optimize:clear', [], $this->output);
            Artisan::call('optimize', [], $this->output);
        } catch (Throwable $erro) {
            // Só o código e o tipo do erro: a mensagem pode trazer dados do banco.
            Log::error(self::CODIGO_FALHA, ['versao' => $publicada, 'excecao' => $erro::class]);
            $this->error(self::CODIGO_FALHA);

            return self::FAILURE;
        }

        File::put($arquivoAplicado, $publicada);
        Log::info('POS_DEPLOY_APLICADO', ['versao' => $publicada]);
        $this->info("Versão {$publicada} aplicada.");

        return self::SUCCESS;
    }
}
