<?php

namespace App\Services;

use App\Models\DataExport;
use App\Models\DataSubjectRequest;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Storage;

class PrivacyRetentionService
{
    /** @return array{expired_exports: int, minimized_requests: int, removed_subscriptions: int, execute: bool} */
    public function run(bool $execute): array
    {
        $expiredExports = DataExport::query()
            ->whereIn('status', ['available', 'failed'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();
        $cutoff = now()->subDays((int) config('privacy.request_evidence_retention_days'));
        $requests = DataSubjectRequest::query()
            ->whereIn('status', ['completed', 'denied', 'cancelled'])
            ->where('updated_at', '<=', $cutoff)
            ->where(fn ($query) => $query->whereNotNull('request_note')->orWhereNotNull('decision_note'))
            ->get();
        $subscriptions = PushSubscription::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<=', now()->subDays(30))
            ->get();

        if ($execute) {
            foreach ($expiredExports as $export) {
                if (is_string($export->encrypted_path) && $export->encrypted_path !== '') {
                    $disk = Storage::disk('local');
                    if ($disk->exists($export->encrypted_path) && ! $disk->delete($export->encrypted_path)) {
                        continue;
                    }
                }
                $export->forceFill(['status' => 'expired', 'encrypted_path' => null])->save();
            }
            foreach ($requests as $request) {
                $request->forceFill(['request_note' => null, 'decision_note' => null])->save();
                app(DataSubjectRequestService::class)->event($request, null, 'request.evidence_minimized', [
                    'retention_days' => (int) config('privacy.request_evidence_retention_days'),
                ]);
            }
            PushSubscription::query()->whereKey($subscriptions->modelKeys())->delete();
        }

        return [
            'expired_exports' => $expiredExports->count(),
            'minimized_requests' => $requests->count(),
            'removed_subscriptions' => $subscriptions->count(),
            'execute' => $execute,
        ];
    }
}
