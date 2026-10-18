<?php

/*
 * AVISO DE MENSALIDADE (#80): e-mail 3 dias antes do vencimento das aulas
 * regulares, com o caminho para o Pix em "Minhas aulas". Texto sóbrio, sem
 * cobrança agressiva (valores do IBTO: sério, verdadeiro e decente).
 */

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AvisoMensalidade extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $programa  O nome do programa. Ex.: 'Krav Maga online'.
     * @param  string  $vence  A data do vencimento. Ex.: '10/11/2026'.
     * @param  string|null  $link  A página de pagamento /pagar/<código> (17/10/2026); null = "Minhas aulas".
     */
    public function __construct(public readonly string $programa, public readonly string $vence, public readonly ?string $link = null) {}

    /**
     * Por onde o aviso sai: só e-mail.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * O e-mail.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('IBTO — mensalidade de '.$this->programa.' vence em '.$this->vence)
            ->greeting('Olá!')
            ->line('A mensalidade das aulas de **'.$this->programa.'** vence em '.$this->vence.'.')
            ->line('O Pix copia e cola já está disponível. Depois do vencimento, o acesso às aulas continua por mais 5 dias.')
            // O botão leva direto à página de pagamento desta mensalidade (ou a "Minhas aulas").
            ->action('Pagar a mensalidade', $this->link ?? url('/minhas-aulas'))
            ->line('Se você já pagou, desconsidere esta mensagem.');
    }
}
