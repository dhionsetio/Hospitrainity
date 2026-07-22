<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Quality\ActiveBrandGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BrandIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIVE_BRAND_PATTERN = '~stay(?:[\s_.-]|<[^>]+>)*ready~iu';

    private string $artifactRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactRoot = storage_path('framework/testing/brand-integrity-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');
        config([
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
        ]);
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->artifactRoot);

        parent::tearDown();
    }

    public function test_active_artifact_and_database_guard_has_only_the_exact_historical_allowance(): void
    {
        $report = app(ActiveBrandGuard::class)->inspect();

        $this->assertSame('verified', $report['status']);
        $this->assertSame(0, $report['active_match_count']);
        $this->assertSame([], $report['violations']);
        $this->assertSame([[
            'path' => 'curriculum/hospitrainity/0.4.0-draft/provenance/legacy-inventory.json',
            'match' => 'StayReady to Hospitrainity rebrand',
        ]], $report['historical_allowlist']['file_matches']);
        $this->assertCount(1, $report['historical_allowlist']['database_matches']);
        $this->assertSame('StayReady to Hospitrainity rebrand', $report['historical_allowlist']['database_matches'][0]['match']);
    }

    public function test_public_auth_and_every_role_surface_render_only_the_current_brand(): void
    {
        $responses = [
            $this->get('/'),
            $this->get(route('login')),
            $this->get(route('register')),
            $this->get(route('password.request')),
        ];

        foreach (['user', 'supervisor', 'admin', 'superadmin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
            if ($role === 'supervisor') {
                $institution = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
                InstitutionMembership::query()->create([
                    'institution_id' => $institution->id,
                    'user_id' => $user->id,
                    'status' => InstitutionMembershipStatus::Active,
                    'is_default' => true,
                    'provenance' => 'test_fixture',
                    'joined_at' => now(),
                ]);
                $this->grantInstitutionRole($user, InstitutionRole::Instructor, $institution);
            }
            $route = match ($role) {
                'user' => 'dashboard',
                'supervisor' => 'supervisor.dashboard',
                'admin' => 'admin.dashboard',
                'superadmin' => 'superadmin.dashboard',
            };
            $responses[] = $this->actingAs($user)->get(route($route));
        }

        $unverified = User::factory()->create(['role' => 'user', 'email_verified_at' => null]);
        $responses[] = $this->actingAs($unverified)->get(route('verification.notice'));

        foreach ($responses as $response) {
            $response->assertOk();
            $this->assertDoesNotMatchRegularExpression(self::ACTIVE_BRAND_PATTERN, $response->getContent());
            $this->assertStringContainsString('Hospitrainity', $response->getContent());
        }
    }
}
