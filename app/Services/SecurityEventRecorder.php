<?php

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\AuditPayloadSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityEventRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $event,
        string $outcome,
        ?User $actor = null,
        mixed $accountIdentifier = null,
        ?Request $request = null,
        array $metadata = [],
        string $severity = 'info',
    ): SecurityEvent {
        $safeMetadata = AuditPayloadSanitizer::metadata($metadata);
        $key = (string) config('app.key');
        $model = SecurityEvent::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'account_fingerprint' => $accountIdentifier === null ? null : hash_hmac('sha256', User::canonicalEmail($accountIdentifier), $key),
            'ip_fingerprint' => $request?->ip() === null ? null : hash_hmac('sha256', $request->ip(), $key),
            'event' => mb_substr($event, 0, 100),
            'outcome' => mb_substr($outcome, 0, 32),
            'severity' => in_array($severity, ['debug', 'info', 'notice', 'warning', 'error', 'critical'], true) ? $severity : 'info',
            'metadata' => $safeMetadata,
            'occurred_at' => now(),
        ]);

        Log::channel('security')->log($model->severity, 'security_event', [
            'event_id' => $model->getKey(),
            'event' => $model->event,
            'outcome' => $model->outcome,
            'actor_user_id' => $model->actor_user_id,
            'account_fingerprint' => $model->account_fingerprint,
            'ip_fingerprint' => $model->ip_fingerprint,
            'metadata' => $safeMetadata,
        ]);

        return $model;
    }
}
