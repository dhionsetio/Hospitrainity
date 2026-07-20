<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AccessibilityMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_pages_pass_accessibility_markup_contracts(): void
    {
        $learner = User::factory()->create(['role' => 'user']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $admin = User::factory()->create(['role' => 'superadmin']);
        $contentAdmin = User::factory()->create(['role' => 'admin']);
        $this->addMembership($supervisor, Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail());

        $pages = [
            'welcome' => $this->get('/'),
            'login' => $this->get(route('login')),
            'register' => $this->get(route('register')),
            'learner dashboard' => $this->actingAs($learner)->get(route('dashboard')),
            'display preferences' => $this->actingAs($learner)->get(route('preferences.edit')),
            'supervisor dashboard' => $this->actingAs($supervisor)->get(route('supervisor.dashboard')),
            'superadmin learner progress' => $this->actingAs($admin)->get(route('superadmin.progress.index')),
            'superadmin audit' => $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin)->get(route('superadmin.audit.index')),
            'admin aggregate progress' => $this->actingAs($contentAdmin)->get(route('admin.progress.index')),
            'canonical exercise workspaces' => $this->actingAs($contentAdmin)->get(route('admin.curriculum-exercises.index')),
            'legacy evidence overview' => $this->actingAs($contentAdmin)->get(route('admin.legacy-evidence.index')),
            'admin modules' => $this->actingAs($admin)->get(route('superadmin.modules.index')),
            'admin lessons' => $this->actingAs($admin)->get(route('superadmin.lessons.index')),
            'admin vocabulary' => $this->actingAs($admin)->get(route('superadmin.vocabularies.index')),
            'admin materials' => $this->actingAs($admin)->get(route('superadmin.materials.index')),
            'admin exercises' => $this->actingAs($admin)->get(route('superadmin.exercises.index')),
        ];

        foreach ($pages as $name => $response) {
            $this->assertSame(200, $response->getStatusCode(), "{$name}: expected HTTP 200.");
            $this->assertMarkupContracts($name, $response->getContent());
        }
    }

    public function test_lesson_source_has_balanced_container_markup(): void
    {
        $source = file_get_contents(resource_path('views/lesson.blade.php'));

        $this->assertSame(
            preg_match_all('/<div\b/i', $source),
            preg_match_all('/<\/div>/i', $source),
            'lesson.blade.php contains an unmatched div tag.',
        );
    }

    public function test_authentication_pages_have_one_main_landmark_and_primary_heading(): void
    {
        $pages = [
            'login' => $this->get(route('login')),
            'forgot password' => $this->get(route('password.request')),
            'reset password' => $this->get(route('password.reset', [
                'token' => 'accessibility-test-token',
                'email' => 'learner@example.com',
            ])),
        ];

        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $pages['confirm password'] = $this->actingAs($superadmin)->get(route('password.confirm'));

        $unverified = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => null,
        ]);
        $pages['verify email'] = $this->actingAs($unverified)->get(route('verification.notice'));

        foreach ($pages as $name => $response) {
            $response->assertOk();
            [$document, $xpath] = $this->parse($name, $response->getContent());

            $this->assertSame(1, $xpath->query('//main')->length, "{$name}: expected exactly one main landmark.");
            $this->assertSame(1, $xpath->query('//h1')->length, "{$name}: expected exactly one h1.");
            $this->assertReferencedIdsExist($name, $document, $xpath, 'aria-describedby');
        }
    }

    public function test_authentication_errors_are_programmatically_associated_with_fields(): void
    {
        $pages = [
            'login' => [
                $this->withSession(['errors' => $this->errorBag(['email' => 'Invalid credentials.'])])
                    ->get(route('login')),
                ['email-address' => 'email-error'],
            ],
            'forgot password' => [
                $this->withSession(['errors' => $this->errorBag(['email' => 'Enter a valid email address.'])])
                    ->get(route('password.request')),
                ['email' => 'email-error'],
            ],
            'reset password' => [
                $this->withSession(['errors' => $this->errorBag([
                    'email' => 'Enter a valid email address.',
                    'password' => 'Enter a valid password.',
                    'password_confirmation' => 'Confirm the password.',
                ])])->get(route('password.reset', [
                    'token' => 'accessibility-test-token',
                    'email' => 'learner@example.com',
                ])),
                [
                    'email' => 'password-reset-errors',
                    'password' => 'password-reset-errors',
                    'password_confirmation' => 'password-reset-errors',
                ],
            ],
        ];

        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $pages['confirm password'] = [
            $this->actingAs($superadmin)
                ->withSession(['errors' => $this->errorBag(['password' => 'The password is incorrect.'])])
                ->get(route('password.confirm')),
            ['password' => 'password-error'],
        ];

        foreach ($pages as $name => [$response, $associations]) {
            $response->assertOk();
            [$document, $xpath] = $this->parse($name, $response->getContent());

            foreach ($associations as $fieldId => $errorId) {
                $field = $document->getElementById($fieldId);
                $this->assertInstanceOf(DOMElement::class, $field, "{$name}: field #{$fieldId} is missing.");
                $this->assertSame('true', $field->getAttribute('aria-invalid'), "{$name}: #{$fieldId} is not marked invalid.");
                $this->assertContains($errorId, preg_split('/\s+/', trim($field->getAttribute('aria-describedby'))));
                $this->assertInstanceOf(DOMElement::class, $document->getElementById($errorId), "{$name}: error #{$errorId} is missing.");
            }

            $this->assertReferencedIdsExist($name, $document, $xpath, 'aria-describedby');
        }
    }

    public function test_each_desktop_and_mobile_role_navigation_identifies_one_current_destination(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $institution = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $this->addMembership($supervisor, $institution);
        $this->addMembership($admin, $institution);

        $pages = [
            'supervisor dashboard' => $this->actingAs($supervisor)->get(route('supervisor.dashboard')),
            'supervisor invitations' => $this->actingAs($supervisor)->get(route('supervisor.invitations.index')),
            'admin dashboard' => $this->actingAs($admin)->get(route('admin.dashboard')),
            'admin content' => $this->actingAs($admin)->get(route('admin.curriculum-drafts.index')),
            'admin exercises' => $this->actingAs($admin)->get(route('admin.curriculum-exercises.index')),
            'admin progress' => $this->actingAs($admin)->get(route('admin.progress.index')),
            'admin legacy evidence' => $this->actingAs($admin)->get(route('admin.legacy-evidence.index')),
            'superadmin dashboard' => $this->actingAs($superadmin)->get(route('superadmin.dashboard')),
            'superadmin content' => $this->actingAs($superadmin)->get(route('superadmin.curriculum-drafts.index')),
            'superadmin exercises' => $this->actingAs($superadmin)->get(route('superadmin.curriculum-exercises.index')),
            'superadmin progress' => $this->actingAs($superadmin)->get(route('superadmin.progress.index')),
            'superadmin invitations' => $this->actingAs($superadmin)->get(route('superadmin.invitations.index')),
            'superadmin users' => $this->actingAs($superadmin)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->get(route('superadmin.users.index')),
            'superadmin audit' => $this->actingAs($superadmin)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->get(route('superadmin.audit.index')),
            'superadmin legacy evidence' => $this->actingAs($superadmin)->get(route('superadmin.legacy-evidence.index')),
        ];

        foreach ($pages as $name => $response) {
            $this->assertSame(200, $response->getStatusCode(), "{$name}: expected HTTP 200.");
            [, $xpath] = $this->parse($name, $response->getContent());
            $this->assertSame(
                2,
                $xpath->query('//nav//a[@aria-current="page"]')->length,
                "{$name}: expected one current destination in each desktop and mobile role navigation.",
            );
        }
    }

    public function test_selection_controls_cannot_flex_shrink(): void
    {
        $selectionControlCount = 0;

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all(
                '/<input\b(?=[^>]*\btype="(?:radio|checkbox)")[^>]*>/i',
                $file->getContents(),
                $matches,
            );

            foreach ($matches[0] as $selectionControl) {
                $selectionControlCount++;
                $this->assertStringContainsString(
                    'shrink-0',
                    $selectionControl,
                    $file->getRelativePathname().': selection controls must not flex-shrink.',
                );
            }
        }

        $this->assertGreaterThan(0, $selectionControlCount, 'No selection controls were found.');
    }

    private function assertMarkupContracts(string $page, string $html): void
    {
        [$document, $xpath] = $this->parse($page, $html);
        $this->assertNotSame('', trim($xpath->evaluate('string(/html/@lang)')), "{$page}: missing document language.");
        $this->assertNotSame('', trim($xpath->evaluate('string(/html/head/title)')), "{$page}: missing document title.");
        $this->assertSame(1, $xpath->query('//main')->length, "{$page}: expected exactly one main landmark.");
        $this->assertSame(1, $xpath->query('//h1')->length, "{$page}: expected exactly one h1.");
        $this->assertSame(0, $xpath->query('//a[@href="#"]')->length, "{$page}: placeholder link found.");
        $this->assertSame(0, $xpath->query('//img[not(@alt)]')->length, "{$page}: image without alt found.");
        $this->assertSame(1, $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " hsp-skip-link ")][@href="#hsp-page-content"]')->length, "{$page}: expected one skip link.");
        $this->assertInstanceOf(DOMElement::class, $document->getElementById('hsp-page-content'), "{$page}: skip-link target is missing.");
        foreach (['data-theme', 'data-motion', 'data-text-scale', 'data-contrast', 'data-audio'] as $attribute) {
            $this->assertNotSame('', $document->documentElement->getAttribute($attribute), "{$page}: {$attribute} is missing.");
        }

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $this->assertArrayNotHasKey($id, $ids, "{$page}: duplicate id '{$id}'.");
            $ids[$id] = true;
        }

        foreach ($xpath->query('//button') as $button) {
            $name = trim($button->getAttribute('aria-label').' '.$button->getAttribute('title').' '.$button->textContent);
            $this->assertNotSame('', $name, "{$page}: unnamed button found.");
        }

        foreach ($xpath->query('//progress') as $progress) {
            $value = (float) $progress->getAttribute('value');
            $max = (float) $progress->getAttribute('max');
            $this->assertGreaterThan(0, $max, "{$page}: progress max must be positive.");
            $this->assertGreaterThanOrEqual(0, $value, "{$page}: progress value must not be negative.");
            $this->assertLessThanOrEqual($max, $value, "{$page}: progress value exceeds max.");
            $this->assertNotSame('', trim($progress->getAttribute('aria-label')), "{$page}: progress has no accessible name.");
        }

        foreach ($xpath->query('//*[@role="dialog"]') as $dialog) {
            $this->assertSame('true', $dialog->getAttribute('aria-modal'), "{$page}: dialog is not marked modal.");
            $labelId = $dialog->getAttribute('aria-labelledby');
            $this->assertNotSame('', $labelId, "{$page}: dialog has no label reference.");
            $this->assertInstanceOf(DOMElement::class, $document->getElementById($labelId), "{$page}: dialog label target is missing.");
        }

        foreach ($xpath->query('//dialog') as $dialog) {
            $labelId = $dialog->getAttribute('aria-labelledby');
            $this->assertNotSame('', $labelId, "{$page}: native dialog has no label reference.");
            $this->assertInstanceOf(DOMElement::class, $document->getElementById($labelId), "{$page}: native dialog label target is missing.");
            $this->assertSame(1, $xpath->query('.//*[@data-shell-drawer-close]', $dialog)->length, "{$page}: navigation dialog needs one close control.");
        }

        foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
            $this->assertReferencedIdsExist($page, $document, $xpath, $attribute);
        }
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function parse(string $page, string $html): array
    {
        $document = new DOMDocument;
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertTrue($loaded, "{$page}: rendered HTML could not be parsed.");

        return [$document, new DOMXPath($document)];
    }

    private function assertReferencedIdsExist(
        string $page,
        DOMDocument $document,
        DOMXPath $xpath,
        string $attribute,
    ): void {
        foreach ($xpath->query("//*[@{$attribute}]") as $element) {
            $references = preg_split('/\s+/', trim($element->getAttribute($attribute)), flags: PREG_SPLIT_NO_EMPTY);
            $this->assertNotEmpty($references, "{$page}: {$attribute} is empty.");

            foreach ($references as $reference) {
                $this->assertInstanceOf(
                    DOMElement::class,
                    $document->getElementById($reference),
                    "{$page}: {$attribute} references missing #{$reference}.",
                );
            }
        }
    }

    /** @param array<string, string> $errors */
    private function errorBag(array $errors): ViewErrorBag
    {
        return (new ViewErrorBag)->put('default', new MessageBag($errors));
    }

    private function addMembership(User $user, Institution $institution): void
    {
        $membership = InstitutionMembership::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'accessibility_test_fixture',
            'joined_at' => now(),
        ]);
        if ($user->isSupervisor()) {
            InstitutionRoleAssignment::query()->create([
                'institution_membership_id' => $membership->id,
                'role' => InstitutionRole::Instructor,
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ]);
        }
    }
}
