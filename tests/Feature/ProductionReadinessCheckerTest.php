<?php

namespace Tests\Feature;

use App\Services\ProductionReadinessChecker;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionReadinessCheckerTest extends TestCase
{
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
        File::ensureDirectoryExists($this->releasePath.'/public/build');
        File::ensureDirectoryExists($this->releasePath.'/storage/app/public');
        File::put($this->releasePath.'/public/build/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/app.css'],
            'resources/js/app.js' => ['file' => 'assets/app.js'],
        ], JSON_THROW_ON_ERROR));

        $checks = (new ProductionReadinessChecker(
            $this->releasePath,
            static fn (string $package): bool => false,
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
        File::put($this->releasePath.'/public/hot', 'http://127.0.0.1:5173');
        File::put($this->releasePath.'/public/build/manifest.json', '{}');

        $checks = (new ProductionReadinessChecker(
            $this->releasePath,
            static fn (string $package): bool => $package === 'phpunit/phpunit',
        ))->inspect();
        $failed = collect($checks)->where('passed', false)->pluck('name');

        $this->assertTrue($failed->contains('vite_hot_file'));
        $this->assertTrue($failed->contains('asset_manifest'));
        $this->assertTrue($failed->contains('public_storage_link'));
        $this->assertTrue($failed->contains('dev_dependencies'));
    }

    private function configureProductionRuntime(): void
    {
        $this->app['env'] = 'production';
        config()->set([
            'app.debug' => false,
            'app.url' => 'https://hospitrainity.example',
            'app.key' => 'base64:test-key',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'security.headers_enabled' => true,
            'security.csp.enabled' => true,
            'security.csp.report_only' => false,
            'security.hsts.enabled' => true,
        ]);
    }
}
