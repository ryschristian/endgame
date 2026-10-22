<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInvitationNotification extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are invited to '.config('app.name'))
            ->line('You have been invited to create an account.')
            ->action('Accept invitation', route('invitations.show', ['token' => $this->token]))
            ->line('This invitation expires in 48 hours and can be used once.')
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
