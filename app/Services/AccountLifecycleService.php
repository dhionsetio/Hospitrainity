<?php

namespace App\Services;

use App\Enums\AccountDisableReason;
use App\Models\IdentityAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class AccountLifecycleService
{
    /** @return array{changed: bool, sessions_revoked: int} */
    public function disable(User $target, User $actor, AccountDisableReason $reason): array
    {
        return DB::transaction(function () use ($target, $actor, $reason): array {
            $lockedActor = User::query()->lockForUpdate()->find($actor->getKey());
            $lockedTarget = User::query()->lockForUpdate()->find($target->getKey());
            if ($lockedActor === null
                || $lockedTarget === null
                || $lockedActor->isDisabled()
                || ! $lockedActor->isSuperAdmin()
                || $lockedActor->is($lockedTarget)) {
                throw new RuntimeException('The account lifecycle transition is not authorized from the current state.');
            }

            if ($lockedTarget->isDisabled()) {
                return ['changed' => false, 'sessions_revoked' => 0];
            }

            $sessionTable = (string) config('session.table', 'sessions');
            if (config('session.driver') !== 'database' || ! Schema::hasTable($sessionTable)) {
                throw new RuntimeException('Account disabling requires the centrally revocable database session store.');
            }

            $lockedTarget->forceFill([
                'disabled_at' => now(),
                'disabled_by_user_id' => $lockedActor->getKey(),
                'disabled_reason_code' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            $sessionsRevoked = DB::table($sessionTable)
                ->where('user_id', $lockedTarget->getKey())
                ->delete();

            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $lockedTarget->getKey(),
                'event' => 'account.disabled',
                'metadata' => [
                    'reason_code' => $reason->value,
                    'sessions_revoked' => $sessionsRevoked,
                ],
                'created_at' => now(),
            ]);

            return ['changed' => true, 'sessions_revoked' => $sessionsRevoked];
        }, 3);
    }

    /** @return array{changed: bool} */
    public function enable(User $target, User $actor): array
    {
        return DB::transaction(function () use ($target, $actor): array {
            $lockedActor = User::query()->lockForUpdate()->find($actor->getKey());
            $lockedTarget = User::query()->lockForUpdate()->find($target->getKey());
            if ($lockedActor === null
                || $lockedTarget === null
                || $lockedActor->isDisabled()
                || ! $lockedActor->isSuperAdmin()) {
                throw new RuntimeException('The account lifecycle transition is not authorized from the current state.');
            }

            if (! $lockedTarget->isDisabled()) {
                return ['changed' => false];
            }

            $lockedTarget->forceFill([
                'disabled_at' => null,
                'disabled_by_user_id' => null,
                'disabled_reason_code' => null,
                'remember_token' => Str::random(60),
            ])->save();

            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $lockedTarget->getKey(),
                'event' => 'account.enabled',
                'created_at' => now(),
            ]);

            return ['changed' => true];
        }, 3);
    }
}
