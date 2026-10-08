<?php

/*
 * E-mail de CONVITE do filiado (#37, bloco F3): leva o link para a pessoa
 * criar a própria senha. Decisão do Gleyson (07/10/2026): todo filiado ganha
 * conta; ninguém (nem o administrador) define ou vê a senha de outra pessoa.
 *
 * O link é ASSINADO (um "lacre" digital: mudar qualquer letra do endereço o
 * invalida) e vale por 7 dias. Vai pela fila, como o e-mail de confirmação.
 */

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ConviteFiliado extends Notification implements ShouldQueue
{
    use Queueable;

    // Por quantos dias o link do convite vale.
    public const DIAS_DE_VALIDADE = 7;

    public function __construct()
    {
        // Só entra na fila DEPOIS que o cadastro foi gravado de vez no banco
        // (se o cadastro falhar e for desfeito, o e-mail não sai).
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
     * @param  User  $usuario  Quem recebe o convite.
     * @return MailMessage O e-mail com o botão "Criar minha senha".
     */
    public function toMail(User $usuario): MailMessage
    {
        // Link assinado e com prazo: /convite/{usuario}?expires=...&signature=...
        $link = URL::temporarySignedRoute(
            'convite.mostrar',
            now()->addDays(self::DIAS_DE_VALIDADE),
            ['usuario' => $usuario->id],
        );

        return (new MailMessage)
            ->subject('Seu acesso à plataforma de cursos do IBTO')
            ->greeting("Olá, {$usuario->name}!")
            ->line('Você foi cadastrado como filiado do Instituto Brasileiro de Treinamento Operacional (IBTO).')
            ->line('Clique no botão abaixo para criar a sua senha e entrar na plataforma.')
            ->action('Criar minha senha', $link)
            ->line('O link vale por '.self::DIAS_DE_VALIDADE.' dias e só pode ser usado uma vez.')
            ->line('Se você não esperava este e-mail, pode ignorá-lo.');
    }
}
