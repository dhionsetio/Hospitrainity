<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JoinRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{type: string, title: string, body: string, url: ?string} */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'join_request',
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url ?? route('join-codes.index'),
        ];
    }
}
