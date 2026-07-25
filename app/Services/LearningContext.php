<?php

namespace App\Services;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\CourseEnrollment;
use App\Models\InstitutionMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

final class LearningContext
{
    public const SESSION_MEMBERSHIP_KEY = 'learning.active_institution_membership_id';

    public const SESSION_ENROLLMENT_KEY = 'learning.active_course_enrollment_id';

    private const REQUEST_ATTRIBUTE = 'hospitrainity.learning_context';

    /**
     * @return array{
     *     scope_key: string,
     *     membership_id: int|null,
     *     course_enrollment_id: int|null,
     *     course_offering_id: string|null,
     *     class_invalidated: bool
     * }
     */
    public function current(Request $request, User $user): array
    {
        $resolved = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if (is_array($resolved)) {
            return $resolved;
        }

        $personal = $this->personalContext();
        if (! $request->hasSession()) {
            return $personal;
        }

        $membershipId = $request->session()->get(self::SESSION_MEMBERSHIP_KEY);
        if (! is_int($membershipId) && ! (is_string($membershipId) && ctype_digit($membershipId))) {
            $hadClassSelection = $request->session()->has(self::SESSION_ENROLLMENT_KEY);
            $request->session()->forget(self::SESSION_ENROLLMENT_KEY);

            return $this->remember($request, $hadClassSelection ? $this->invalidClassContext() : $personal);
        }

        $membership = $this->availableMemberships($user)->firstWhere('id', (int) $membershipId);
        if ($membership === null) {
            $hadClassSelection = $request->session()->has(self::SESSION_ENROLLMENT_KEY);
            $request->session()->forget([self::SESSION_MEMBERSHIP_KEY, self::SESSION_ENROLLMENT_KEY]);

            return $this->remember($request, $hadClassSelection ? $this->invalidClassContext() : $personal);
        }

        $institution = [
            'scope_key' => 'membership:'.$membership->getKey(),
            'membership_id' => (int) $membership->getKey(),
            'course_enrollment_id' => null,
            'course_offering_id' => null,
            'class_invalidated' => false,
        ];

        $enrollmentId = $request->session()->get(self::SESSION_ENROLLMENT_KEY);
        if ($enrollmentId === null) {
            return $this->remember($request, $institution);
        }
        if (! is_int($enrollmentId) && ! (is_string($enrollmentId) && ctype_digit($enrollmentId))) {
            $request->session()->forget(self::SESSION_ENROLLMENT_KEY);

            return $this->remember($request, $this->invalidClassContext());
        }

        $enrollment = $this->availableClassEnrollments($user)
            ->first(static fn (CourseEnrollment $candidate): bool => (int) $candidate->getKey() === (int) $enrollmentId
                && (int) $candidate->institution_membership_id === (int) $membership->getKey()
            );
        if ($enrollment === null) {
            $request->session()->forget(self::SESSION_ENROLLMENT_KEY);

            return $this->remember($request, $this->invalidClassContext());
        }

        return $this->remember($request, [
            'scope_key' => 'class:'.$enrollment->course_offering_id,
            'membership_id' => (int) $enrollment->institution_membership_id,
            'course_enrollment_id' => (int) $enrollment->getKey(),
            'course_offering_id' => (string) $enrollment->course_offering_id,
            'class_invalidated' => false,
        ]);
    }

    /** @return Collection<int, InstitutionMembership> */
    public function availableMemberships(User $user): Collection
    {
        return InstitutionMembership::query()
            ->with('institution')
            ->where('user_id', $user->getKey())
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->whereHas('roleAssignments', function ($query): void {
                $query->where('role', InstitutionRole::Learner->value)->whereNull('revoked_at');
            })
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, CourseEnrollment> */
    public function availableClassEnrollments(User $user): Collection
    {
        return CourseEnrollment::query()
            ->with(['offering.revision.curriculumPackage', 'membership.institution'])
            ->where('status', CourseEnrollmentStatus::Active->value)
            ->whereHas('offering', static function ($query): void {
                $query->where('status', CourseOfferingStatus::Active->value);
            })
            ->whereHas('membership', static function ($query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->where('status', InstitutionMembershipStatus::Active->value)
                    ->whereHas('roleAssignments', static function ($roles): void {
                        $roles->where('role', InstitutionRole::Learner->value)->whereNull('revoked_at');
                    });
            })
            ->orderBy('course_offering_id')
            ->orderBy('id')
            ->get();
    }

    public function selectPersonal(Request $request): void
    {
        $request->session()->forget([self::SESSION_MEMBERSHIP_KEY, self::SESSION_ENROLLMENT_KEY]);
        $request->attributes->remove(self::REQUEST_ATTRIBUTE);
    }

    public function selectInstitution(Request $request, User $user, int $membershipId): void
    {
        if ($this->availableMemberships($user)->doesntContain(
            static fn (InstitutionMembership $membership): bool => (int) $membership->getKey() === $membershipId,
        )) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        $request->session()->put(self::SESSION_MEMBERSHIP_KEY, $membershipId);
        $request->session()->forget(self::SESSION_ENROLLMENT_KEY);
        $request->attributes->remove(self::REQUEST_ATTRIBUTE);
    }

    public function selectClass(Request $request, User $user, int $enrollmentId): void
    {
        $enrollment = $this->availableClassEnrollments($user)->first(
            static fn (CourseEnrollment $candidate): bool => (int) $candidate->getKey() === $enrollmentId,
        );
        if ($enrollment === null) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        $request->session()->put([
            self::SESSION_MEMBERSHIP_KEY => (int) $enrollment->institution_membership_id,
            self::SESSION_ENROLLMENT_KEY => (int) $enrollment->getKey(),
        ]);
        $request->attributes->remove(self::REQUEST_ATTRIBUTE);
    }

    /** @return array{scope_key: string, membership_id: null, course_enrollment_id: null, course_offering_id: null, class_invalidated: bool} */
    private function personalContext(): array
    {
        return [
            'scope_key' => 'personal',
            'membership_id' => null,
            'course_enrollment_id' => null,
            'course_offering_id' => null,
            'class_invalidated' => false,
        ];
    }

    /** @return array{scope_key: string, membership_id: null, course_enrollment_id: null, course_offering_id: null, class_invalidated: bool} */
    private function invalidClassContext(): array
    {
        return [
            'scope_key' => 'invalid-class',
            'membership_id' => null,
            'course_enrollment_id' => null,
            'course_offering_id' => null,
            'class_invalidated' => true,
        ];
    }

    /** @param array<string, int|string|bool|null> $context @return array<string, int|string|bool|null> */
    private function remember(Request $request, array $context): array
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $context);

        return $context;
    }
}
