<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotification implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $relativeUrl,
    ) {}

    public function handle(PushNotificationService $push): void
    {
        $user = User::query()->find($this->userId);
        if ($user !== null && ! $user->isDisabled()) {
            $push->send($user, $this->title, $this->body, $this->relativeUrl);
        }
    }
}
