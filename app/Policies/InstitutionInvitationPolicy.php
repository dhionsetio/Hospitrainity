<?php

namespace App\Policies;

use App\Enums\InstitutionStatus;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\User;
use App\Services\CourseAccessService;
use App\Services\InstitutionAccessService;
use Illuminate\Auth\Access\Response;

class InstitutionInvitationPolicy
{
    public function __construct(
        private readonly InstitutionAccessService $access,
        private readonly CourseAccessService $courses,
    ) {}

    public function create(User $actor, Institution $institution): bool
    {
        if ($institution->status !== InstitutionStatus::Active) {
            return false;
        }

        return $this->access->canManageLearners($actor, $institution);
    }

    public function revoke(User $actor, InstitutionInvitation $invitation): Response
    {
        $offering = $invitation->course_offering_id === null
            ? null
            : CourseOffering::query()->find($invitation->course_offering_id);
        $authorized = $offering === null
            ? $this->create($actor, $invitation->institution)
            : $offering->institution_id === $invitation->institution_id
                && $this->courses->canManageOffering($actor, $offering);

        if ($invitation->revoked_at === null
            && $invitation->accepted_at === null
            && $authorized) {
            return Response::allow();
        }

        // A scoped staff member must not be able to distinguish another
        // institution's invitation UUID from an identifier that does not exist.
        return Response::denyAsNotFound();
    }
}
