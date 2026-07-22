<?php

namespace App\Http\Controllers;

use App\Models\InstitutionInvitation;
use App\Notifications\InstitutionInvitationNotification;
use App\Services\InstitutionContext;
use App\Services\InstitutionInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

class InstitutionInvitationController extends Controller
{
    public function __construct(
        private readonly InstitutionContext $context,
        private readonly InstitutionInvitationService $invitations,
    ) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $institution = $this->context->current($request, $actor);
        Gate::authorize('create', [InstitutionInvitation::class, $institution]);

        $rows = InstitutionInvitation::query()
            ->with(['issuer:id,name', 'acceptedBy:id,name'])
            ->where('institution_id', $institution->getKey())
            ->whereNull('course_offering_id')
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $rows->getCollection()->each(function (InstitutionInvitation $invitation): void {
            $invitation->masked_target = $this->invitations->maskedTarget($invitation);
        });

        return view('invitations.index', [
            'institution' => $institution,
            'institutions' => $this->context->availableFor($actor),
            'invitations' => $rows,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $institution = $this->context->current($request, $actor);
        Gate::authorize('create', [InstitutionInvitation::class, $institution]);
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        $issued = $this->invitations->issue($actor, $institution, $validated['email']);
        $invitation = $issued['invitation'];
        try {
            Notification::route('mail', $invitation->target_email_ciphertext)
                ->notify(new InstitutionInvitationNotification(
                    $institution,
                    route('invitations.accept', ['token' => $issued['token']]),
                    $invitation->expires_at->toDayDateTimeString(),
                ));
        } catch (Throwable) {
            $this->invitations->revokeAfterDeliveryFailure($invitation);

            return back()->withErrors([
                'email' => __('The invitation could not be sent. Try again later.'),
            ]);
        }

        return back()->with('status', __('If the address is eligible, an invitation has been sent.'));
    }

    public function destroy(Request $request, InstitutionInvitation $invitation): RedirectResponse
    {
        $invitation->loadMissing('institution');
        Gate::authorize('revoke', $invitation);
        $this->invitations->revoke($request->user(), $invitation);

        return back()->with('status', __('The invitation has been revoked.'));
    }

    public function selectInstitution(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'uuid'],
        ]);
        $this->context->selectById($request, $request->user(), $validated['institution_id']);

        return back();
    }

    private function routePrefix(Request $request): string
    {
        $prefix = explode('.', (string) $request->route()?->getName())[0] ?? '';

        return in_array($prefix, ['supervisor', 'admin', 'superadmin'], true) ? $prefix : 'supervisor';
    }
}
