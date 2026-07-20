<?php

namespace App\Policies;

use App\Enums\InstitutionStatus;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\User;
use App\Services\InstitutionAccessService;
use Illuminate\Auth\Access\Response;

class InstitutionInvitationPolicy
{
    public function __construct(private readonly InstitutionAccessService $access) {}

    public function create(User $actor, Institution $institution): bool
    {
        if ($institution->status !== InstitutionStatus::Active) {
            return false;
        }

        return $this->access->canManageLearners($actor, $institution);
    }

    public function revoke(User $actor, InstitutionInvitation $invitation): Response
    {
        if ($invitation->revoked_at === null
            && $invitation->accepted_at === null
            && $this->create($actor, $invitation->institution)) {
            return Response::allow();
        }

        // A scoped staff member must not be able to distinguish another
        // institution's invitation UUID from an identifier that does not exist.
        return Response::denyAsNotFound();
    }
}
