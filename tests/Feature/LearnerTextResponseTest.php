<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\CurriculumEntity;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\LearnerTextResponse;
use App\Models\User;
use App\Services\CourseRevisionService;
use App\Services\DataExportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\InstallsCanonicalCurriculumFixture;
use Tests\TestCase;
use ZipArchive;

class LearnerTextResponseTest extends TestCase
{
    use InstallsCanonicalCurriculumFixture;
    use RefreshDatabase;

    public function test_learner_can_save_encrypted_draft_submit_it_and_cannot_change_submission(): void
    {
        $this->installCanonicalCurriculumFixture();
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);
        $otherLearner = User::factory()->create(['role' => UserRole::Learner]);
        $responseKey = (string) Str::uuid();
        $body = '<script>alert("unsafe")</script> I would apologise and offer help.';

        $this->actingAs($learner)
            ->get(route('responses.edit', ['HSP-C07-ACT-ROLEPLAY', 'HSP-C07-RP-FREE']))
            ->assertOk()
            ->assertSeeText('Save your response')
            ->assertDontSeeText('source_sha256');

        $this->post(route('responses.store', ['HSP-C07-ACT-ROLEPLAY', 'HSP-C07-RP-FREE']), [
            'response_key' => $responseKey,
            'kind' => 'journal',
            'intent' => 'save',
            'body' => $body,
        ])->assertRedirect(route('responses.edit', ['HSP-C07-ACT-ROLEPLAY', 'HSP-C07-RP-FREE']));

        $saved = LearnerTextResponse::query()->sole();
        $this->assertSame('draft', $saved->state);
        $this->assertSame('journal', $saved->kind);
        $this->assertSame($body, $saved->body);
        $this->assertSame('personal', $saved->learning_scope_key);
        $this->assertNull($saved->course_offering_id);
        $this->assertNull($saved->course_revision_id);
        $this->assertTrue(Gate::forUser($learner)->allows('view', $saved));
        $this->assertFalse(Gate::forUser($otherLearner)->allows('view', $saved));
        $rawBody = (string) DB::table('learner_text_responses')->value('body');
        $this->assertStringNotContainsString('offer help', $rawBody);

        $this->get(route('responses.index'))
            ->assertOk()
            ->assertSeeText('I would apologise and offer help.')
            ->assertDontSee('<script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false);

        $this->post(route('responses.store', ['HSP-C07-ACT-ROLEPLAY', 'HSP-C07-RP-FREE']), [
            'response_key' => $responseKey,
            'kind' => 'journal',
            'intent' => 'submit',
            'body' => 'Final response.',
        ])->assertRedirect(route('responses.index'));

        $saved->refresh();
        $this->assertSame('submitted', $saved->state);
        $this->assertSame('Final response.', $saved->body);
        $this->assertNotNull($saved->submitted_at);
        $this->assertFalse(Gate::forUser($learner)->allows('update', $saved));

        $this->post(route('responses.store', ['HSP-C07-ACT-ROLEPLAY', 'HSP-C07-RP-FREE']), [
            'response_key' => $responseKey,
            'kind' => 'journal',
            'intent' => 'save',
            'body' => 'Changed after submission.',
        ])->assertSessionHasErrors('body')
            ->assertSessionHasInput('response_key', $responseKey)
            ->assertSessionMissing('_old_input.body');
        $this->assertSame('Final response.', $saved->fresh()->body);

        $export = app(DataExportBuilder::class)->build($learner);
        $temporary = tempnam(sys_get_temp_dir(), 'hospitrainity-writing-export-');
        $this->assertNotFalse($temporary);
        file_put_contents($temporary, $export['bytes']);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($temporary));
        $savedWriting = $archive->getFromName('saved-writing.csv');
        $this->assertIsString($savedWriting);
        $this->assertStringContainsString('Final response.', $savedWriting);
        $archive->close();
        unlink($temporary);
    }

    public function test_response_rejects_closed_prompts_and_non_learner_routes(): void
    {
        $this->installCanonicalCurriculumFixture();
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);
        $responseKey = (string) Str::uuid();

        $this->actingAs($learner)
            ->get(route('responses.edit', ['HSP-C02-ACT-QUIZ', 'HSP-C02-QZ-Q1']))
            ->assertNotFound();
        $this->post(route('responses.store', ['HSP-C07-ACT-ROLEPLAY', 'HSP-C07-RP-FREE']), [
            'response_key' => $responseKey,
            'kind' => 'journal',
            'intent' => 'save',
            'body' => str_repeat('sensitive', 1000),
        ])->assertSessionHasErrors('body')
            ->assertSessionHasInput('response_key', $responseKey)
            ->assertSessionMissing('_old_input.body');
        $this->actingAs($admin)->get(route('responses.index'))->assertForbidden();
    }

    public function test_class_response_keeps_exact_enrollment_revision_and_curriculum_context(): void
    {
        $package = $this->installCanonicalCurriculumFixture();
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        $course = Course::query()->create([
            'institution_id' => $institution->getKey(),
            'key' => 'saved-writing-course',
            'title' => 'Saved Writing Course',
            'created_by_user_id' => $admin->getKey(),
        ]);
        $chapter = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('code', 'HSP-C02')
            ->sole();
        $revision = app(CourseRevisionService::class)->create(
            $admin,
            $course,
            $package,
            [$chapter->getKey()],
            'Front Desk writing',
        );
        $offering = CourseOffering::query()->create([
            'institution_id' => $institution->getKey(),
            'course_id' => $course->getKey(),
            'course_revision_id' => $revision->getKey(),
            'key' => 'saved-writing-class',
            'title' => 'Saved Writing Class',
            'status' => CourseOfferingStatus::Active,
            'created_by_user_id' => $admin->getKey(),
        ]);
        $enrollment = CourseEnrollment::query()->create([
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $learnerMembership->getKey(),
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($learner)->post(route('learning-context.select'), [
            'scope' => 'class',
            'course_enrollment_id' => $enrollment->getKey(),
        ])->assertRedirect();
        $this->post(route('responses.store', ['HSP-C02-ACT-ROLEPLAY', 'HSP-C02-RP-FREE']), [
            'response_key' => (string) Str::uuid(),
            'kind' => 'assessment',
            'intent' => 'submit',
            'body' => 'Welcome. May I see your identification, please?',
        ])->assertRedirect(route('responses.index'));

        $saved = LearnerTextResponse::query()->sole();
        $this->assertSame('class:'.$offering->getKey(), $saved->learning_scope_key);
        $this->assertSame($learnerMembership->getKey(), $saved->institution_membership_id);
        $this->assertSame($offering->getKey(), $saved->course_offering_id);
        $this->assertSame($enrollment->getKey(), $saved->course_enrollment_id);
        $this->assertSame($revision->getKey(), $saved->course_revision_id);
        $this->assertSame($package->getKey(), $saved->curriculum_package_id);
        $this->assertSame('HSP-C02-ACT-ROLEPLAY', $saved->activity->code);
        $this->assertSame('HSP-C02-RP-FREE', $saved->prompt->code);

        $this->get(route('responses.edit', ['HSP-C07-ACT-ROLEPLAY', 'HSP-C07-RP-FREE']))
            ->assertNotFound();
    }

    private function membership(User $user, Institution $institution, InstitutionRole $role): InstitutionMembership
    {
        $membership = InstitutionMembership::query()->create([
            'institution_id' => $institution->getKey(),
            'user_id' => $user->getKey(),
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'test_fixture',
            'joined_at' => now(),
        ]);
        InstitutionRoleAssignment::query()->create([
            'institution_membership_id' => $membership->getKey(),
            'role' => $role,
            'assigned_at' => now(),
        ]);

        return $membership;
    }
}
