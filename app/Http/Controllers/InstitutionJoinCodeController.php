<?php

namespace App\Http\Controllers;

use App\Enums\InstitutionJoinRequestStatus;
use App\Http\Requests\StoreInstitutionJoinCodeRequest;
use App\Models\InstitutionJoinCode;
use App\Services\InstitutionAccessService;
use App\Services\InstitutionContext;
use App\Services\InstitutionJoinCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionJoinCodeController extends Controller
{
    public function index(Request $request, InstitutionContext $context, InstitutionAccessService $access): View
    {
        $actor = $request->user();
        $institution = $context->current($request, $actor);
        $access->authorizeLearnerManagement($actor, $institution);

        return view('institution-join-codes.index', [
            'institution' => $institution,
            'institutions' => $context->availableFor($actor),
            'codes' => $institution->joinCodes()->whereNull('course_offering_id')->with('issuer:id,name')->latest()->paginate(20, ['*'], 'codes'),
            'requests' => $institution->joinRequests()
                ->whereNull('course_offering_id')
                ->with(['user:id,name,email', 'decidedBy:id,name'])
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [InstitutionJoinRequestStatus::Pending->value])
                ->latest('requested_at')
                ->paginate(20, ['*'], 'requests'),
            'canManageStaff' => $access->canManageStaff($actor, $institution),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(StoreInstitutionJoinCodeRequest $request, InstitutionContext $context, InstitutionJoinCodeService $codes): RedirectResponse
    {
        $validated = $request->validated();
        $issued = $codes->issue(
            $request->user(),
            $context->current($request, $request->user()),
            (int) $validated['use_limit'],
            $request->durationSeconds(),
        );

        return back()
            ->with('status', __('The classroom code was created. Copy it now; it will not be shown again.'))
            ->with('issued_join_code', $issued['code']);
    }

    public function destroy(Request $request, InstitutionJoinCode $joinCode, InstitutionContext $context, InstitutionJoinCodeService $codes): RedirectResponse
    {
        $institution = $context->current($request, $request->user());
        abort_unless($joinCode->institution_id === $institution->getKey(), 404);
        $codes->revoke($request->user(), $joinCode);

        return back()->with('status', __('The classroom code has been revoked.'));
    }

    private function routePrefix(Request $request): string
    {
        $prefix = explode('.', (string) $request->route()?->getName())[0] ?? '';

        return in_array($prefix, ['supervisor', 'admin', 'superadmin'], true) ? $prefix : 'supervisor';
    }
}
