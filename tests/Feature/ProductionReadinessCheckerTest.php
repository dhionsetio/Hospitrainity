<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use App\Services\ProductionReadinessChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionReadinessCheckerTest extends TestCase
{
    use RefreshDatabase;

    private ?string $releasePath = null;

    protected function tearDown(): void
    {
        if ($this->releasePath !== null) {
            File::deleteDirectory($this->releasePath);
        }

        parent::tearDown();
    }

    public function test_checker_accepts_production_settings_and_identifies_only_a_missing_storage_link(): void
    {
        $this->configureProductionRuntime();
        $this->releasePath = storage_path('framework/testing/production-readiness-'.bin2hex(random_bytes(5)));
        $this->writeValidReleaseAssets();

        $checks = (new ProductionReadinessChecker(
            $this->releasePath,
            static fn (string $package): bool => false,
            static fn (): bool => false,
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): bool => true,
        ))->inspect();
        $failed = collect($checks)->where('passed', false)->pluck('name')->all();

        $this->assertSame(['public_storage_link'], $failed);
    }

    public function test_checker_rejects_a_hot_file_invalid_manifest_and_installed_dev_packages(): void
    {
        $this->configureProductionRuntime();
        $this->releasePath = storage_path('framework/testing/production-readiness-'.bin2hex(random_bytes(5)));
        File::ensureDirectoryExists($this->releasePath.'/public/build');
        File::ensureDirectoryExists($this->releasePath.'/storage/app/public');
        File::ensureDirectoryExists($this->releasePath.'/node_modules');
        File::put($this->releasePath.'/public/hot', 'http://127.0.0.1:5173');
        File::put($this->releasePath.'/public/build/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/missing.css'],
            'resources/js/app.js' => ['file' => 'assets/missing.js'],
        ], JSON_THROW_ON_ERROR));

        $checks = (new ProductionReadinessChecker(
            $this->releasePath,
            static fn (string $package): bool => $package === 'fakerphp/faker',
            static fn (): bool => false,
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): bool => true,
        ))->inspect();
        $failed = collect($checks)->where('passed', false)->pluck('name');

        $this->assertTrue($failed->contains('vite_hot_file'));
        $this->assertTrue($failed->contains('asset_manifest'));
        $this->assertTrue($failed->contains('public_storage_link'));
        $this->assertTrue($failed->contains('dev_dependencies'));
        $this->assertTrue($failed->contains('node_dependencies_absent'));
    }

    public function test_checker_rejects_a_non_generated_application_key(): void
    {
        $this->configureProductionRuntime();
        config()->set('app.key', 'base64:not-a-valid-encryption-key');
        $this->releasePath = storage_path('framework/testing/production-readiness-'.bin2hex(random_bytes(5)));
        $this->writeValidReleaseAssets();

        $checks = (new ProductionReadinessChecker(
            $this->releasePath,
            static fn (string $package): bool => false,
            static fn (): bool => false,
            static fn (): bool => true,
            static fn (): bool => true,
            static fn (): bool => true,
        ))->inspect();

        $this->assertContains('app_key', collect($checks)->where('passed', false)->pluck('name')->all());
    }

    public function test_checker_rejects_demo_seeding_demo_identities_and_a_draft_active_package(): void
    {
        $this->configureProductionRuntime();
        config()->set('identity.demo_seed.enabled', true);
        $this->releasePath = storage_path('framework/testing/production-readiness-'.bin2hex(random_bytes(5)));
        $this->writeValidReleaseAssets();

        $checks = (new ProductionReadinessChecker(
            $this->releasePath,
            static fn (string $package): bool => false,
            static fn (): bool => true,
            static fn (): bool => false,
            static fn (): bool => true,
            static fn (): bool => true,
        ))->inspect();
        $failed = collect($checks)->where('passed', false)->pluck('name');

        $this->assertTrue($failed->contains('demo_seed_disabled'));
        $this->assertTrue($failed->contains('demo_identities_absent'));
        $this->assertTrue($failed->contains('active_curriculum_release'));
    }

    public function test_checker_rejects_an_unfinalized_identity_session_migration(): void
    {
        $this->configureProductionRuntime();
        $this->releasePath = storage_path('framework/testing/production-readiness-'.bin2hex(random_bytes(5)));
        $this->writeValidReleaseAssets();

        $checks = (new ProductionReadinessChecker(
            $this->releasePath,
            static fn (string $package): bool => false,
            static fn (): bool => false,
            static fn (): bool => true,
            static fn (): bool => false,
            static fn (): bool => true,
        ))->inspect();

        $this->assertContains(
            'identity_session_migration_finalized',
            collect($checks)->where('passed', false)->pluck('name')->all(),
        );
    }

    public function test_checker_rejects_a_custom_demo_identity_by_fixture_provenance(): void
    {
        $this->configureProductionRuntime();
        $this->releasePath = storage_path('framework/testing/production-readiness-'.bin2hex(random_bytes(5)));
        $this->writeValidReleaseAssets();
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $customDemo = User::factory()->create([
            'email' => 'custom-local-owner@example.test',
            'role' => UserRole::Superadmin,
        ]);
        InstitutionMembership::query()->create([
            'institution_id' => $hq->id,
            'user_id' => $customDemo->id,
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'disposable_demo_fixture',
            'joined_at' => now(),
        ]);

        $checks = (new ProductionReadinessChecker(
            basePath: $this->releasePath,
            packageInstalled: static fn (string $package): bool => false,
            activeCurriculumIsReleaseReady: static fn (): bool => true,
            identityMigrationFinalized: static fn (): bool => true,
            uploadScannerHealthy: static fn (): bool => true,
        ))->inspect();

        $this->assertContains(
            'demo_identities_absent',
            collect($checks)->where('passed', false)->pluck('name')->all(),
        );
    }

    private function configureProductionRuntime(): void
    {
        $this->app['env'] = 'production';
        config()->set([
            'app.debug' => false,
            'app.url' => 'https://hospitrainity.example',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.secure' => true,
            'session.driver' => 'database',
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'security.headers_enabled' => true,
            'security.csp.enabled' => true,
            'security.csp.report_only' => false,
            'security.hsts.enabled' => true,
            'mail.default' => 'smtp',
            'queue.default' => 'database',
            'privacy.operator.production_controller_established' => true,
            'privacy.operator.email' => 'privacy@example.test',
            'push.enabled' => true,
            'push.vapid.subject' => 'mailto:privacy@example.test',
            'push.vapid.public_key' => str_repeat('A', 87),
            'push.vapid.private_key' => str_repeat('B', 43),
            'hashing.driver' => 'argon2id',
            'passkeys.relying_party_id' => 'hospitrainity.example',
            'passkeys.allowed_origins' => ['https://hospitrainity.example'],
            'upload_security.required' => true,
        ]);
    }

    private function writeValidReleaseAssets(): void
    {
        File::ensureDirectoryExists($this->releasePath.'/public/build/assets');
        File::ensureDirectoryExists($this->releasePath.'/storage/app/public');
        File::ensureDirectoryExists($this->releasePath.'/docs/security');
        File::put($this->releasePath.'/docs/security/OWASP-ASVS-5.0.md', '# Test fixture');
        File::put($this->releasePath.'/public/build/assets/app.css', '/* test asset */');
        File::put($this->releasePath.'/public/build/assets/app.js', '/* test asset */');
        File::put($this->releasePath.'/public/build/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/app.css'],
            'resources/js/app.js' => ['file' => 'assets/app.js'],
        ], JSON_THROW_ON_ERROR));
    }
}
