<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Pure role-predicate checks. No database is touched. The role helpers only
 * read the in-memory `role` attribute, so these run fast and in isolation.
 */
class UserRoleTest extends TestCase
{
    public function test_superadmin_role_is_detected(): void
    {
        $user = (new User)->forceFill(['role' => UserRole::Superadmin]);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->isSupervisor());
        $this->assertTrue($user->isContentAdministrator());
    }

    public function test_supervisor_role_is_detected(): void
    {
        $user = (new User)->forceFill(['role' => UserRole::Supervisor]);

        $this->assertTrue($user->isSupervisor());
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_plain_user_has_no_elevated_roles(): void
    {
        $user = (new User)->forceFill(['role' => UserRole::Learner]);

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isSupervisor());
        $this->assertFalse($user->isContentAdministrator());
    }

    public function test_content_admin_is_distinct_from_superadmin(): void
    {
        $user = (new User)->forceFill(['role' => UserRole::Admin]);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isContentAdministrator());
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_role_is_not_mass_assignable_through_generic_user_input(): void
    {
        $user = new User([
            'name' => 'Example',
            'email' => 'example@example.test',
            'role' => UserRole::Superadmin->value,
        ]);

        $this->assertNull($user->getRawOriginal('role'));
    }

    public function test_role_middleware_fails_closed_for_an_unknown_configured_role(): void
    {
        $user = (new User)->forceFill(['role' => UserRole::Admin]);
        $request = Request::create('/admin/example');
        $request->setUserResolver(static fn (): User => $user);

        try {
            (new CheckRole)->handle(
                $request,
                static fn (): Response => new Response,
                UserRole::Admin->value,
                'owner',
            );

            $this->fail('Unknown configured roles must not pass the middleware.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
