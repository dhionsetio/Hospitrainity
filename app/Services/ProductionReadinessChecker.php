<?php

namespace App\Services;

use App\Models\CurriculumPackage;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use Closure;
use Composer\InstalledVersions;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Monolog\Formatter\JsonFormatter;
use Throwable;

class ProductionReadinessChecker
{
    private string $basePath;

    /** @var Closure(string): bool */
    private Closure $packageInstalled;

    /** @var Closure(): bool */
    private Closure $demoIdentityExists;

    /** @var Closure(): bool */
    private Closure $activeCurriculumIsReleaseReady;

    /** @var Closure(): bool */
    private Closure $identityMigrationFinalized;

    /** @var Closure(): bool */
    private Closure $uploadScannerHealthy;

    /** @var Closure(): bool */
    private Closure $activeSearchIndexReady;

    public function __construct(
        ?string $basePath = null,
        ?Closure $packageInstalled = null,
        ?Closure $demoIdentityExists = null,
        ?Closure $activeCurriculumIsReleaseReady = null,
        ?Closure $identityMigrationFinalized = null,
        ?Closure $uploadScannerHealthy = null,
        ?Closure $activeSearchIndexReady = null,
    ) {
        $this->basePath = $basePath ?? base_path();
        $this->packageInstalled = $packageInstalled
            ?? static fn (string $package): bool => InstalledVersions::isInstalled($package);
        $this->demoIdentityExists = $demoIdentityExists ?? static function (): bool {
            try {
                return ! Schema::hasTable('users')
                    || ! Schema::hasTable('institutions')
                    || ! Schema::hasTable('institution_memberships')
                    || User::query()->whereIn('email', config('identity.known_demo_emails', []))->exists()
                    || Institution::query()->whereIn('key', config('identity.known_demo_institution_keys', []))->exists()
                    || Institution::query()->where('verification_method', 'disposable_demo_fixture')->exists()
                    || InstitutionMembership::query()->where('provenance', 'disposable_demo_fixture')->exists();
            } catch (Throwable) {
                return true;
            }
        };
        $this->activeCurriculumIsReleaseReady = $activeCurriculumIsReleaseReady ?? static function (): bool {
            try {
                if (! Schema::hasTable('curriculum_packages')) {
                    return false;
                }

                $active = CurriculumPackage::active();

                return $active !== null && strtolower(trim((string) $active->lifecycle_status)) !== 'draft';
            } catch (Throwable) {
                return false;
            }
        };
        $this->identityMigrationFinalized = $identityMigrationFinalized ?? static function (): bool {
            try {
                return Schema::hasTable('identity_migration_states')
                    && DB::table('identity_migration_states')
                        ->where('name', 'normalized-institutions-session-revocation-v1')
                        ->whereNotNull('completed_at')
                        ->exists();
            } catch (Throwable) {
                return false;
            }
        };
        $this->uploadScannerHealthy = $uploadScannerHealthy
            ?? static fn (): bool => app(UploadSecurityService::class)->healthy();
        $this->activeSearchIndexReady = $activeSearchIndexReady ?? static function (): bool {
            try {
                return Schema::hasTable('search_index_generations')
                    && Schema::hasTable('search_documents')
                    && Schema::hasTable('search_document_terms')
                    && DB::table('search_index_generations')
                        ->where('is_active', true)
                        ->where('document_count', '>', 0)
                        ->count() === 1;
            } catch (Throwable) {
                return false;
            }
        };
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
            $this->check('app_key', $this->appKeyIsValid(), 'APP_KEY must be a valid generated key for APP_CIPHER.'),
            $this->check('secure_session_cookie', config('session.secure') === true, 'SESSION_SECURE_COOKIE must be true.'),
            $this->check('revocable_session_store', config('session.driver') === 'database', 'SESSION_DRIVER must be database so account and membership changes can revoke sessions.'),
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
            $this->check('node_dependencies_absent', ! is_dir($this->path('node_modules')), 'node_modules must not be present in the PHP production release.'),
            $this->check(
                'production_mail_transport',
                ! in_array(strtolower((string) config('mail.default')), ['', 'array', 'log'], true),
                'MAIL_MAILER must use a real production transport, not log or array.',
            ),
            $this->check(
                'demo_seed_disabled',
                config('identity.demo_seed.enabled') === false,
                'HOSPITRAINITY_DEMO_SEED must be false in production.',
            ),
            $this->check(
                'demo_identities_absent',
                ! ($this->demoIdentityExists)(),
                'Known disposable demo identities or institutions must not exist in production.',
            ),
            $this->check(
                'disabled_account_lifecycle',
                Schema::hasColumn('users', 'disabled_at')
                    && Schema::hasColumn('users', 'disabled_by_user_id')
                    && Schema::hasColumn('users', 'disabled_reason_code'),
                'The auditable disabled-account lifecycle schema must be installed.',
            ),
            $this->check(
                'tenant_role_and_learning_scope_schema',
                Schema::hasTable('platform_role_assignments')
                    && Schema::hasTable('institution_role_assignments')
                    && Schema::hasTable('user_capability_assignments')
                    && Schema::hasTable('institution_join_codes')
                    && Schema::hasTable('institution_join_requests')
                    && Schema::hasColumn('curriculum_activity_progress', 'learning_scope_key')
                    && Schema::hasColumn('curriculum_activity_progress', 'institution_membership_id')
                    && Schema::hasColumn('curriculum_attempts', 'learning_scope_key')
                    && Schema::hasColumn('completions', 'learning_scope_key'),
                'Normalized tenant roles, classroom-code requests, and separated learning-scope schema must be installed.',
            ),
            $this->check(
                'privacy_lifecycle_schema',
                Schema::hasTable('policy_acknowledgements')
                    && Schema::hasTable('data_subject_requests')
                    && Schema::hasTable('data_subject_request_events')
                    && Schema::hasTable('data_exports')
                    && Schema::hasTable('account_erasure_steps')
                    && Schema::hasTable('push_subscriptions'),
                'The versioned policy, rights-request, export, erasure, and push-subscription schema must be installed.',
            ),
            $this->check(
                'privacy_controller_established',
                config('privacy.operator.production_controller_established') === true
                    && filter_var(config('privacy.operator.email'), FILTER_VALIDATE_EMAIL) !== false,
                'A reviewed production controller/operator and privacy contact must be explicitly configured.',
            ),
            $this->check(
                'privacy_async_queue',
                ! in_array(strtolower((string) config('queue.default')), ['', 'sync'], true),
                'Privacy exports, erasure, and push delivery require an asynchronous production queue.',
            ),
            $this->check(
                'push_notifications_configured',
                app(PushNotificationService::class)->configured(),
                'Approved Web Push requires enabled VAPID configuration with non-empty public/private keys and a valid subject.',
            ),
            $this->check(
                'security_assurance_schema',
                Schema::hasTable('passkeys')
                    && Schema::hasTable('mfa_recovery_codes')
                    && Schema::hasTable('security_events')
                    && Schema::hasTable('upload_security_records')
                    && Schema::hasColumn('users', 'two_factor_secret')
                    && Schema::hasColumn('users', 'two_factor_confirmed_at'),
                'Passkey, hashed recovery-code, security-event, and upload-security schema must be installed.',
            ),
            $this->check(
                'argon2id_password_hashing',
                config('hashing.driver') === 'argon2id',
                'HASH_DRIVER must be argon2id; Laravel retains verification and opportunistic rehash compatibility for existing bcrypt credentials.',
            ),
            $this->check(
                'privileged_mfa_enrolled',
                $this->allPrivilegedAccountsHaveMfa(),
                'Every enabled privileged account must have a passkey or confirmed TOTP before production.',
            ),
            $this->check(
                'passkey_origin_bound',
                str_starts_with(strtolower((string) config('app.url')), 'https://')
                    && config('passkeys.relying_party_id') === parse_url(config('app.url'), PHP_URL_HOST)
                    && in_array(config('app.url'), config('passkeys.allowed_origins', []), true),
                'Passkeys must be bound to the exact reviewed HTTPS relying-party domain and origin.',
            ),
            $this->check(
                'upload_scanner_required_and_healthy',
                config('upload_security.required') === true && ($this->uploadScannerHealthy)(),
                'Production upload promotion must require a healthy approved malware scanner.',
            ),
            $this->check(
                'structured_security_log',
                config('logging.channels.security.driver') === 'daily'
                    && config('logging.channels.security.formatter') === JsonFormatter::class,
                'Security events must use the bounded JSON security channel; a reviewed production destination remains an operational gate.',
            ),
            $this->check(
                'asvs_traceability',
                is_file($this->path('docs/security/OWASP-ASVS-5.0.md')),
                'The OWASP ASVS 5.0 evidence and explicit-gap matrix must ship with the release.',
            ),
            $this->check(
                'identity_session_migration_finalized',
                ($this->identityMigrationFinalized)(),
                'The explicitly confirmed normalized-identity session revocation must be complete.',
            ),
            $this->check(
                'active_curriculum_release',
                ($this->activeCurriculumIsReleaseReady)(),
                'Exactly one fully approved, checksum-coherent, non-draft curriculum release must be active.',
            ),
            $this->check(
                'active_search_index',
                ($this->activeSearchIndexReady)(),
                'Exactly one non-empty published-content search index generation must be active.',
            ),
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

        $requiredEntries = ['resources/css/app.css', 'resources/js/app.js'];
        $hasEntries = is_array($manifest);
        if ($hasEntries) {
            foreach ($requiredEntries as $source) {
                $entry = $manifest[$source] ?? null;
                $file = is_array($entry) ? ($entry['file'] ?? null) : null;
                if (! is_string($file)
                    || $file === ''
                    || str_starts_with($file, '/')
                    || str_starts_with($file, '\\')
                    || preg_match('/(?:^|[\\\\\/])\.\.(?:[\\\\\/]|$)/', $file) === 1
                    || ! is_file($this->path('public/build/'.str_replace('\\', '/', $file)))) {
                    $hasEntries = false;
                    break;
                }
            }
        }

        return [
            'passed' => $hasEntries,
            'message' => $hasEntries
                ? 'The Vite manifest and referenced application CSS/JavaScript assets are present.'
                : 'The Vite manifest is missing a valid application CSS/JavaScript asset.',
        ];
    }

    private function hasDevDependencies(): bool
    {
        foreach ([
            'fakerphp/faker',
            'laravel/pail',
            'laravel/pint',
            'laravel/sail',
            'mockery/mockery',
            'nunomaduro/collision',
            'phpunit/phpunit',
        ] as $package) {
            if (($this->packageInstalled)($package)) {
                return true;
            }
        }

        return false;
    }

    private function appKeyIsValid(): bool
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (! is_string($decoded)) {
                return false;
            }
            $key = $decoded;
        }

        return Encrypter::supported($key, (string) config('app.cipher'));
    }

    private function allPrivilegedAccountsHaveMfa(): bool
    {
        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('passkeys')) {
                return false;
            }

            return User::query()->whereNull('disabled_at')->get()->every(
                static fn (User $user): bool => ! $user->requiresMfa() || $user->hasStrongMfa(),
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function path(string $path): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
