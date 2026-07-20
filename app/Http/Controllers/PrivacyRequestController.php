<?php

namespace App\Http\Controllers;

use App\Enums\DataSubjectRequestType;
use App\Models\DataExport;
use App\Models\DataSubjectRequest;
use App\Services\DataSubjectRequestService;
use App\Services\PushNotificationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('privacy-requests.index', [
            'requests' => DataSubjectRequest::query()
                ->where('user_id', $request->user()->getKey())
                ->with('export')
                ->latest()
                ->paginate(15),
            'generalTypes' => [
                DataSubjectRequestType::Correction,
                DataSubjectRequestType::Restriction,
                DataSubjectRequestType::Objection,
                DataSubjectRequestType::ConsentWithdrawal,
                DataSubjectRequestType::Appeal,
            ],
            'pushConfigured' => app(PushNotificationService::class)->configured(),
            'pushPublicKey' => (string) config('push.vapid.public_key'),
            'pushSubscription' => $request->user()->pushSubscriptions()->whereNull('revoked_at')->latest()->first(),
        ]);
    }

    public function sensitive(Request $request, string $type): View
    {
        $requestType = DataSubjectRequestType::tryFrom(str_replace('-', '_', $type));
        abort_unless(in_array($requestType, [DataSubjectRequestType::AccessExport, DataSubjectRequestType::Deletion], true), 404);

        return view('privacy-requests.sensitive', ['requestType' => $requestType]);
    }

    public function store(Request $request, DataSubjectRequestService $service): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(DataSubjectRequestType::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'confirm_effects' => ['nullable', 'accepted'],
        ]);
        $type = DataSubjectRequestType::from($validated['type']);
        $sensitive = in_array($type, [DataSubjectRequestType::AccessExport, DataSubjectRequestType::Deletion], true);
        if ($sensitive && ! $request->boolean('confirm_effects')) {
            return back()->withErrors(['confirm_effects' => __('Confirm that you understand the request effects.')]);
        }

        try {
            $service->submit(
                $request->user(),
                $type,
                $validated['note'] ?? null,
                $this->recentPassword($request),
            );
        } catch (DomainException $exception) {
            if ($exception->getMessage() === 'recent_password_required') {
                return redirect()->route('privacy-requests.sensitive', ['type' => str_replace('_', '-', $type->value)]);
            }

            return back()->withErrors(['type' => __('An active request of this type already exists.')]);
        }

        return redirect()->route('privacy-requests.index')
            ->with('status', __('Your request was recorded. You can track every status change here.'));
    }

    public function cancel(Request $httpRequest, DataSubjectRequest $privacyRequest, DataSubjectRequestService $service): RedirectResponse
    {
        try {
            $service->cancel($privacyRequest, $httpRequest->user());
        } catch (DomainException) {
            abort(403);
        }

        return back()->with('status', __('The request was cancelled.'));
    }

    public function download(Request $request, DataExport $export, DataSubjectRequestService $requests): StreamedResponse
    {
        abort_unless((int) $export->user_id === (int) $request->user()->getKey(), 404);
        abort_unless($export->status === 'available' && $export->expires_at?->isFuture(), 410);
        abort_unless(is_string($export->encrypted_path) && Storage::disk('local')->exists($export->encrypted_path), 410);

        $encrypted = Storage::disk('local')->get($export->encrypted_path);
        $bytes = Crypt::decryptString($encrypted);
        abort_unless(hash_equals((string) $export->payload_sha256, hash('sha256', $bytes)), 500);

        $export->forceFill(['downloaded_at' => now()])->save();
        $privacyRequest = $export->request;
        $requests->event($privacyRequest, $request->user(), 'export.downloaded', ['export_id' => $export->getKey()]);

        return response()->streamDownload(
            static fn () => print $bytes,
            'hospitrainity-personal-data-'.$export->getKey().'.zip',
            ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store, private'],
        );
    }

    private function recentPassword(Request $request): bool
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return $confirmedAt > 0 && (time() - $confirmedAt) < (int) config('auth.password_timeout', 10800);
    }
}
