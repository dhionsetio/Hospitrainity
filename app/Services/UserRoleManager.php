<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AdministrationAudit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UserRoleManager
{
    /**
     * @return array{target: User, old_role: UserRole, new_role: UserRole, audit: AdministrationAudit, sessions_revoked: int}
     */
    public function change(
        User $actor,
        User $target,
        UserRole $newRole,
        UserRole $expectedRole,
        string $reason,
        ?string $ipAddress,
        ?string $userAgent,
        bool $superadminPromotion = false,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $target,
            $newRole,
            $expectedRole,
            $reason,
            $ipAddress,
            $userAgent,
            $superadminPromotion,
        ): array {
            $lockedUsers = User::query()
                ->whereKey([$actor->getKey(), $target->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var User|null $lockedActor */
            $lockedActor = $lockedUsers->get($actor->getKey());
            /** @var User|null $lockedTarget */
            $lockedTarget = $lockedUsers->get($target->getKey());

            if ($lockedActor === null || $lockedTarget === null || ! $lockedActor->isSuperAdmin()) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            if ($lockedActor->is($lockedTarget)) {
                throw ValidationException::withMessages([
                    'role' => __('admin.cannot_change_own_role'),
                ]);
            }

            if ($lockedTarget->role !== $expectedRole) {
                throw ValidationException::withMessages([
                    'role' => __('admin.role_changed_since_page_load'),
                ]);
            }

            if ($lockedTarget->role === $newRole) {
                throw ValidationException::withMessages([
                    'role' => __('admin.role_must_change'),
                ]);
            }

            if ($superadminPromotion !== ($newRole === UserRole::Superadmin)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            if ($newRole->isElevated() && ! $lockedTarget->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'role' => __('admin.elevated_role_requires_verified_email'),
                ]);
            }

            if ($lockedTarget->isSuperAdmin()) {
                $superadminIds = User::query()
                    ->where('role', UserRole::Superadmin->value)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                if ($superadminIds->count() <= 1) {
                    throw ValidationException::withMessages([
                        'role' => __('admin.last_superadmin_cannot_be_demoted'),
                    ]);
                }
            }

            $oldRole = $lockedTarget->role;
            $lockedTarget->forceFill([
                'role' => $newRole,
                'remember_token' => Str::random(60),
            ])->save();

            $sessionsRevoked = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $lockedTarget->getKey())
                ->delete();

            $audit = AdministrationAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $lockedTarget->getKey(),
                'event' => $superadminPromotion ? 'user.promoted_superadmin' : 'user.role_changed',
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'reason' => trim($reason),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
                'metadata' => ['sessions_revoked' => $sessionsRevoked],
                'created_at' => now(),
            ]);

            return [
                'target' => $lockedTarget->fresh(),
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'audit' => $audit,
                'sessions_revoked' => $sessionsRevoked,
            ];
        }, 3);
    }
}
