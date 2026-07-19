<?php

namespace App\Notifications;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRoleChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly UserRole $oldRole,
        public readonly UserRole $newRole,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('admin.role_changed_notification_subject'))
            ->greeting(__('admin.role_changed_notification_greeting', ['name' => $notifiable->name]))
            ->line(__('admin.role_changed_notification_line', [
                'old' => __('admin.roles.'.$this->oldRole->value),
                'new' => __('admin.roles.'.$this->newRole->value),
            ]))
            ->line(__('admin.role_changed_notification_security'));
    }
}
