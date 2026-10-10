<?php

/*
 * ALERTA de troca nos DADOS PARA PAGAMENTO (#41; pedido do Gleyson em
 * 10/10/2026: "deve ser uma tela bem segura para que pessoas mal-intencionadas
 * não mudem"). A cada troca, TODOS os responsáveis (quem tem a permissão
 * "pagamentos.configurar") recebem este e-mail: se a troca não foi
 * combinada, alguém percebe na hora.
 *
 * O e-mail diz QUEM trocou, ONDE (IBTO ou qual curso) e QUAIS campos; os
 * valores novos ficam só no histórico da plataforma (e-mail pode ser
 * encaminhado para fora).
 */

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PagamentoAlterado extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $quem  Nome de quem trocou. Ex.: 'Ana Souza'
     * @param  string  $onde  Ex.: 'dados do IBTO' ou 'curso "Tiro Defensivo"'
     * @param  list<string>  $campos  Ex.: ['Chave PIX', 'Conta']
     */
    public function __construct(public string $quem, public string $onde, public array $campos)
    {
        // Só sai depois que a troca foi gravada de vez.
        $this->afterCommit();
    }

    /**
     * Por onde enviar: só e-mail.
     *
     * @return list<string>
     */
    public function via(User $usuario): array
    {
        return ['mail'];
    }

    /**
     * Monta o e-mail.
     *
     * @param  User  $usuario  O responsável que recebe o alerta.
     * @return MailMessage O e-mail com o botão para conferir.
     */
    public function toMail(User $usuario): MailMessage
    {
        return (new MailMessage)
            ->subject('ALERTA: dados para pagamento alterados na plataforma do IBTO')
            ->greeting("Olá, {$usuario->name}!")
            ->line("{$this->quem} alterou os {$this->onde}.")
            ->line('Campos alterados: '.implode(', ', $this->campos).'.')
            ->action('Conferir os dados para pagamento', route('comando-geral.pagamento.edit'))
            ->line('Se esta troca NÃO foi combinada, avise a Tecnologia imediatamente: o histórico mostra o antes e o depois.');
    }
}
