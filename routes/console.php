<?php

use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use App\Services\ClassWorkspaceService;
use App\Services\CourseEnrollmentService;
use App\Services\CourseOfferingLifecycle;
use App\Services\MfaService;
use App\Services\PrivacyRetentionService;
use App\Services\PublicMediaManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hospitrainity:e2e-prepare-mfa', function () {
    $database = realpath((string) config('database.connections.sqlite.database'));
    $testingRoot = realpath(storage_path('framework/testing'));
    $normalizedDatabase = str_replace('\\', '/', (string) $database);
    $normalizedRoot = rtrim(str_replace('\\', '/', (string) $testingRoot), '/').'/e2e';

    if (! app()->environment('testing')
        || $database === false
        || $testingRoot === false
        || ! str_starts_with(strtolower($normalizedDatabase), strtolower($normalizedRoot))
        || basename($normalizedDatabase) !== 'database.sqlite') {
        $this->error('Refusing to create an MFA fixture outside an isolated E2E testing database.');

        return Command::FAILURE;
    }

    $fixtures = [];
    foreach (['superadmin', 'supervisor', 'admin'] as $account) {
        $user = User::query()->where('email', $account.'@example.com')->firstOrFail();
        $secret = app(MfaService::class)->beginTotp($user);
        $fixtures[$account] = [
            'recoveryCodes' => app(MfaService::class)->confirmTotp(
                $user->fresh(),
                (new Google2FA)->getCurrentOtp($secret),
            ),
        ];
    }

    $this->line(json_encode($fixtures, JSON_THROW_ON_ERROR));

    return Command::SUCCESS;
})->purpose('Create disposable privileged MFA fixtures in an isolated E2E database only');

Artisan::command('hospitrainity:e2e-prepare-class', function () {
    $database = realpath((string) config('database.connections.sqlite.database'));
    $testingRoot = realpath(storage_path('framework/testing'));
    $normalizedDatabase = str_replace('\\', '/', (string) $database);
    $normalizedRoot = rtrim(str_replace('\\', '/', (string) $testingRoot), '/').'/e2e';

    if (! app()->environment('testing')
        || $database === false
        || $testingRoot === false
        || ! str_starts_with(strtolower($normalizedDatabase), strtolower($normalizedRoot))
        || basename($normalizedDatabase) !== 'database.sqlite') {
        $this->error('Refusing to create a Class fixture outside an isolated E2E testing database.');

        return Command::FAILURE;
    }

    $superadmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
    $supervisor = User::query()->where('email', 'supervisor@example.com')->firstOrFail();
    $learner = User::query()->where('email', 'user@example.com')->firstOrFail();
    $supervisorMembership = InstitutionMembership::query()
        ->where('user_id', $supervisor->getKey())
        ->where('status', InstitutionMembershipStatus::Active->value)
        ->firstOrFail();
    $institution = $supervisorMembership->institution()->firstOrFail();
    $learnerMembership = InstitutionMembership::query()->firstOrNew([
        'institution_id' => $institution->getKey(),
        'user_id' => $learner->getKey(),
    ]);
    $learnerMembership->fill([
        'status' => InstitutionMembershipStatus::Active,
        'is_default' => false,
        'revoked_at' => null,
    ]);
    if (! $learnerMembership->exists) {
        $learnerMembership->fill([
            'provenance' => 'disposable_e2e_class_fixture',
            'joined_at' => now(),
        ]);
    }
    $learnerMembership->save();
    InstitutionRoleAssignment::query()->firstOrCreate([
        'institution_membership_id' => $learnerMembership->getKey(),
        'role' => InstitutionRole::Learner->value,
    ], [
        'assigned_by_user_id' => $superadmin->getKey(),
        'assigned_at' => now(),
    ])->forceFill(['revoked_at' => null])->save();

    $deliveryPackage = CurriculumPackage::active();
    if ($deliveryPackage === null) {
        $this->error('The isolated E2E database has no active curriculum package.');

        return Command::FAILURE;
    }
    $deliveryPackage->forceFill(['is_active' => false])->save();
    $fixturePackage = CurriculumPackage::query()->create([
        'package_name' => 'disposable-e2e-class-package',
        'content_version' => '0.0.0-e2e',
        'schema_version' => '2.1.0',
        'namespace_uuid' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        'lifecycle_status' => 'published',
        'source_path' => 'tests/e2e/class-fixture',
        'source_tree_sha256' => hash('sha256', 'disposable-e2e-class-package'),
        'source_file_count' => 2,
        'source_byte_count' => 2,
        'counts' => ['chapters' => 2],
        'projection_meta' => ['fixture' => 'disposable_e2e_class'],
        'laravel_projection_sha256' => hash('sha256', 'disposable-e2e-class-laravel'),
        'standalone_sha256' => hash('sha256', 'disposable-e2e-class-standalone'),
        'is_active' => true,
        'imported_at' => now(),
    ]);
    $moduleIds = [];
    foreach ([1 => 'Guest welcome', 2 => 'Service recovery'] as $position => $title) {
        $moduleIds[] = CurriculumEntity::query()->create([
            'curriculum_package_id' => $fixturePackage->getKey(),
            'entity_uuid' => sprintf('00000000-0000-4000-8000-%012d', $position),
            'code' => 'E2E-CLASS-MODULE-'.$position,
            'entity_type' => 'chapter',
            'position' => $position,
            'lifecycle_status' => 'published',
            'content_version' => '0.0.0-e2e',
            'source_path' => 'tests/e2e/module-'.$position.'.json',
            'source_sha256' => hash('sha256', 'disposable-e2e-class-module-'.$position),
            'payload' => ['module' => $position, 'title' => $title],
        ])->getKey();
    }

    try {
        $offering = app(ClassWorkspaceService::class)->createWithCourse(
            $superadmin,
            $institution,
            $fixturePackage,
            $supervisorMembership,
            'browser-test-course',
            'Browser Test Course',
            'Disposable browser verification fixture.',
            'Browser Test Revision',
            $moduleIds,
            'browser-test-class',
            'Browser Test Class',
            'E2E verification',
            'Asia/Jakarta',
        );
        $offering = app(CourseOfferingLifecycle::class)->transition(
            $superadmin,
            $offering,
            CourseOfferingStatus::Draft,
            CourseOfferingStatus::EnrollmentOpen,
            'Open the disposable Class for browser verification.',
        );
        app(CourseEnrollmentService::class)->enroll(
            $superadmin,
            $offering,
            $learnerMembership,
            'Add the disposable learner to the browser verification roster.',
        );
        $offering = app(CourseOfferingLifecycle::class)->transition(
            $superadmin,
            $offering,
            CourseOfferingStatus::EnrollmentOpen,
            CourseOfferingStatus::Active,
            'Begin the disposable Class for browser verification.',
        );
    } finally {
        // Keep learner delivery on the imported canonical package. The
        // synthetic published package exists only to exercise the immutable
        // Course Revision workflow in this isolated browser-test database.
        $fixturePackage->forceFill(['is_active' => false])->save();
        $deliveryPackage->forceFill(['is_active' => true])->save();
    }

    $this->line(json_encode(['class' => $offering->getKey()], JSON_THROW_ON_ERROR));

    return Command::SUCCESS;
})->purpose('Create a disposable assigned Class in an isolated E2E database only');

Artisan::command('hospitrainity:media-cleanup {--limit=100}', function () {
    $result = app(PublicMediaManager::class)->processPending((int) $this->option('limit'));
    $this->info("Processed {$result['processed']} pending media deletion(s); {$result['remaining']} remain.");

    return $result['remaining'] === 0 ? Command::SUCCESS : Command::FAILURE;
})->purpose('Retry durable public-media cleanup jobs left by committed content changes')
    ->hourly()
    ->withoutOverlapping();

Artisan::command('hospitrainity:storage-health', function () {
    if (! is_dir(public_path('storage'))) {
        $this->error('public/storage is missing. Run: php artisan storage:link');

        return Command::FAILURE;
    }

    $path = '.health/'.Str::uuid().'.txt';
    $disk = Storage::disk('public');

    try {
        $written = $disk->put($path, 'hospitrainity-storage-health');
        if (! $written || ! $disk->exists($path)) {
            $this->error('The public disk write/read probe failed.');

            return Command::FAILURE;
        }

        if (! is_file(public_path('storage/'.$path))) {
            $this->error('The public/storage link does not resolve the stored probe file.');

            return Command::FAILURE;
        }

        $url = PublicMediaManager::urlForPath($path);
        if (! str_starts_with($url, '/storage/')) {
            $this->error('The public media URL contract is invalid.');

            return Command::FAILURE;
        }

        $this->info("Public storage is writable and web-mapped at {$url}.");

        return Command::SUCCESS;
    } catch (Throwable $exception) {
        $this->error('Public storage health check failed: '.$exception->getMessage());

        return Command::FAILURE;
    } finally {
        try {
            $disk->delete($path);
        } catch (Throwable) {
            // The command already reports the primary failure; cleanup can be
            // retried manually if this best-effort probe deletion also fails.
        }
    }
})->purpose('Verify the public storage link and a write/read/delete media probe');

Artisan::command('hospitrainity:privacy-retention {--execute}', function () {
    $result = app(PrivacyRetentionService::class)->run((bool) $this->option('execute'));
    $mode = $result['execute'] ? 'executed' : 'dry-run';
    $this->info("Privacy retention {$mode}: {$result['expired_exports']} expired export(s), {$result['minimized_requests']} request(s) ready for minimization, {$result['removed_subscriptions']} revoked subscription(s).");

    return Command::SUCCESS;
})->purpose('Preview or execute approved privacy retention and artifact expiry');

Schedule::command('hospitrainity:privacy-retention --execute')
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::command('learning:process-pending-media-deletions')
    ->hourly()
    ->withoutOverlapping();
