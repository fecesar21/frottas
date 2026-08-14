<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RedefinicaoSenhaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $token, protected string $email) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')
            .'/redefinir-senha?token='.$this->token.'&email='.urlencode($this->email);

        return (new MailMessage)
            ->subject('Solicitação de Redefinição de Senha — HealthDrive')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir sua senha.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em 60 minutos.')
            ->line('Se você não solicitou isso, ignore este e-mail — nenhuma alteração será feita.');
    }
}
