<?php

use App\Http\Controllers\Admin\ProgressAggregateController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordConfirmationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CanonicalAttemptController;
use App\Http\Controllers\CanonicalCurriculumAssetController;
use App\Http\Controllers\CanonicalCurriculumController;
use App\Http\Controllers\CurriculumAssetController;
use App\Http\Controllers\CurriculumDraftController;
use App\Http\Controllers\CurriculumDraftExerciseController;
use App\Http\Controllers\CurriculumDraftPreviewController;
use App\Http\Controllers\CurriculumImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\LegacyEvidenceController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\Superadmin\AdminDashboardController;
use App\Http\Controllers\Superadmin\AdministrationAuditController;
use App\Http\Controllers\Superadmin\LearnerProgressController as SuperadminLearnerProgressController;
use App\Http\Controllers\Superadmin\UserAdministrationController;
use App\Http\Controllers\Supervisor\LearnerProgressController as SupervisorLearnerProgressController;
use App\Http\Controllers\Supervisor\SpvDashboardController;
use App\Http\Controllers\VocabularyController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

$curriculumDraftRoutes = static function (bool $publicationAuthority): void {
    Route::get('/curriculum-drafts', [CurriculumDraftController::class, 'index'])->name('curriculum-drafts.index');
    Route::post('/curriculum-drafts', [CurriculumDraftController::class, 'store'])->name('curriculum-drafts.store');
    Route::get('/curriculum-drafts/{curriculumDraft}', [CurriculumDraftController::class, 'show'])->name('curriculum-drafts.show');
    Route::post('/curriculum-drafts/{curriculumDraft}/imports', [CurriculumImportController::class, 'store'])
        ->middleware('throttle:10,1')->name('curriculum-drafts.imports.store');
    Route::post('/curriculum-drafts/{curriculumDraft}/imports/{curriculumImport}/accept', [CurriculumImportController::class, 'accept'])
        ->name('curriculum-drafts.imports.accept');
    Route::post('/curriculum-drafts/{curriculumDraft}/assets', [CurriculumAssetController::class, 'store'])
        ->middleware('throttle:20,1')->name('curriculum-drafts.assets.store');
    Route::get('/curriculum-drafts/{curriculumDraft}/assets/{curriculumAsset}', [CurriculumAssetController::class, 'show'])
        ->name('curriculum-drafts.assets.show');

    Route::get('/curriculum-drafts/{curriculumDraft}/exercises', [CurriculumDraftExerciseController::class, 'index'])
        ->name('curriculum-drafts.exercises.index');
    Route::get('/curriculum-drafts/{curriculumDraft}/exercises/create', [CurriculumDraftExerciseController::class, 'create'])
        ->name('curriculum-drafts.exercises.create');
    Route::post('/curriculum-drafts/{curriculumDraft}/exercises', [CurriculumDraftExerciseController::class, 'store'])
        ->name('curriculum-drafts.exercises.store');
    Route::get('/curriculum-drafts/{curriculumDraft}/exercises/{draftExercise}/edit', [CurriculumDraftExerciseController::class, 'edit'])
        ->name('curriculum-drafts.exercises.edit');
    Route::patch('/curriculum-drafts/{curriculumDraft}/exercises/{draftExercise}', [CurriculumDraftExerciseController::class, 'update'])
        ->name('curriculum-drafts.exercises.update');
    Route::post('/curriculum-drafts/{curriculumDraft}/exercises/{draftExercise}/duplicate', [CurriculumDraftExerciseController::class, 'duplicate'])
        ->name('curriculum-drafts.exercises.duplicate');

    Route::post('/curriculum-drafts/{curriculumDraft}/entities', [CurriculumDraftController::class, 'storeEntity'])->name('curriculum-drafts.entities.store');
    Route::get('/curriculum-drafts/{curriculumDraft}/entities/{draftEntity}', [CurriculumDraftController::class, 'editEntity'])->name('curriculum-drafts.entities.edit');
    Route::patch('/curriculum-drafts/{curriculumDraft}/entities/{draftEntity}', [CurriculumDraftController::class, 'updateEntity'])->name('curriculum-drafts.entities.update');
    Route::post('/curriculum-drafts/{curriculumDraft}/entities/{draftEntity}/move', [CurriculumDraftController::class, 'moveEntity'])->name('curriculum-drafts.entities.move');
    Route::post('/curriculum-drafts/{curriculumDraft}/entities/{draftEntity}/archive', [CurriculumDraftController::class, 'archiveEntity'])->name('curriculum-drafts.entities.archive');
    Route::post('/curriculum-drafts/{curriculumDraft}/entities/{draftEntity}/restore', [CurriculumDraftController::class, 'restoreEntity'])->name('curriculum-drafts.entities.restore');
    Route::post('/curriculum-drafts/{curriculumDraft}/entities/{draftEntity}/blocks', [CurriculumDraftController::class, 'storeBlock'])->name('curriculum-drafts.blocks.store');
    Route::patch('/curriculum-drafts/{curriculumDraft}/blocks/{draftBlock}', [CurriculumDraftController::class, 'updateBlock'])->name('curriculum-drafts.blocks.update');
    Route::post('/curriculum-drafts/{curriculumDraft}/blocks/{draftBlock}/move', [CurriculumDraftController::class, 'moveBlock'])->name('curriculum-drafts.blocks.move');
    Route::post('/curriculum-drafts/{curriculumDraft}/blocks/{draftBlock}/archive', [CurriculumDraftController::class, 'archiveBlock'])->name('curriculum-drafts.blocks.archive');
    Route::post('/curriculum-drafts/{curriculumDraft}/blocks/{draftBlock}/restore', [CurriculumDraftController::class, 'restoreBlock'])->name('curriculum-drafts.blocks.restore');

    Route::post('/curriculum-drafts/{curriculumDraft}/validate', [CurriculumDraftController::class, 'validateDraft'])->name('curriculum-drafts.validate');
    Route::post('/curriculum-drafts/{curriculumDraft}/request-changes', [CurriculumDraftController::class, 'requestChanges'])->name('curriculum-drafts.request-changes');

    Route::get('/curriculum-drafts/{curriculumDraft}/preview', [CurriculumDraftPreviewController::class, 'index'])->name('curriculum-drafts.preview.index');
    Route::get('/curriculum-drafts/{curriculumDraft}/preview/chapters/{chapter}', [CurriculumDraftPreviewController::class, 'chapter'])->name('curriculum-drafts.preview.chapters.show');
    Route::get('/curriculum-drafts/{curriculumDraft}/preview/sections/{section}', [CurriculumDraftPreviewController::class, 'section'])->name('curriculum-drafts.preview.sections.show');
    Route::get('/curriculum-drafts/{curriculumDraft}/preview/activities/{activity}', [CurriculumDraftPreviewController::class, 'activity'])->name('curriculum-drafts.preview.activities.show');
    Route::post('/curriculum-drafts/{curriculumDraft}/preview/activities/{activity}/attempt', [CurriculumDraftPreviewController::class, 'attempt'])
        ->middleware('throttle:curriculum-attempt')->name('curriculum-drafts.preview.activities.attempt');

    if ($publicationAuthority) {
        Route::post('/curriculum-drafts/{curriculumDraft}/approve', [CurriculumDraftController::class, 'approve'])->name('curriculum-drafts.approve');
        Route::middleware(['password.confirm', 'throttle:curriculum-publication'])->group(function (): void {
            Route::post('/curriculum-drafts/{curriculumDraft}/publish', [CurriculumDraftController::class, 'publish'])->name('curriculum-drafts.publish');
            Route::post('/curriculum-drafts/{curriculumDraft}/rollback', [CurriculumDraftController::class, 'rollback'])->name('curriculum-drafts.rollback');
        });
    }
};

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Locale switching (session-driven; honoured by App\Http\Middleware\SetLocale)
|--------------------------------------------------------------------------
*/
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Guest-only authentication routes (login, register, password reset)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:registration');

    // Password reset via Laravel's native password broker.
    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:password-email')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Email verification routes (authenticated, not necessarily verified)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Recent-password confirmation for privileged account administration
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
    Route::get('/confirm-password', [PasswordConfirmationController::class, 'show'])
        ->name('password.confirm');
    Route::post('/confirm-password', [PasswordConfirmationController::class, 'store'])
        ->middleware('throttle:password-confirm')
        ->name('password.confirm.store');
});

/*
|--------------------------------------------------------------------------
| Learner experience (requires an authenticated AND verified account)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:user'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/curriculum/activities/{activity}', [CanonicalCurriculumController::class, 'activity'])
        ->name('curriculum.activities.show');
    Route::post('/curriculum/activities/{activity}/attempts', [CanonicalAttemptController::class, 'store'])
        ->middleware('throttle:curriculum-attempt')
        ->name('curriculum.activities.attempts.store');
    Route::get('/curriculum/confidence-history', [CanonicalCurriculumController::class, 'confidence'])
        ->name('curriculum.confidence-history');
    Route::get('/curriculum/sections/{section}', [CanonicalCurriculumController::class, 'section'])
        ->name('curriculum.sections.show');
    Route::get('/curriculum/assets/{sha256}/{extension}', [CanonicalCurriculumAssetController::class, 'show'])
        ->where(['sha256' => '[0-9a-f]{64}', 'extension' => 'jpg|jpeg|png|webp|mp3|wav'])
        ->name('curriculum.assets.show');
    Route::get('/curriculum/{chapter}', [CanonicalCurriculumController::class, 'chapter'])
        ->name('curriculum.chapters.show');

    Route::get('/modules/{module:slug}', [ModuleController::class, 'show'])
        ->can('view', 'module')
        ->name('modules.show');

    Route::get('/lessons/{lesson:slug}', [LessonController::class, 'show'])
        ->can('view', 'lesson')
        ->name('lessons.show');

    // NOTE (Phase 2 / CP 2.1): the former top-level GET /lessons/{material:slug}
    // route (name: materials.show) was REMOVED. It was unreachable (shadowed by
    // lessons.show above), pointed at a non-existent MaterialController@show, and
    // relied on a `slug` column that Material does not have. Materials are served
    // via lessons.material.show (nested under a lesson).

    Route::post('/progress/store', [ProgressController::class, 'store'])->name('progress.store');

    Route::get('/lessons/{lesson:slug}/practice/{vocabulary}', [LessonController::class, 'practice'])
        ->scopeBindings()
        ->can('view', 'vocabulary')
        ->name('lessons.practice');

    Route::get('/lessons/{lesson:slug}/materials/{material}', [LessonController::class, 'material'])
        ->scopeBindings()
        ->can('view', 'material')
        ->name('lessons.material.show');

    Route::get('/lessons/{lesson:slug}/exercise', [LessonController::class, 'practiceExercises'])
        ->can('view', 'lesson')
        ->name('lessons.exercise.practice');
});

/*
|--------------------------------------------------------------------------
| Role-scoped dashboards
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:supervisor'])->prefix('supervisor')->name('supervisor.')->group(function () {
    Route::get('/dashboard', [SpvDashboardController::class, 'index'])->name('dashboard');
    Route::get('/progress/learners/{learner}', [SupervisorLearnerProgressController::class, 'show'])
        ->name('progress.learners.show');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () use ($curriculumDraftRoutes) {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/curriculum-exercises', [CurriculumDraftExerciseController::class, 'overview'])->name('curriculum-exercises.index');
    Route::get('/progress', [ProgressAggregateController::class, 'index'])->name('progress.index');
    Route::get('/legacy-evidence', [LegacyEvidenceController::class, 'index'])->name('legacy-evidence.index');

    // ADM-1: content admins can inspect retained evidence but cannot mutate it.
    Route::resource('modules', ModuleController::class)->only('index');
    Route::resource('lessons', LessonController::class)->only('index');
    Route::resource('vocabularies', VocabularyController::class)->only('index');
    Route::resource('materials', MaterialController::class)->only('index');
    Route::resource('exercises', ExerciseController::class)->only('index');
    $curriculumDraftRoutes(false);
});

Route::middleware(['auth', 'verified', 'role:superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () use ($curriculumDraftRoutes) {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/curriculum-exercises', [CurriculumDraftExerciseController::class, 'overview'])->name('curriculum-exercises.index');
    Route::get('/progress', [SuperadminLearnerProgressController::class, 'index'])->name('progress.index');
    Route::get('/progress/learners/{learner}', [SuperadminLearnerProgressController::class, 'show'])
        ->name('progress.learners.show');
    Route::get('/legacy-evidence', [LegacyEvidenceController::class, 'index'])->name('legacy-evidence.index');

    Route::resource('modules', ModuleController::class)->only('index');
    Route::resource('lessons', LessonController::class)->only('index');
    Route::resource('vocabularies', VocabularyController::class)->only('index');
    Route::resource('materials', MaterialController::class)->only('index');
    Route::resource('exercises', ExerciseController::class)->only('index');
    $curriculumDraftRoutes(true);

    Route::middleware('password.confirm')->group(function () {
        Route::get('/audit', [AdministrationAuditController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('audit.index');
        Route::get('/users', [UserAdministrationController::class, 'index'])
            ->name('users.index');
        Route::patch('/users/{user}/role', [UserAdministrationController::class, 'updateRole'])
            ->middleware('throttle:role-change')
            ->name('users.role.update');
        Route::patch('/users/{user}/promote-superadmin', [UserAdministrationController::class, 'promoteSuperadmin'])
            ->middleware('throttle:superadmin-promotion')
            ->name('users.promote-superadmin');
    });

    Route::middleware('legacy.curriculum.writable')->group(function () {
        Route::resource('modules', ModuleController::class)->only(['store', 'update', 'destroy']);
        Route::resource('lessons', LessonController::class)->only(['store', 'update', 'destroy']);
        Route::resource('vocabularies', VocabularyController::class)->only(['store', 'update', 'destroy']);
        Route::resource('materials', MaterialController::class)->only(['store', 'update', 'destroy']);
        Route::resource('exercises', ExerciseController::class)->only(['store', 'update', 'destroy']);
    });
});
