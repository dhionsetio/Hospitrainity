<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function store(Request $request, PushNotificationService $push): JsonResponse
    {
        abort_unless($push->configured(), 503, 'Push notifications are not configured for this deployment.');
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048', 'url:http,https'],
            'keys.p256dh' => ['required', 'string', 'min:40', 'max:255', 'regex:/\A[A-Za-z0-9_\-]+\z/'],
            'keys.auth' => ['required', 'string', 'min:16', 'max:255', 'regex:/\A[A-Za-z0-9_\-]+\z/'],
            'contentEncoding' => ['nullable', Rule::in(['aes128gcm', 'aesgcm'])],
        ]);
        abort_unless(str_starts_with($validated['endpoint'], 'https://') || app()->environment('testing'), 422);
        $hash = hash('sha256', $validated['endpoint']);

        $subscription = DB::transaction(function () use ($request, $validated, $hash): PushSubscription {
            $existing = PushSubscription::query()->where('endpoint_hash', $hash)->lockForUpdate()->first();
            abort_if($existing !== null && (int) $existing->user_id !== (int) $request->user()->getKey(), 409);

            $subscription = $existing ?? new PushSubscription;
            $subscription->forceFill([
                'user_id' => $request->user()->getKey(),
                'endpoint_hash' => $hash,
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
                'revoked_at' => null,
            ])->save();

            return $subscription;
        }, attempts: 3);

        return response()->json(['id' => $subscription->getKey(), 'status' => 'subscribed'], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, PushSubscription $pushSubscription): JsonResponse
    {
        abort_unless((int) $pushSubscription->user_id === (int) $request->user()->getKey(), 404);
        $pushSubscription->forceFill(['revoked_at' => now()])->save();

        return response()->json(['status' => 'revoked']);
    }

    public function test(Request $request, PushNotificationService $push): JsonResponse
    {
        abort_unless($push->configured(), 503, 'Push notifications are not configured for this deployment.');
        SendPushNotification::dispatch(
            $request->user()->getKey(),
            'Hospitrainity notification test',
            'Push notifications are connected for this browser.',
            '/privacy/requests',
        );

        return response()->json(['status' => 'queued'], 202);
    }
}
