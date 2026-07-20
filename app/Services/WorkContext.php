<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Enums\PlatformRole;
use App\Enums\UserCapability;
use App\Enums\UserRole;
use App\Enums\WorkContextRole;
use App\Models\IdentityAudit;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class WorkContext
{
    public const SESSION_ROLE_KEY = 'identity.active_work_role';

    public const SESSION_PREVIEW_KEY = 'identity.work_context_preview';

    public function __construct(
        private readonly InstitutionContext $institutions,
        private readonly InstitutionAccessService $access,
    ) {}

    public function current(Request $request, User $user): WorkContextRole
    {
        $stored = $request->hasSession()
            ? WorkContextRole::tryFrom((string) $request->session()->get(self::SESSION_ROLE_KEY, ''))
            : null;
        $preview = $request->hasSession() && $request->session()->get(self::SESSION_PREVIEW_KEY) === true;
        if ($stored !== null && $this->canUse($request, $user, $stored, $preview)) {
            return $stored;
        }

        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_ROLE_KEY);
            $request->session()->forget(self::SESSION_PREVIEW_KEY);
        }

        return match ($user->role) {
            UserRole::Superadmin => WorkContextRole::SystemAdmin,
            UserRole::Admin => WorkContextRole::ContentAuthor,
            // Preserve the legacy staff route shape during expand-first
            // migration. Resource policies still deny as not-found when no
            // valid institution context exists.
            UserRole::Supervisor => WorkContextRole::Instructor,
            default => WorkContextRole::Learner,
        };
    }

    /** @return Collection<int, array{role: WorkContextRole, institution: Institution|null, preview: bool}> */
    public function available(Request $request, User $user): Collection
    {
        $contexts = collect([['role' => WorkContextRole::Learner, 'institution' => null, 'preview' => false]]);
        foreach ($this->institutions->availableFor($user) as $institution) {
            if ($user->hasInstitutionRole($institution, InstitutionRole::Instructor)) {
                $contexts->push(['role' => WorkContextRole::Instructor, 'institution' => $institution, 'preview' => false]);
            }
            if ($user->hasInstitutionRole($institution, InstitutionRole::InstitutionAdmin)) {
                $contexts->push(['role' => WorkContextRole::InstitutionAdmin, 'institution' => $institution, 'preview' => false]);
            }
        }
        if ($user->isContentAdministrator() && ! $user->isSuperAdmin()) {
            $contexts->push(['role' => WorkContextRole::ContentAuthor, 'institution' => null, 'preview' => false]);
        }
        if ($this->access->isSystemAdmin($user)) {
            $contexts->push(['role' => WorkContextRole::SystemAdmin, 'institution' => null, 'preview' => false]);
            foreach ($this->institutions->availableFor($user) as $institution) {
                $contexts->push(['role' => WorkContextRole::Instructor, 'institution' => $institution, 'preview' => true]);
                $contexts->push(['role' => WorkContextRole::InstitutionAdmin, 'institution' => $institution, 'preview' => true]);
            }
        }

        return $contexts->values();
    }

    public function hasAlternativeRole(User $user): bool
    {
        if ($user->isDisabled()) {
            return false;
        }

        // Every legacy staff identity can also enter its Learner context.
        if ($user->role !== UserRole::Learner) {
            return true;
        }

        return User::query()
            ->whereKey($user->getKey())
            ->where(function ($query): void {
                $query->whereHas('platformRoleAssignments', function ($assignments): void {
                    $assignments->where('role', PlatformRole::SystemAdmin->value)
                        ->whereNull('revoked_at');
                })->orWhereHas('capabilityAssignments', function ($assignments): void {
                    $assignments->where('capability', UserCapability::ContentAuthor->value)
                        ->whereNull('revoked_at');
                })->orWhereHas('institutionMemberships', function ($memberships): void {
                    $memberships->where('status', InstitutionMembershipStatus::Active->value)
                        ->whereHas('institution', fn ($institutions) => $institutions->where('status', InstitutionStatus::Active->value))
                        ->whereHas('roleAssignments', function ($assignments): void {
                            $assignments->whereIn('role', [
                                InstitutionRole::Instructor->value,
                                InstitutionRole::InstitutionAdmin->value,
                            ])->whereNull('revoked_at');
                        });
                });
            })
            ->exists();
    }

    public function select(
        Request $request,
        User $user,
        WorkContextRole $role,
        ?string $institutionId = null,
        bool $preview = false,
    ): void {
        if (in_array($role, [WorkContextRole::Instructor, WorkContextRole::InstitutionAdmin], true)) {
            if ($institutionId === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $this->institutions->selectById($request, $user, $institutionId);
        }
        if (! $this->canUse($request, $user, $role, $preview)) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        $request->session()->put(self::SESSION_ROLE_KEY, $role->value);
        if ($preview) {
            $request->session()->put(self::SESSION_PREVIEW_KEY, true);
            IdentityAudit::query()->create([
                'actor_user_id' => $user->getKey(),
                'institution_id' => $institutionId,
                'event' => 'work_context.preview_started',
                'metadata' => ['role' => $role->value],
                'created_at' => now(),
            ]);
        } else {
            $request->session()->forget(self::SESSION_PREVIEW_KEY);
        }
    }

    public function canUse(Request $request, User $user, WorkContextRole $role, bool $preview = false): bool
    {
        if ($user->isDisabled()) {
            return false;
        }

        if ($preview) {
            return $this->access->isSystemAdmin($user)
                && in_array($role, [WorkContextRole::Instructor, WorkContextRole::InstitutionAdmin], true);
        }

        return match ($role) {
            WorkContextRole::Learner => true,
            WorkContextRole::ContentAuthor => ! $user->isSuperAdmin() && $user->isContentAdministrator(),
            WorkContextRole::SystemAdmin => $this->access->isSystemAdmin($user),
            WorkContextRole::Instructor, WorkContextRole::InstitutionAdmin => $this->canUseInstitutionRole($request, $user, $role),
        };
    }

    private function canUseInstitutionRole(Request $request, User $user, WorkContextRole $workRole): bool
    {
        try {
            $institution = $this->institutions->current($request, $user);
        } catch (AuthorizationException) {
            return false;
        }

        $role = $workRole === WorkContextRole::InstitutionAdmin
            ? InstitutionRole::InstitutionAdmin
            : InstitutionRole::Instructor;
        if ($user->hasInstitutionRole($institution, $role)) {
            return true;
        }

        return $workRole === WorkContextRole::Instructor
            && ! $user->isSuperAdmin()
            && $this->access->canManageLearners($user, $institution);
    }
}
