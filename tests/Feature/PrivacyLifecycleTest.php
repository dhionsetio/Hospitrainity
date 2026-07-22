<?php

namespace Tests\Feature;

use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use App\Enums\UserRole;
use App\Jobs\SendPushNotification;
use App\Models\CurriculumEntity;
use App\Models\DataExport;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\LearnerTextResponse;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\AccountErasureService;
use App\Services\DataSubjectRequestService;
use App\Services\PrivacyRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\InstallsCanonicalCurriculumFixture;
use Tests\TestCase;
use ZipArchive;

class PrivacyLifecycleTest extends TestCase
{
    use InstallsCanonicalCurriculumFixture;
    use RefreshDatabase;

    public function test_public_policy_pages_show_useful_content_and_links_before_registration(): void
    {
        foreach (['privacy', 'terms', 'accessibility', 'acceptable-use', 'support'] as $type) {
            $this->get(route('policies.show', ['type' => $type]))
                ->assertOk()
                ->assertSee('href="'.url('/').'"', false)
                ->assertDontSee('2026-07-20-prototype.1')
                ->assertDontSee('qualified legal review not recorded', false);
        }

        $this->get(route('policies.show', ['type' => 'privacy']))
            ->assertOk()
            ->assertSeeText('Data we handle')
            ->assertSeeText('How long data is kept');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee(route('policies.show', ['type' => 'privacy']), false)
            ->assertSee(route('policies.show', ['type' => 'terms']), false);

        $learner = User::factory()->create();
        $this->actingAs($learner)
            ->get(route('policies.show', ['type' => 'privacy']))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('Return to current dashboard');
    }

    public function test_registration_records_separate_versioned_policy_acknowledgements(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Policy Learner',
            'email' => 'policy@example.test',
            'password' => 'Correct Horse Battery Staple 2026!',
            'password_confirmation' => 'Correct Horse Battery Staple 2026!',
            'scope_acknowledgement' => '1',
            'policy_acknowledgement' => '1',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'policy@example.test')->firstOrFail();
        $this->assertDatabaseCount('policy_acknowledgements', 2);
        $this->assertDatabaseHas('policy_acknowledgements', [
            'user_id' => $user->getKey(),
            'policy_type' => 'privacy',
            'version' => '2026-07-20-prototype.1',
            'source' => 'registration',
        ]);
    }

    public function test_sensitive_requests_require_recent_password_and_duplicate_active_requests_are_bounded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('privacy-requests.index'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('Return to current dashboard');

        $this->post(route('privacy-requests.store'), [
            'type' => DataSubjectRequestType::AccessExport->value,
            'confirm_effects' => '1',
        ])->assertRedirect(route('privacy-requests.sensitive', ['type' => 'access-export']));
        $this->assertDatabaseCount('data_subject_requests', 0);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('privacy-requests.store'), [
                'type' => DataSubjectRequestType::AccessExport->value,
                'confirm_effects' => '1',
            ])->assertRedirect(route('privacy-requests.index'));
        $this->assertDatabaseCount('data_subject_requests', 1);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('privacy-requests.index'))
            ->post(route('privacy-requests.store'), [
                'type' => DataSubjectRequestType::AccessExport->value,
                'confirm_effects' => '1',
            ])->assertSessionHasErrors('type');
        $this->assertDatabaseCount('data_subject_requests', 1);
    }

    public function test_approved_export_is_encrypted_at_rest_signed_owner_only_and_hash_checked(): void
    {
        Storage::fake('local');
        $learner = User::factory()->create(['name' => '=Formula Learner']);
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $service = app(DataSubjectRequestService::class);
        $privacyRequest = $service->submit($learner, DataSubjectRequestType::AccessExport, null, true);
        $service->transition($privacyRequest, DataSubjectRequestStatus::InReview, $admin, null, null);
        $service->transition($privacyRequest->fresh(), DataSubjectRequestStatus::Approved, $admin, 'approved', 'Identity and export scope reviewed.');

        $privacyRequest->refresh();
        $export = $privacyRequest->export()->firstOrFail();
        $this->assertSame(DataSubjectRequestStatus::Completed, $privacyRequest->status);
        $this->assertSame('available', $export->status);
        $stored = Storage::disk('local')->get($export->encrypted_path);
        $this->assertStringNotContainsString('Formula Learner', $stored);
        $this->assertStringNotContainsString($learner->email, $stored);
        $plainZip = Crypt::decryptString($stored);
        $this->assertSame($export->payload_sha256, hash('sha256', $plainZip));
        $temporary = tempnam(sys_get_temp_dir(), 'hospitrainity-export-test-');
        $this->assertNotFalse($temporary);
        file_put_contents($temporary, $plainZip);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($temporary));
        $summary = $archive->getFromName('summary.html');
        $this->assertIsString($summary);
        $this->assertStringContainsString('<html lang="en">', $summary);
        $this->assertStringContainsString('=Formula Learner', $summary);
        $this->assertStringNotContainsString('<script', $summary);
        $archive->close();
        unlink($temporary);

        $signed = URL::temporarySignedRoute('privacy-exports.download', now()->addMinutes(5), ['export' => $export]);
        $this->actingAs(User::factory()->create())->get($signed)->assertNotFound();
        $this->actingAs($learner)->get($signed)
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
        $this->assertNotNull($export->fresh()->downloaded_at);
    }

    public function test_deletion_orchestrator_is_idempotent_and_keeps_only_pseudonymous_institution_evidence(): void
    {
        $package = $this->installCanonicalCurriculumFixture();
        $learner = User::factory()->create(['email' => 'erase@example.test']);
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $institution = Institution::query()->create([
            'id' => (string) Str::uuid(),
            'key' => 'privacy-test',
            'name_id' => 'Institusi Uji Privasi',
            'name_en' => 'Privacy Test Institution',
            'status' => 'active',
        ]);
        $membership = InstitutionMembership::query()->create([
            'institution_id' => $institution->getKey(),
            'user_id' => $learner->getKey(),
            'status' => 'active',
            'is_default' => true,
            'provenance' => 'test_double',
            'joined_at' => now(),
        ]);
        $activity = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('code', 'HSP-C07-ACT-ROLEPLAY')
            ->sole();
        $prompt = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('code', 'HSP-C07-RP-FREE')
            ->sole();
        $responseEvidence = [
            'user_id' => $learner->getKey(),
            'curriculum_package_id' => $package->getKey(),
            'activity_entity_id' => $activity->getKey(),
            'prompt_entity_id' => $prompt->getKey(),
            'activity_source_sha256' => $activity->source_sha256,
            'prompt_source_sha256' => $prompt->source_sha256,
        ];
        $personalBody = 'Private personal journal response.';
        $personalResponse = LearnerTextResponse::query()->create($responseEvidence + [
            'response_key' => (string) Str::uuid(),
            'learning_scope_key' => 'personal',
            'institution_membership_id' => null,
            'kind' => 'journal',
            'state' => 'draft',
            'body' => $personalBody,
            'body_hmac_sha256' => hash_hmac('sha256', $personalBody, (string) config('app.key')),
        ]);
        $institutionBody = 'Submitted institution assessment response.';
        $institutionResponse = LearnerTextResponse::query()->create($responseEvidence + [
            'response_key' => (string) Str::uuid(),
            'learning_scope_key' => 'institution:'.$membership->getKey(),
            'institution_membership_id' => $membership->getKey(),
            'kind' => 'assessment',
            'state' => 'submitted',
            'body' => $institutionBody,
            'body_hmac_sha256' => hash_hmac('sha256', $institutionBody, (string) config('app.key')),
            'submitted_at' => now(),
        ]);

        DB::table('curriculum_activity_progress')->insert([
            [
                'user_id' => $learner->getKey(), 'learning_scope_key' => 'personal', 'institution_membership_id' => null,
                'package_name' => 'test', 'content_version' => '1', 'activity_code' => 'personal-a', 'section_code' => 's',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'user_id' => $learner->getKey(), 'learning_scope_key' => 'institution:'.$membership->getKey(), 'institution_membership_id' => $membership->getKey(),
                'package_name' => 'test', 'content_version' => '1', 'activity_code' => 'institution-a', 'section_code' => 's',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        PushSubscription::query()->create([
            'user_id' => $learner->getKey(),
            'endpoint_hash' => hash('sha256', 'https://push.example.test/one'),
            'endpoint' => 'https://push.example.test/one',
            'public_key' => 'test-double-key',
            'auth_token' => 'test-double-auth',
        ]);

        $service = app(DataSubjectRequestService::class);
        $privacyRequest = $service->submit($learner, DataSubjectRequestType::Deletion, 'Delete my personal account.', true);
        $service->transition($privacyRequest, DataSubjectRequestStatus::InReview, $admin, null, null);
        $service->transition($privacyRequest->fresh(), DataSubjectRequestStatus::Approved, $admin, 'approved', 'Pseudonymization and institution evidence retention approved.');

        $privacyRequest->refresh();
        $learner->refresh();
        $this->assertSame(DataSubjectRequestStatus::Completed, $privacyRequest->status);
        $this->assertTrue($learner->isDisabled());
        $this->assertStringStartsWith('deleted+', $learner->email);
        $this->assertNull($learner->email_verified_at);
        $this->assertDatabaseMissing('curriculum_activity_progress', ['user_id' => $learner->getKey(), 'activity_code' => 'personal-a']);
        $this->assertDatabaseHas('curriculum_activity_progress', ['user_id' => $learner->getKey(), 'activity_code' => 'institution-a']);
        $this->assertDatabaseMissing('learner_text_responses', ['id' => $personalResponse->getKey()]);
        $this->assertDatabaseHas('learner_text_responses', ['id' => $institutionResponse->getKey()]);
        $this->assertSame($institutionBody, $institutionResponse->fresh()->body);
        $this->assertDatabaseHas('institution_memberships', ['id' => $membership->getKey(), 'status' => 'revoked', 'is_default' => false]);
        $this->assertNotNull(PushSubscription::query()->firstOrFail()->revoked_at);
        $this->assertDatabaseCount('account_erasure_steps', 5);

        app(AccountErasureService::class)->execute($privacyRequest, $service);
        $this->assertDatabaseCount('account_erasure_steps', 5);
    }

    public function test_staff_queue_is_system_admin_only_and_recent_password_protected(): void
    {
        $learner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        app(DataSubjectRequestService::class)->submit($learner, DataSubjectRequestType::Correction, 'Correct my name.', false);

        $this->actingAs($learner)->get(route('superadmin.privacy-requests.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('superadmin.privacy-requests.index'))->assertRedirect(route('password.confirm'));
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('superadmin.privacy-requests.index'))->assertOk()->assertSee($learner->name)->assertDontSee('Correct my name.');
    }

    public function test_account_deletion_cannot_be_self_approved_by_a_system_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $service = app(DataSubjectRequestService::class);
        $request = $service->submit($admin, DataSubjectRequestType::Deletion, null, true);
        $service->transition($request, DataSubjectRequestStatus::InReview, $admin, null, null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('deletion_self_approval_denied');
        $service->transition($request->fresh(), DataSubjectRequestStatus::Approved, $admin, 'approved', null);
    }

    public function test_push_subscription_is_explicit_encrypted_owner_scoped_and_test_delivery_is_queued(): void
    {
        config()->set('push.enabled', true);
        config()->set('push.vapid.public_key', str_repeat('A', 87));
        config()->set('push.vapid.private_key', str_repeat('B', 43));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $endpoint = 'https://push.example.test/subscription-one';

        $response = $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => str_repeat('C', 87), 'auth' => str_repeat('D', 22)],
            'contentEncoding' => 'aes128gcm',
        ])->assertCreated();
        $id = $response->json('id');
        $stored = PushSubscription::query()->findOrFail($id);
        $this->assertSame($endpoint, $stored->endpoint);
        $this->assertStringNotContainsString($endpoint, (string) DB::table('push_subscriptions')->where('id', $id)->value('endpoint'));

        $this->actingAs($other)->deleteJson(route('push-subscriptions.destroy', $stored))->assertNotFound();
        Queue::fake();
        $this->actingAs($user)->postJson(route('push-subscriptions.test'))->assertAccepted();
        Queue::assertPushed(SendPushNotification::class, fn ($job): bool => $job->userId === $user->getKey());

        $this->actingAs($user)->deleteJson(route('push-subscriptions.destroy', $stored))->assertOk();
        $this->assertNotNull($stored->fresh()->revoked_at);
    }

    public function test_retention_preview_is_non_mutating_and_execute_expires_only_due_artifacts(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = app(DataSubjectRequestService::class)->submit($user, DataSubjectRequestType::Correction, 'old request note', false);
        $request->forceFill([
            'status' => DataSubjectRequestStatus::Completed,
            'decision_note' => 'old decision note',
            'updated_at' => now()->subDays(1100),
        ])->save();
        $export = DataExport::query()->create([
            'data_subject_request_id' => $request->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'available',
            'encrypted_path' => 'privacy-exports/expired.enc',
            'expires_at' => now()->subMinute(),
        ]);
        Storage::disk('local')->put('privacy-exports/expired.enc', 'encrypted-test-double');

        $preview = app(PrivacyRetentionService::class)->run(false);
        $this->assertSame(1, $preview['expired_exports']);
        Storage::disk('local')->assertExists('privacy-exports/expired.enc');
        $this->assertNotNull($request->fresh()->request_note);

        app(PrivacyRetentionService::class)->run(true);
        Storage::disk('local')->assertMissing('privacy-exports/expired.enc');
        $this->assertSame('expired', $export->fresh()->status);
        $this->assertNull($request->fresh()->request_note);
        $this->assertNull($request->fresh()->decision_note);
    }

    public function test_privacy_migration_rolls_back_without_changing_accounts_and_reapplies_cleanly(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_07_20_000014_create_privacy_lifecycle_tables.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('data_subject_requests'));
        $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'email' => $user->email]);

        $migration->up();
        $this->assertTrue(Schema::hasTable('data_subject_requests'));
        $this->assertTrue(Schema::hasTable('push_subscriptions'));
        $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'email' => $user->email]);
    }
}
