<?php

/*
 * E-mail de MATRÍCULA CONFIRMADA (#41: "e-mail ao aluno quando o pagamento for
 * confirmado"). Sai pela fila e só DEPOIS que a confirmação foi gravada de vez
 * (se algo falhar e for desfeito, o e-mail não sai).
 */

namespace App\Notifications;

use App\Models\CursoMatricula;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatriculaConfirmada extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  CursoMatricula  $matricula  A matrícula confirmada (com o curso).
     */
    public function __construct(public CursoMatricula $matricula)
    {
        // Só entra na fila depois que a transação terminou com sucesso.
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
     * @param  User  $usuario  Quem se matriculou.
     * @return MailMessage O e-mail com o botão para a página do curso.
     */
    public function toMail(User $usuario): MailMessage
    {
        $curso = $this->matricula->curso;

        return (new MailMessage)
            ->subject("Matrícula confirmada: {$curso->titulo}")
            ->greeting("Olá, {$usuario->name}!")
            ->line("Sua matrícula no curso \"{$curso->titulo}\" está confirmada.")
            ->action('Ver o curso', route('catalogo.curso', $curso->id))
            ->line('Bons treinos! Equipe do Instituto Brasileiro de Treinamento Operacional (IBTO).');
    }
}
