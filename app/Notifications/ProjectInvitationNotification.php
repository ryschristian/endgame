<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvitationNotification extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $token, public readonly string $projectName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Invitation to '.$this->projectName)
            ->line('You have been invited to join '.$this->projectName.'. Sign in with your invited account to accept.')
            ->action('Review invitation', route('project-invitations.show', ['token' => $this->token]))
            ->line('This invitation expires in 48 hours and can be used once.');
    }
}
