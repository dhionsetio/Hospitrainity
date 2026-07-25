<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_notifications_index_and_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);

        $notification = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\ContentPublishedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'content_published',
                'title' => 'New Module Available',
                'body' => 'A new module has been published.',
                'url' => route('dashboard'),
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertOk()
            ->assertSee('New Module Available')
            ->assertSee('A new module has been published.');

        $panelResponse = $this->actingAs($user)->getJson(route('notifications.panel'));
        $panelResponse->assertOk()
            ->assertJson([
                'unread_count' => 1,
                'items' => [
                    [
                        'id' => $notification->id,
                        'title' => 'New Module Available',
                    ],
                ],
            ]);
    }

    public function test_user_can_mark_single_and_all_notifications_as_read(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);

        $notification1 = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'First Notification'],
            'read_at' => null,
        ]);

        $notification2 = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Second Notification'],
            'read_at' => null,
        ]);

        $this->actingAs($user)->patchJson(route('notifications.read', $notification1->id))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertNotNull($notification1->fresh()->read_at);
        $this->assertNull($notification2->fresh()->read_at);

        $this->actingAs($user)->patchJson(route('notifications.readAll'))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertNotNull($notification2->fresh()->read_at);
    }

    public function test_user_can_delete_notification(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);

        $notification = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Delete Me'],
            'read_at' => null,
        ]);

        $this->actingAs($user)->deleteJson(route('notifications.destroy', $notification->id))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }
}
