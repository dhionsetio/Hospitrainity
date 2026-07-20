<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\DataSubjectRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\DataSubjectRequest;
use App\Services\DataSubjectRequestService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrivacyRequestAdministrationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(DataSubjectRequestStatus::class)],
        ]);
        $query = DataSubjectRequest::query()->with('user')->orderBy('due_at');
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return view('superadmin.privacy-requests.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'selectedStatus' => $validated['status'] ?? null,
            'statuses' => DataSubjectRequestStatus::cases(),
        ]);
    }

    public function show(DataSubjectRequest $privacyRequest): View
    {
        return view('superadmin.privacy-requests.show', [
            'privacyRequest' => $privacyRequest->load(['user', 'assignee', 'events.actor', 'export', 'erasureSteps']),
            'actions' => $this->actionsFor($privacyRequest->status),
        ]);
    }

    public function update(Request $request, DataSubjectRequest $privacyRequest, DataSubjectRequestService $service): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(DataSubjectRequestStatus::class)],
            'reason_code' => ['nullable', 'string', 'max:80', 'regex:/\A[a-z0-9_\-]+\z/'],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $status = DataSubjectRequestStatus::from($validated['status']);
        abort_unless(in_array($status, $this->actionsFor($privacyRequest->status), true), 422);

        try {
            $service->transition(
                $privacyRequest,
                $status,
                $request->user(),
                $validated['reason_code'] ?? null,
                $validated['decision_note'] ?? null,
            );
        } catch (DomainException) {
            return back()->withErrors(['status' => __('The request changed before this action. Reload and review its current state.')]);
        }

        return redirect()->route('superadmin.privacy-requests.show', $privacyRequest)
            ->with('status', __('Privacy request status updated.'));
    }

    /** @return list<DataSubjectRequestStatus> */
    private function actionsFor(DataSubjectRequestStatus $status): array
    {
        return match ($status) {
            DataSubjectRequestStatus::Submitted, DataSubjectRequestStatus::IdentityPending => [DataSubjectRequestStatus::InReview],
            DataSubjectRequestStatus::InReview => [DataSubjectRequestStatus::Approved, DataSubjectRequestStatus::Denied, DataSubjectRequestStatus::Held],
            DataSubjectRequestStatus::Held => [DataSubjectRequestStatus::InReview, DataSubjectRequestStatus::Approved, DataSubjectRequestStatus::Denied],
            DataSubjectRequestStatus::Failed => [DataSubjectRequestStatus::Executing, DataSubjectRequestStatus::Held],
            DataSubjectRequestStatus::Denied => [DataSubjectRequestStatus::Appealed],
            DataSubjectRequestStatus::Appealed => [DataSubjectRequestStatus::InReview, DataSubjectRequestStatus::Held],
            DataSubjectRequestStatus::Approved => [DataSubjectRequestStatus::Completed, DataSubjectRequestStatus::Held],
            default => [],
        };
    }
}
