<?php

/*
 * E-MAIL DO RESULTADO DO PEDIDO DE ACESSO (#84): avisa a pessoa que o
 * cadastro foi APROVADO (pode entrar e ver os cursos) ou NÃO APROVADO (com
 * o motivo escrito por quem analisou). Sai pela fila (Cron), como os outros
 * e-mails da plataforma: se o servidor de e-mail falhar, nada quebra na tela.
 */

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CadastroAnalisado extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $aprovado  true = aprovado; false = não aprovado.
     * @param  string|null  $motivo  O motivo da recusa (só quando não aprovado). Ex.: 'Equipe não confirmou a indicação.'
     */
    public function __construct(public bool $aprovado, public ?string $motivo = null)
    {
        // Só sai depois que a análise foi gravada de vez (fim da transação).
        $this->afterCommit();
    }

    /**
     * Por onde avisar: só e-mail.
     *
     * @return list<string>
     */
    public function via(User $usuario): array
    {
        return ['mail'];
    }

    /**
     * O e-mail, em português.
     *
     * @param  User  $usuario  Quem pediu acesso.
     */
    public function toMail(User $usuario): MailMessage
    {
        // Aprovado: boas-vindas e o botão para entrar.
        if ($this->aprovado) {
            return (new MailMessage)
                ->subject('Seu acesso à plataforma do IBTO foi aprovado')
                ->greeting("Olá, {$usuario->name}!")
                ->line('O seu pedido de acesso à plataforma de cursos do IBTO foi aprovado.')
                ->action('Entrar na plataforma', route('login'))
                ->line('Bons treinos!');
        }

        // Não aprovado: o motivo e o caminho para falar com o IBTO.
        return (new MailMessage)
            ->subject('Seu pedido de acesso à plataforma do IBTO')
            ->greeting("Olá, {$usuario->name}.")
            ->line('O seu pedido de acesso à plataforma de cursos do IBTO não foi aprovado.')
            ->line('Motivo: '.($this->motivo ?? 'não informado.'))
            ->line('Se achar que houve engano, fale com a sua equipe ou com o IBTO.');
    }
}
