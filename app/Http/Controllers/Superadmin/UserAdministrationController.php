<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\ListUsersRequest;
use App\Http\Requests\Superadmin\PromoteSuperadminRequest;
use App\Http\Requests\Superadmin\UpdateUserRoleRequest;
use App\Models\User;
use App\Notifications\UserRoleChanged;
use App\Services\UserRoleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class UserAdministrationController extends Controller
{
    public function __construct(private readonly UserRoleManager $roleManager) {}

    public function index(ListUsersRequest $request): View
    {
        $filters = $request->validated();
        $users = User::query()
            ->select(['id', 'name', 'instansi', 'email', 'email_verified_at', 'role', 'created_at'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $pattern = '%'.trim($search).'%';
                    $query->whereLike('name', $pattern)
                        ->orWhereLike('email', $pattern)
                        ->orWhereLike('instansi', $pattern);
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['institution'] ?? null, fn ($query, string $institution) => $query->where('instansi', $institution))
            ->when(($filters['verification'] ?? null) === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when(($filters['verification'] ?? null) === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $institutions = User::query()
            ->whereNotNull('instansi')
            ->where('instansi', '!=', '')
            ->distinct()
            ->orderBy('instansi')
            ->pluck('instansi');

        return view('superadmin.users.index', [
            'users' => $users,
            'institutions' => $institutions,
            'roles' => UserRole::cases(),
            'assignableRoles' => UserRole::assignableWithoutSuperadmin(),
            'filters' => $filters,
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        return $this->performChange(
            $request,
            $user,
            UserRole::from($request->validated('role')),
            false,
        );
    }

    public function promoteSuperadmin(PromoteSuperadminRequest $request, User $user): RedirectResponse
    {
        return $this->performChange($request, $user, UserRole::Superadmin, true);
    }

    private function performChange(
        UpdateUserRoleRequest|PromoteSuperadminRequest $request,
        User $user,
        UserRole $newRole,
        bool $superadminPromotion,
    ): RedirectResponse {
        $result = $this->roleManager->change(
            actor: $request->user(),
            target: $user,
            newRole: $newRole,
            expectedRole: UserRole::from($request->validated('expected_role')),
            reason: $request->validated('reason'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            superadminPromotion: $superadminPromotion,
        );

        try {
            $result['target']->notify(new UserRoleChanged($result['old_role'], $result['new_role']));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('warning', __('admin.role_changed_notification_failed'));
        }

        return back()->with('success', __('admin.role_changed_successfully', [
            'name' => $result['target']->name,
            'role' => __('admin.roles.'.$result['new_role']->value),
        ]));
    }
}
