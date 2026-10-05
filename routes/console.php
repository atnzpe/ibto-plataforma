<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No servidor (sem SSH), um Cron Job do cPanel roda `php artisan schedule:run`
// a cada 5 minutos; cada tarefa abaixo roda em toda chamada. Guia:
// docs/publicacao-homologacao.md.
Schedule::command('plataforma:pos-deploy')->everyMinute()->withoutOverlapping();

// Processa a fila (e-mails, por exemplo) e para quando ela esvazia, porque a
// hospedagem compartilhada não mantém processos rodando.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=240')
    ->everyMinute()
    ->withoutOverlapping();
