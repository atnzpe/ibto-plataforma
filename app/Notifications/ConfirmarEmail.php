<?php

/*
 * E-MAIL DE CONFIRMAÇÃO DE CADASTRO, pela FILA e com a marca do IBTO.
 *
 * - Vai pela fila (tabela `jobs`, enviada pelo Cron): se o servidor de e-mail
 *   falhar, o cadastro não dá erro 500.
 * - Texto do IBTO (17/10/2026): sério, direto e convidativo, no tom da
 *   instituição; diz o que acontece depois (a análise do pedido) e traz a
 *   SEGURANÇA por extenso: o endereço oficial e quanto tempo o link vale, para
 *   a pessoa reconhecer um e-mail falso. O aviso "o IBTO nunca pede senha"
 *   está no rodapé de todos os e-mails (resources/views/vendor/mail).
 */

namespace App\Notifications;

// O e-mail padrão de confirmação do Laravel (o link seguro e assinado vem dele).
use Illuminate\Auth\Notifications\VerifyEmail;
// Ferramenta que sabe guardar uma notificação na fila.
use Illuminate\Bus\Queueable;
// "Contrato" que avisa ao Laravel: esta notificação vai pela fila.
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notificação "confirme o seu e-mail", enviada pela fila.
 *
 * extends VerifyEmail: herda o link assinado (que expira e não pode ser alterado).
 * implements ShouldQueue: vai pela fila, não sai na hora.
 */
class ConfirmarEmail extends VerifyEmail implements ShouldQueue
{
    // Ferramenta do Laravel que guarda a notificação na fila.
    use Queueable;

    /**
     * O e-mail, com o texto do IBTO no lugar do texto padrão do Laravel.
     *
     * @param  string  $url  O link assinado de confirmação. Ex.: https://cursos.ib-to.org/email/verify/12/abc...?signature=...
     * @return MailMessage A mensagem pronta (a moldura com a marca e o aviso de segurança vem do tema).
     */
    protected function buildMailMessage($url): MailMessage
    {
        // O endereço oficial por extenso (sem "https://"), para a pessoa conferir. Ex.: 'cursos.ib-to.org'.
        $enderecoOficial = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        // Quantos minutos o link vale (o mesmo número que o Laravel usa para assinar o link).
        $minutos = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Confirme o seu e-mail — IBTO')
            ->greeting('Bem-vindo ao IBTO.')
            ->line('Recebemos o seu pedido de acesso à plataforma de cursos do Instituto Brasileiro de Treinamento Operacional: formação real, com instrutores que vivem o que ensinam.')
            ->line('Para continuar, confirme que este e-mail é seu:')
            ->action('Confirmar meu e-mail', $url)
            ->line("Este link vale por {$minutos} minutos e leva para **{$enderecoOficial}**. Se o endereço for outro, não clique.")
            ->line('Depois da confirmação, o seu pedido é analisado pelo admin da sua equipe ou pelo Comando Geral. Você recebe a resposta por e-mail.')
            ->line('Não pediu acesso? Ignore esta mensagem: nada acontece sem a confirmação.')
            ->salutation('Comando Geral do IBTO');
    }
}
