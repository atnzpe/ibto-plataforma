<?php

/*
 * Testes do E-MAIL NA FILA (17/10/2026): antes de cada tarefa da fila, a
 * conexão de e-mail é "esquecida" (cada envio abre uma nova, porque o Titan
 * fecha a conexão parada); e o agendador tenta de novo só depois de 1 minuto.
 */

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;

test('antes de cada tarefa da fila, o mailer guardado é esquecido', function () {
    // Abre (e guarda) o mailer padrão, como um envio anterior faria.
    $antes = app('mail.manager')->mailer();

    // Simula o aviso "uma tarefa da fila vai começar".
    event(new JobProcessing('sync', new SyncJob(app(), '{}', 'sync', 'default')));

    // Depois do aviso, pedir o mailer devolve um NOVO (conexão nova).
    expect(app('mail.manager')->mailer())->not->toBe($antes);
});

test('o trabalhador da fila espera 1 minuto entre as tentativas', function () {
    $comandos = collect(app(Schedule::class)->events())->map(fn ($evento) => (string) $evento->command)->implode("\n");

    expect($comandos)->toContain('queue:work')->toContain('--backoff=60');
});
