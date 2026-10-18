<?php

/*
 * E-mail de COMPROVANTE RECUSADO (18/10/2026): o Comando Geral conferiu e não
 * aceitou o comprovante (ex.: ilegível, valor diferente). A vaga continua
 * reservada; o aluno vê o motivo e envia outro comprovante pela página do curso.
 * Sai pela fila, só depois que a recusa foi gravada.
 */

namespace App\Notifications;

use App\Models\CursoMatricula;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComprovanteRecusado extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  CursoMatricula  $matricula  A matrícula (com o curso e o motivo da recusa).
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
     * Monta o e-mail com o motivo e o botão para enviar outro comprovante.
     *
     * @param  User  $usuario  O aluno.
     */
    public function toMail(User $usuario): MailMessage
    {
        $curso = $this->matricula->curso;

        return (new MailMessage)
            ->subject("Comprovante não aceito: {$curso->titulo}")
            ->greeting("Olá, {$usuario->name}!")
            ->line("Conferimos o comprovante da sua inscrição no curso \"{$curso->titulo}\" e não conseguimos aceitá-lo.")
            ->line('Motivo: '.$this->matricula->comprovante_recusa_motivo)
            ->line('A sua vaga continua reservada. Envie outro comprovante pela página do curso.')
            ->action('Enviar outro comprovante', route('catalogo.curso', $curso->id))
            ->line('Equipe do Instituto Brasileiro de Treinamento Operacional (IBTO).');
    }
}
