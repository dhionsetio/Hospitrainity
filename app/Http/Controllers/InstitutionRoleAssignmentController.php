<?php

namespace App\Http\Controllers;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\InstitutionMembership;
use App\Services\InstitutionAccessService;
use App\Services\InstitutionContext;
use App\Services\InstitutionRoleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionRoleAssignmentController extends Controller
{
    public function index(
        Request $request,
        InstitutionContext $context,
        InstitutionAccessService $access,
    ): View {
        $actor = $request->user();
        $institution = $context->current($request, $actor);
        abort_unless($access->canManageStaff($actor, $institution), 403);

        return view('institution-roles.index', [
            'institution' => $institution,
            'institutions' => $context->availableFor($actor),
            'members' => $institution->memberships()
                ->where('status', InstitutionMembershipStatus::Active->value)
                ->with([
                    'user:id,name,email',
                    'roleAssignments' => fn ($query) => $query->whereNull('revoked_at'),
                ])
                ->orderBy('user_id')
                ->get(),
            'isSystemAdmin' => $access->isSystemAdmin($actor),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function update(
        Request $request,
        InstitutionMembership $membership,
        InstitutionContext $context,
        InstitutionRoleManager $roles,
    ): RedirectResponse {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:instructor,institution_admin'],
            'action' => ['required', 'string', 'in:grant,revoke'],
        ]);
        $institution = $context->current($request, $request->user());
        abort_unless($membership->institution_id === $institution->getKey(), 404);
        $roles->set(
            $request->user(),
            $membership,
            InstitutionRole::from($validated['role']),
            $validated['action'] === 'grant',
        );

        return back()->with('status', $validated['action'] === 'grant'
            ? __('The institution role has been granted.')
            : __('The institution role has been revoked.'));
    }

    private function routePrefix(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'superadmin.')
            ? 'superadmin'
            : 'supervisor';
    }
}
