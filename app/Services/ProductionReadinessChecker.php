<?php

namespace App\Services;

use Closure;
use Composer\InstalledVersions;
use JsonException;

class ProductionReadinessChecker
{
    private string $basePath;

    /** @var Closure(string): bool */
    private Closure $packageInstalled;

    public function __construct(?string $basePath = null, ?Closure $packageInstalled = null)
    {
        $this->basePath = $basePath ?? base_path();
        $this->packageInstalled = $packageInstalled
            ?? static fn (string $package): bool => InstalledVersions::isInstalled($package);
    }

    /**
     * @return list<array{name: string, passed: bool, message: string}>
     */
    public function inspect(): array
    {
        $manifest = $this->inspectManifest();
        $publicStorage = $this->path('public/storage');
        $storageTarget = $this->path('storage/app/public');
        $publicStorageTarget = realpath($publicStorage);
        $expectedStorageTarget = realpath($storageTarget);
        $sameSite = strtolower((string) config('session.same_site'));

        return [
            $this->check('environment', app()->environment('production'), 'APP_ENV must be production.'),
            $this->check('debug', config('app.debug') === false, 'APP_DEBUG must be false.'),
            $this->check('app_url', str_starts_with(strtolower((string) config('app.url')), 'https://'), 'APP_URL must use HTTPS.'),
            $this->check('app_key', trim((string) config('app.key')) !== '', 'APP_KEY must be generated.'),
            $this->check('secure_session_cookie', config('session.secure') === true, 'SESSION_SECURE_COOKIE must be true.'),
            $this->check('http_only_session_cookie', config('session.http_only') === true, 'SESSION_HTTP_ONLY must be true.'),
            $this->check('same_site_session_cookie', in_array($sameSite, ['lax', 'strict'], true), 'SESSION_SAME_SITE must be lax or strict.'),
            $this->check('security_headers', config('security.headers_enabled') === true, 'Security headers must be enabled.'),
            $this->check('csp_enforced', config('security.csp.enabled') === true && config('security.csp.report_only') === false, 'CSP must be enabled and enforcement mode must be active.'),
            $this->check(
                'hsts',
                config('security.hsts.enabled') === true
                    && (int) config('security.hsts.max_age') >= 31_536_000
                    && (! config('security.hsts.preload') || config('security.hsts.include_subdomains')),
                'HSTS must be enabled for at least one year; preload also requires includeSubDomains.',
            ),
            $this->check('vite_hot_file', ! is_file($this->path('public/hot')), 'public/hot must not exist in a production release.'),
            $this->check('asset_manifest', $manifest['passed'], $manifest['message']),
            $this->check(
                'public_storage_link',
                $publicStorageTarget !== false
                    && $expectedStorageTarget !== false
                    && $publicStorageTarget === $expectedStorageTarget,
                'public/storage must resolve to storage/app/public.',
            ),
            $this->check('dev_dependencies', ! $this->hasDevDependencies(), 'Composer development packages must not be installed.'),
        ];
    }

    /** @return array{name: string, passed: bool, message: string} */
    private function check(string $name, bool $passed, string $message): array
    {
        return compact('name', 'passed', 'message');
    }

    /** @return array{passed: bool, message: string} */
    private function inspectManifest(): array
    {
        $path = $this->path('public/build/manifest.json');

        if (! is_file($path)) {
            return ['passed' => false, 'message' => 'public/build/manifest.json is missing.'];
        }

        try {
            $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['passed' => false, 'message' => 'public/build/manifest.json is not valid JSON.'];
        }

        $hasEntries = is_array($manifest)
            && array_key_exists('resources/css/app.css', $manifest)
            && array_key_exists('resources/js/app.js', $manifest);

        return [
            'passed' => $hasEntries,
            'message' => $hasEntries
                ? 'The Vite manifest contains the application CSS and JavaScript entries.'
                : 'The Vite manifest is missing the application CSS or JavaScript entry.',
        ];
    }

    private function hasDevDependencies(): bool
    {
        foreach (['phpunit/phpunit', 'laravel/pint', 'laravel/sail'] as $package) {
            if (($this->packageInstalled)($package)) {
                return true;
            }
        }

        return false;
    }

    private function path(string $path): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
