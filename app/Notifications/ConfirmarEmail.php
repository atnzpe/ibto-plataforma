<?php

/*
 * E-mail de confirmação de cadastro que vai pela FILA.
 *
 * É o mesmo e-mail padrão do Laravel (VerifyEmail), só que, em vez de sair na
 * hora do cadastro, ele é guardado na tabela `jobs` e enviado pelo Cron. Assim,
 * se o servidor de e-mail falhar, o cadastro não dá erro 500.
 */

namespace App\Notifications;

// O e-mail padrão de confirmação, que já vem pronto no Laravel.
use Illuminate\Auth\Notifications\VerifyEmail;
// Ferramenta que sabe guardar uma notificação na fila.
use Illuminate\Bus\Queueable;
// "Contrato" que avisa ao Laravel: esta notificação vai pela fila.
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Notificação "confirme o seu e-mail", enviada pela fila.
 *
 * extends VerifyEmail: herda o e-mail pronto (texto, botão e link seguro).
 * implements ShouldQueue: assina o contrato "vai pela fila, não sai na hora".
 */
class ConfirmarEmail extends VerifyEmail implements ShouldQueue
{
    // Ferramenta do Laravel que guarda a notificação na fila.
    use Queueable;
}
