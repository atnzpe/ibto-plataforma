<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No servidor (sem SSH), um Cron Job do cPanel roda a cada 5 minutos
// `php artisan plataforma:pos-deploy; php artisan schedule:run`; cada tarefa
// abaixo roda em toda chamada do agendador. Guia: docs/publicacao-homologacao.md.
//
// O plataforma:pos-deploy (migrations e caches) NÃO fica aqui de propósito: o
// agendador usa a tabela `cache` do banco para a trava "withoutOverlapping", e
// essa tabela só existe depois das migrations. Se as migrations dependessem do
// agendador, o primeiro deploy nunca andaria ("ovo e galinha"). Por isso o Cron
// chama o pós-deploy direto, antes do agendador.

// Processa a fila (e-mails, por exemplo) e para quando ela esvazia, porque a
// hospedagem compartilhada não mantém processos rodando.
// --backoff=60 (17/10/2026): se um envio falhar (ex.: o Titan fechou a conexão),
// a nova tentativa espera 1 minuto, em vez de as 3 tentativas acontecerem no mesmo
// segundo e o e-mail se perder. O próximo minuto do agendador pega a tentativa.
Schedule::command('queue:work --stop-when-empty --tries=3 --backoff=60 --max-time=240')
    ->everyMinute()
    ->withoutOverlapping();

// Venda de fotos (#90): uma vez por dia, apaga as fotos dos álbuns que já expiraram
// (guarda os pedidos e o financeiro). Saúde e tamanho do servidor.
Schedule::command('fotos:expirar')
    ->dailyAt('03:00')
    ->withoutOverlapping();
