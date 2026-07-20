<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\InstitutionMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

final class LearningContext
{
    public const SESSION_MEMBERSHIP_KEY = 'learning.active_institution_membership_id';

    /** @return array{scope_key: string, membership_id: int|null} */
    public function current(Request $request, User $user): array
    {
        if (! $request->hasSession()) {
            return ['scope_key' => 'personal', 'membership_id' => null];
        }

        $membershipId = $request->session()->get(self::SESSION_MEMBERSHIP_KEY);
        if (! is_int($membershipId) && ! (is_string($membershipId) && ctype_digit($membershipId))) {
            return ['scope_key' => 'personal', 'membership_id' => null];
        }

        $membership = $this->availableMemberships($user)->firstWhere('id', (int) $membershipId);
        if ($membership === null) {
            $request->session()->forget(self::SESSION_MEMBERSHIP_KEY);

            return ['scope_key' => 'personal', 'membership_id' => null];
        }

        return [
            'scope_key' => 'membership:'.$membership->getKey(),
            'membership_id' => (int) $membership->getKey(),
        ];
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

    public function selectPersonal(Request $request): void
    {
        $request->session()->forget(self::SESSION_MEMBERSHIP_KEY);
    }

    public function selectInstitution(Request $request, User $user, int $membershipId): void
    {
        if ($this->availableMemberships($user)->doesntContain(
            static fn (InstitutionMembership $membership): bool => (int) $membership->getKey() === $membershipId,
        )) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        $request->session()->put(self::SESSION_MEMBERSHIP_KEY, $membershipId);
    }
}
