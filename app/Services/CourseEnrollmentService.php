<?php

namespace App\Services;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\CourseEnrollment;
use App\Models\CourseEnrollmentEvent;
use App\Models\CourseOffering;
use App\Models\InstitutionMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CourseEnrollmentService
{
    public function __construct(private readonly CourseAccessService $access) {}

    public function enroll(
        User $actor,
        CourseOffering $offering,
        InstitutionMembership $membership,
        string $reason,
    ): CourseEnrollment {
        return DB::transaction(function () use ($actor, $offering, $membership, $reason): CourseEnrollment {
            [$lockedActor, $lockedOffering, $lockedMembership] = $this->lockAuthorizedContext(
                $actor,
                $offering,
                $membership,
            );

            return $this->enrollLocked($lockedOffering, $lockedMembership, $lockedActor, $reason, false);
        }, attempts: 3);
    }

    /**
     * Complete an already authorized invitation or classroom-code workflow.
     * The caller must hold the Institution, connection record, and membership
     * locks in one surrounding transaction. This method still rechecks every
     * Class, membership, role, and lifecycle invariant before writing.
     */
    public function enrollFromApprovedConnection(
        CourseOffering $offering,
        InstitutionMembership $membership,
        ?User $actor,
        string $reason,
    ): CourseEnrollment {
        $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
        $lockedMembership = InstitutionMembership::query()->lockForUpdate()->findOrFail($membership->getKey());

        return $this->enrollLocked($lockedOffering, $lockedMembership, $actor, $reason, true);
    }

    public function changeStatus(
        User $actor,
        CourseEnrollment $enrollment,
        CourseEnrollmentStatus $target,
        string $reason,
    ): CourseEnrollment {
        return DB::transaction(function () use ($actor, $enrollment, $target, $reason): CourseEnrollment {
            $reason = $this->reason($reason);
            $locked = CourseEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $offering = CourseOffering::query()->lockForUpdate()->findOrFail($locked->course_offering_id);
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            if (! $this->access->canManageOffering($lockedActor, $offering)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            $from = $locked->status;
            $allowed = match ($from) {
                CourseEnrollmentStatus::Active => [CourseEnrollmentStatus::Suspended, CourseEnrollmentStatus::Withdrawn],
                CourseEnrollmentStatus::Suspended => [CourseEnrollmentStatus::Active, CourseEnrollmentStatus::Withdrawn],
                CourseEnrollmentStatus::Withdrawn => [],
            };
            if (! in_array($target, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => __('classes.errors.enrollment_transition'),
                ]);
            }

            $locked->forceFill([
                'status' => $target,
                'suspended_at' => $target === CourseEnrollmentStatus::Suspended
                    ? now()
                    : ($target === CourseEnrollmentStatus::Active ? null : $locked->suspended_at),
                'withdrawn_at' => $target === CourseEnrollmentStatus::Withdrawn ? now() : null,
            ])->save();
            $this->record($locked, $from, $target, $lockedActor, $reason);

            return $locked->fresh();
        }, attempts: 3);
    }

    public function transfer(
        User $actor,
        CourseEnrollment $enrollment,
        CourseOffering $target,
        string $reason,
    ): CourseEnrollment {
        return DB::transaction(function () use ($actor, $enrollment, $target, $reason): CourseEnrollment {
            $reason = $this->reason($reason);
            $lockedEnrollment = CourseEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $offeringIds = [(string) $lockedEnrollment->course_offering_id, (string) $target->getKey()];
            sort($offeringIds, SORT_STRING);
            $offerings = CourseOffering::query()->whereKey($offeringIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $offerings->get($lockedEnrollment->course_offering_id);
            $lockedTarget = $offerings->get($target->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $membership = InstitutionMembership::query()->lockForUpdate()->findOrFail($lockedEnrollment->institution_membership_id);

            if (! $source instanceof CourseOffering
                || ! $lockedTarget instanceof CourseOffering
                || $source->is($lockedTarget)
                || $source->institution_id !== $lockedTarget->institution_id
                || ! $this->access->canManageOffering($lockedActor, $source)
                || ! $this->access->canManageOffering($lockedActor, $lockedTarget)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            if ($lockedEnrollment->status === CourseEnrollmentStatus::Withdrawn) {
                throw ValidationException::withMessages(['transfer' => __('classes.errors.withdrawn_transfer')]);
            }

            $newEnrollment = $this->enrollLocked(
                $lockedTarget,
                $membership,
                $lockedActor,
                __('classes.events.transfer_in', ['class' => $source->title]),
                false,
            );
            $from = $lockedEnrollment->status;
            $lockedEnrollment->forceFill([
                'status' => CourseEnrollmentStatus::Withdrawn,
                'withdrawn_at' => now(),
            ])->save();
            $this->record(
                $lockedEnrollment,
                $from,
                CourseEnrollmentStatus::Withdrawn,
                $lockedActor,
                $reason,
                $lockedTarget,
            );

            return $newEnrollment;
        }, attempts: 3);
    }

    /** @return array{User, CourseOffering, InstitutionMembership} */
    private function lockAuthorizedContext(
        User $actor,
        CourseOffering $offering,
        InstitutionMembership $membership,
    ): array {
        $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
        $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
        $lockedMembership = InstitutionMembership::query()->lockForUpdate()->findOrFail($membership->getKey());
        if (! $this->access->canManageOffering($lockedActor, $lockedOffering)) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        return [$lockedActor, $lockedOffering, $lockedMembership];
    }

    private function enrollLocked(
        CourseOffering $offering,
        InstitutionMembership $membership,
        ?User $actor,
        string $reason,
        bool $idempotent,
    ): CourseEnrollment {
        $reason = $this->reason($reason);
        if (! $offering->acceptsEnrollments()
            || $membership->institution_id !== $offering->institution_id
            || $membership->status !== InstitutionMembershipStatus::Active
            || ! $membership->roleAssignments()
                ->where('role', InstitutionRole::Learner->value)
                ->whereNull('revoked_at')
                ->exists()) {
            throw ValidationException::withMessages(['membership' => __('classes.errors.learner_unavailable')]);
        }

        $existing = CourseEnrollment::query()
            ->where('course_offering_id', $offering->getKey())
            ->where('institution_membership_id', $membership->getKey())
            ->lockForUpdate()
            ->first();
        if ($existing !== null) {
            if ($idempotent && $existing->status === CourseEnrollmentStatus::Active) {
                return $existing;
            }

            throw ValidationException::withMessages(['membership' => __('classes.errors.already_enrolled')]);
        }

        $enrollment = CourseEnrollment::query()->create([
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $membership->getKey(),
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_by_user_id' => $actor?->getKey(),
            'enrolled_at' => now(),
        ]);
        $this->record($enrollment, null, CourseEnrollmentStatus::Active, $actor, $reason);

        return $enrollment;
    }

    private function record(
        CourseEnrollment $enrollment,
        ?CourseEnrollmentStatus $from,
        CourseEnrollmentStatus $to,
        ?User $actor,
        string $reason,
        ?CourseOffering $transferTarget = null,
    ): void {
        CourseEnrollmentEvent::query()->create([
            'course_enrollment_id' => $enrollment->getKey(),
            'course_offering_id' => $enrollment->course_offering_id,
            'institution_id' => $enrollment->institution_id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor?->getKey(),
            'transfer_to_course_offering_id' => $transferTarget?->getKey(),
            'reason' => $reason,
        ]);
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A roster reason must contain 1 to 500 characters.');
        }

        return $reason;
    }
}
