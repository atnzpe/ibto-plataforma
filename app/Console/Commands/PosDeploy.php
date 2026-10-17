<?php

namespace App\Console\Commands;

use App\Models\User;
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
 *
 * Placa de manutenção (16/10/2026): o deploy põe a plataforma "em atualização"
 * (arquivo storage/framework/down, o mesmo do "php artisan down") ANTES de
 * enviar o código. Assim, entre a chegada do código novo e as migrations,
 * quem abre a página vê "em atualização" (503), e não um erro 500 por falta
 * de coluna no banco. Este comando tira a placa quando termina tudo; se der
 * erro, a placa fica (melhor "em atualização" do que tela quebrada).
 */
class PosDeploy extends Command
{
    public const CODIGO_FALHA = 'POS_DEPLOY_FALHOU';

    // Palavra que o deploy grava dentro da placa, para este comando só tirar a
    // placa que o DEPLOY pôs (nunca um "php artisan down" feito de propósito).
    public const MARCA_DO_DEPLOY = 'deploy-homologacao';

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

        // Nada novo para aplicar (ex.: o deploy rodou de novo com o mesmo commit):
        // só tira a placa, se ela ficou do deploy.
        if ($publicada === '' || $publicada === $aplicada) {
            $this->tirarPlacaDoDeploy();
            $this->carregarPilotoSeLigado();
            $this->carregarContaCompletaSeLigado();
            $this->carregarStakeholdersSeLigado();
            $this->gravarEstadoDoPiloto();

            return self::SUCCESS;
        }

        try {
            Artisan::call('migrate', ['--force' => true], $this->output);
            // optimize:clear também limpa o cache: a versão do banco e a última migration (VersaoNoAr) se atualizam.
            Artisan::call('optimize:clear', [], $this->output);
            Artisan::call('optimize', [], $this->output);
        } catch (Throwable $erro) {
            // Só o código e o tipo do erro: a mensagem pode trazer dados do banco.
            Log::error(self::CODIGO_FALHA, ['versao' => $publicada, 'excecao' => $erro::class]);
            $this->error(self::CODIGO_FALHA);

            return self::FAILURE;
        }

        File::put($arquivoAplicado, $publicada);
        // Banco e caches prontos: a plataforma volta ao ar.
        $this->tirarPlacaDoDeploy();
        Log::info('POS_DEPLOY_APLICADO', ['versao' => $publicada]);
        $this->carregarPilotoSeLigado();
        $this->carregarContaCompletaSeLigado();
        $this->carregarStakeholdersSeLigado();
        $this->gravarEstadoDoPiloto();
        $this->info("Versão {$publicada} aplicada.");

        return self::SUCCESS;
    }

    /**
     * Carga da base do piloto (16/10/2026): roda "plataforma:carregar-piloto" UMA vez,
     * só com PILOTO_CARREGAR=true no .env. Sempre DEPOIS das migrations (as tabelas
     * precisam existir). Se der erro, anota o código e tenta de novo na próxima rodada.
     */
    protected function carregarPilotoSeLigado(): void
    {
        // Marca de "já carregado": impede de rodar de novo a cada 5 minutos.
        $marca = storage_path('app/piloto-carregado');

        // Desligado no .env ou já carregado: não faz nada.
        if (! config('app.piloto_carregar') || File::exists($marca)) {
            return;
        }

        try {
            Artisan::call('plataforma:carregar-piloto', [], $this->output);
            File::put($marca, now()->toDateTimeString());
            // A confirmação é a própria marca: o job "Diagnóstico homologação" lista
            // storage/app/ com data e hora (o log do servidor só grava erros).
            Log::info('PILOTO_CARREGADO');
        } catch (Throwable $erro) {
            Log::error('PILOTO_CARGA_FALHOU', ['excecao' => $erro::class]);
        }
    }

    /**
     * Conta completa do piloto (17/10/2026): com PILOTO_CONTA_COMPLETA=<e-mail> no .env,
     * roda "plataforma:conta-completa" UMA vez para esse e-mail. A marca guarda a
     * impressão digital (hash) do e-mail, não o e-mail: trocar o e-mail roda de novo.
     * Se a conta ainda não existe ou der erro, tenta de novo na próxima rodada.
     */
    protected function carregarContaCompletaSeLigado(): void
    {
        $email = strtolower(trim((string) config('app.piloto_conta_completa')));
        // Desligado (vazio): não faz nada.
        if ($email === '') {
            return;
        }

        $marca = storage_path('app/conta-completa');
        $impressao = hash('sha256', $email);
        // Já montada para este e-mail: não repete.
        if (File::exists($marca) && trim(File::get($marca)) === $impressao) {
            return;
        }

        try {
            // 0 = deu certo; a conta inexistente devolve 1 e tenta de novo depois.
            if (Artisan::call('plataforma:conta-completa', ['email' => $email], $this->output) === self::SUCCESS) {
                File::put($marca, $impressao);
                Log::info('CONTA_COMPLETA_CARREGADA');
            }
        } catch (Throwable $erro) {
            Log::error('CONTA_COMPLETA_FALHOU', ['excecao' => $erro::class]);
        }
    }

    /**
     * ESTADO DO PILOTO (17/10/2026): grava storage/app/piloto-estado a cada passada, SEM
     * dado pessoal (só sim/não e números), para o diagnóstico mostrar o que está ligado.
     * Ex.: "conta_completa_encontrada: nao" = o e-mail do .env não tem conta na plataforma.
     */
    protected function gravarEstadoDoPiloto(): void
    {
        $email = strtolower(trim((string) config('app.piloto_conta_completa')));
        // Quantas linhas a lista de stakeholders tem (sem ler nomes nem e-mails).
        $stakeholders = count(array_filter(explode(';', (string) config('app.piloto_stakeholders')), fn (string $l) => trim($l) !== ''));

        $linhas = [
            'atualizado: '.now()->format('d/m/Y H:i'),
            // Config em cache: o .env só é lido de novo depois de apagar este arquivo.
            'config_em_cache: '.(File::exists(base_path('bootstrap/cache/config.php')) ? 'sim' : 'nao'),
            'conta_completa_configurada: '.($email !== '' ? 'sim' : 'nao'),
            'conta_completa_encontrada: '.($email !== '' && User::where('email', $email)->exists() ? 'sim' : 'nao'),
            'conta_completa_montada: '.(File::exists(storage_path('app/conta-completa')) ? 'sim' : 'nao'),
            'senha_demo_configurada: '.((string) config('app.piloto_senha_demo') !== '' ? 'sim' : 'nao'),
            'stakeholders_na_lista: '.$stakeholders,
            'stakeholders_carregados: '.(File::exists(storage_path('app/piloto-stakeholders')) ? 'sim' : 'nao'),
        ];

        try {
            File::put(storage_path('app/piloto-estado'), implode("\n", $linhas)."\n");
        } catch (Throwable) {
            // Não conseguiu gravar (ex.: disco): não atrapalha o pós-deploy.
        }
    }

    /**
     * Stakeholders e contas de exemplo do piloto (17/10/2026): roda
     * "plataforma:piloto-stakeholders" quando a lista, a senha de exemplo ou a conta
     * completa mudam no .env. A marca guarda só a impressão digital (hash) delas.
     */
    protected function carregarStakeholdersSeLigado(): void
    {
        $lista = trim((string) config('app.piloto_stakeholders'));
        $senha = (string) config('app.piloto_senha_demo');
        // Nada configurado: não faz nada.
        if ($lista === '' && $senha === '') {
            return;
        }

        $marca = storage_path('app/piloto-stakeholders');
        $impressao = hash('sha256', $lista.'|'.$senha.'|'.config('app.piloto_conta_completa'));
        // Já rodou com estes mesmos dados: não repete.
        if (File::exists($marca) && trim(File::get($marca)) === $impressao) {
            return;
        }

        try {
            Artisan::call('plataforma:piloto-stakeholders', [], $this->output);
            File::put($marca, $impressao);
            Log::info('PILOTO_STAKEHOLDERS_CARREGADOS');
        } catch (Throwable $erro) {
            Log::error('PILOTO_STAKEHOLDERS_FALHOU', ['excecao' => $erro::class]);
        }
    }

    /**
     * Tira a placa "em atualização" que o deploy pôs. Não faz nada se não há placa,
     * ou se a placa foi posta por uma pessoa (sem a marca do deploy).
     */
    protected function tirarPlacaDoDeploy(): void
    {
        // O mesmo arquivo que o "php artisan down" cria.
        $placa = storage_path('framework/down');

        // Só apaga se existe E foi o deploy que pôs; senão, deixa como está.
        if (File::exists($placa) && str_contains(File::get($placa), self::MARCA_DO_DEPLOY)) {
            File::delete($placa);
            Log::info('POS_DEPLOY_PLACA_RETIRADA');
        }
    }
}
