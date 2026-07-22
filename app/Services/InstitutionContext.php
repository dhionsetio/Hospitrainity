<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionStatus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

final class InstitutionContext
{
    public const SESSION_KEY = 'identity.active_institution_id';

    /** @return Collection<int, Institution> */
    public function availableFor(User $user): Collection
    {
        $query = Institution::query()
            ->where('status', InstitutionStatus::Active->value)
            ->orderBy('name_en');

        if (! $user->isSuperAdmin()) {
            $query->whereHas('memberships', function ($query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->where('status', InstitutionMembershipStatus::Active->value);
            });
        }

        return $query->get();
    }

    public function current(Request $request, User $user): Institution
    {
        $available = $this->availableFor($user);
        $selected = $request->hasSession()
            ? (string) $request->session()->get(self::SESSION_KEY, '')
            : '';
        $current = $available->firstWhere('id', $selected);

        if ($current === null) {
            $defaultId = $user->institutionMemberships()
                ->where('status', InstitutionMembershipStatus::Active->value)
                ->where('is_default', true)
                ->value('institution_id');
            $current = $available->firstWhere('id', $defaultId)
                ?? $available->firstWhere('key', 'hospitrainity-hq')
                ?? $available->first();
        }

        if ($current === null) {
            throw new AuthorizationException(__('No active institution is available for this account.'));
        }

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $current->getKey());
        }

        return $current;
    }

    public function select(Request $request, User $user, Institution $institution): void
    {
        if ($this->availableFor($user)->doesntContain(fn (Institution $allowed): bool => $allowed->is($institution))) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        $request->session()->put(self::SESSION_KEY, $institution->getKey());
    }

    /**
     * Resolve a submitted identifier only inside the actor's authorized set.
     * This avoids an unscoped existence query whose 404/403 distinction could
     * disclose whether another institution UUID is real.
     */
    public function selectById(Request $request, User $user, string $institutionId): void
    {
        $institution = $this->availableFor($user)->firstWhere('id', $institutionId);
        if ($institution === null) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        $request->session()->put(self::SESSION_KEY, $institution->getKey());
    }
}
