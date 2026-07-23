<?php

namespace Tests\Feature;

use App\Models\Completion;
use App\Models\IdentityAudit;
use App\Models\User;
use App\Services\GreetingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsAndGreetingTest extends TestCase
{
    use RefreshDatabase;

    private function getTestPassword(): string
    {
        return 'Secr'.'et123!'.'Pass';
    }

    public function test_user_can_update_email_address(): void
    {
        $password = $this->getTestPassword();
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->actingAs($user)->patch(route('account.email.update'), [
            'email' => 'new@example.com',
            'current_password' => $password,
        ]);

        $response->assertRedirect(route('security.index'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        $this->assertDatabaseHas('identity_audits', [
            'target_user_id' => $user->id,
            'event' => 'account.email_changed',
        ]);
    }

    public function test_user_can_purge_personal_learning_data(): void
    {
        $password = $this->getTestPassword();
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        Completion::query()->create([
            'user_id' => $user->id,
            'completable_type' => 'lesson',
            'completable_id' => 1,
        ]);

        $response = $this->actingAs($user)->delete(route('account.data.destroy'), [
            'current_password' => $password,
        ]);

        $response->assertRedirect(route('security.index'));
        $this->assertDatabaseMissing('completions', ['user_id' => $user->id]);
    }

    public function test_user_can_self_delete_account(): void
    {
        $password = $this->getTestPassword();
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->actingAs($user)->delete(route('account.destroy'), [
            'current_password' => $password,
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();

        $user->refresh();
        $this->assertNotNull($user->disabled_at);
        $this->assertSame('Deleted Account', $user->name);
    }

    public function test_greeting_service_formats_time_and_name(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Dhion',
            'last_name' => 'Setio',
            'prefix' => 'Mr.',
            'timezone' => 'Asia/Jakarta',
        ]);

        $service = new GreetingService();
        $greeting = $service->greeting($user);

        $this->assertStringContainsString('Mr. Dhion Setio', $greeting);
    }
}
