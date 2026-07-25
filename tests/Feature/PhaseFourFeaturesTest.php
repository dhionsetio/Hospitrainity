<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFourFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_leave_institution_membership_and_revert_to_neutral_context(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $institution = Institution::query()->create([
            'key' => 'test-hq-4',
            'name_en' => 'HQ Institution',
            'name_id' => 'HQ Institution',
        ]);

        $membership = InstitutionMembership::query()->create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'status' => InstitutionMembershipStatus::Active,
            'provenance' => 'join_code',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('institution-enrollment.destroy', $membership));

        $response->assertRedirect(route('institution-enrollment.index'));
        $response->assertSessionHas('status');

        $membership->refresh();
        $this->assertSame(InstitutionMembershipStatus::Revoked, $membership->status);
        $this->assertNotNull($membership->revoked_at);

        $this->assertDatabaseHas('identity_audits', [
            'target_user_id' => $user->id,
            'institution_id' => $institution->id,
            'event' => 'institution_membership.self_left',
        ]);
    }

    public function test_superadmin_can_export_progress_csv_with_formula_sanitization(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.progress.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Learner ID', $content);
        $this->assertStringContainsString('Email', $content);

        $this->assertDatabaseHas('administration_audits', [
            'actor_user_id' => $superadmin->id,
            'event' => 'superadmin.progress_exported',
        ]);
    }
}
