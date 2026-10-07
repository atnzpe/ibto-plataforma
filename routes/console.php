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
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=240')
    ->everyMinute()
    ->withoutOverlapping();
