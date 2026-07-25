<?php

namespace Tests;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;

abstract class TestCase extends BaseTestCase
{
    /**
     * Existing feature tests enter downstream authorized behavior directly.
     * Model that precondition explicitly; dedicated B03 tests exercise the
     * unauthenticated and incomplete-MFA boundaries without this helper.
     */
    public function actingAs(UserContract $user, $guard = null): static
    {
        if (method_exists($user, 'requiresMfa')
            && $user->requiresMfa()
            && method_exists($user, 'hasConfirmedTotp')
            && ! $user->hasConfirmedTotp()) {
            // The guard retains this model instance for the request. Keep the
            // synthetic assurance in memory so test setup does not add hidden
            // database writes to query-count assertions.
            $user->forceFill([
                'two_factor_secret' => Crypt::encryptString('explicit-test-assurance-precondition'),
                'two_factor_confirmed_at' => now(),
            ]);
        }
        parent::actingAs($user, $guard);
        $this->withSession([
            'auth.mfa_verified_at' => time(),
            'auth.mfa_method' => 'test_assurance_precondition',
        ]);

        return $this;
    }

    protected function actingAsWithoutMfa(UserContract $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        return $this;
    }

    protected function grantInstitutionRole(
        User $user,
        InstitutionRole $role,
        ?Institution $institution = null,
    ): InstitutionMembership {
        $institution ??= Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $membership = InstitutionMembership::query()->firstOrCreate(
            [
                'institution_id' => $institution->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'status' => InstitutionMembershipStatus::Active,
                'is_default' => true,
                'provenance' => 'test_fixture',
                'joined_at' => now(),
            ],
        );
        $membership->forceFill([
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'joined_at' => $membership->joined_at ?? now(),
        ])->save();

        InstitutionRoleAssignment::query()->updateOrCreate(
            [
                'institution_membership_id' => $membership->getKey(),
                'role' => $role->value,
            ],
            [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
                'revoked_at' => null,
            ],
        );

        return $membership;
    }
}
