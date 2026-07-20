<?php

namespace App\Notifications;

use App\Models\Institution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstitutionInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Institution $institution,
        public readonly string $acceptUrl,
        public readonly string $expiresAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Deliberately synchronous: a raw invitation URL must not be serialized
        // into the jobs table or retained in failed-job payloads.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your Hospitrainity invitation'))
            ->greeting(__('You have been invited to Hospitrainity'))
            ->line(__('A staff member invited this email address to join :institution.', [
                'institution' => $this->institution->displayName(app()->getLocale()),
            ]))
            ->action(__('Accept invitation'), $this->acceptUrl)
            ->line(__('This single-use invitation expires at :time.', ['time' => $this->expiresAt]))
            ->line(__('If you did not expect this invitation, you can ignore this email.'));
    }
}
