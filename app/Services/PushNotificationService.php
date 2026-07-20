<?php

namespace App\Services;

use App\Models\PushSubscription as StoredSubscription;
use App\Models\User;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function configured(): bool
    {
        return (bool) config('push.enabled')
            && is_string(config('push.vapid.public_key'))
            && config('push.vapid.public_key') !== ''
            && is_string(config('push.vapid.private_key'))
            && config('push.vapid.private_key') !== ''
            && preg_match('#\A(?:mailto:|https://)#', (string) config('push.vapid.subject')) === 1;
    }

    /** @return array{queued: int, sent: int, failed: int, expired: int, configured: bool} */
    public function send(User $user, string $title, string $body, string $relativeUrl): array
    {
        if (! $this->configured()) {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0, 'expired' => 0, 'configured' => false];
        }

        $stored = StoredSubscription::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->get();
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('push.vapid.subject'),
                'publicKey' => (string) config('push.vapid.public_key'),
                'privateKey' => (string) config('push.vapid.private_key'),
            ],
        ], [
            'TTL' => (int) config('push.ttl_seconds'),
            'urgency' => (string) config('push.urgency'),
            'batchSize' => 100,
            'requestConcurrency' => 20,
        ]);
        $webPush->setReuseVAPIDHeaders(true);
        $payload = json_encode([
            'title' => Str::limit(strip_tags($title), 100, ''),
            'body' => Str::limit(strip_tags($body), 300, ''),
            'url' => $this->safeRelativeUrl($relativeUrl),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach ($stored as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $payload);
        }

        $sent = $failed = $expired = 0;
        foreach ($webPush->flush() as $report) {
            $record = $stored->first(fn (StoredSubscription $item): bool => hash_equals($item->endpoint_hash, hash('sha256', $report->getEndpoint())));
            if ($report->isSuccess()) {
                $sent++;
                $record?->forceFill(['last_used_at' => now()])->save();
            } else {
                $failed++;
                if ($report->isSubscriptionExpired()) {
                    $expired++;
                    $record?->forceFill(['revoked_at' => now()])->save();
                }
            }
        }

        return ['queued' => $stored->count(), 'sent' => $sent, 'failed' => $failed, 'expired' => $expired, 'configured' => true];
    }

    private function safeRelativeUrl(string $url): string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') && strlen($url) <= 500 ? $url : '/';
    }
}
