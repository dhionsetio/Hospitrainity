<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $pages = [
            'welcome' => $this->get('/')->getContent(),
            'login' => $this->get(route('login'))->getContent(),
            'register' => $this->get(route('register'))->getContent(),
            'learner dashboard' => $this->actingAs($learner)->get(route('dashboard'))->getContent(),
            'supervisor dashboard' => $this->actingAs($supervisor)->get(route('supervisor.dashboard'))->getContent(),
            'superadmin learner progress' => $this->actingAs($admin)->get(route('superadmin.progress.index'))->getContent(),
            'superadmin audit' => $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin)->get(route('superadmin.audit.index'))->getContent(),
            'admin aggregate progress' => $this->actingAs($contentAdmin)->get(route('admin.progress.index'))->getContent(),
            'canonical exercise workspaces' => $this->actingAs($contentAdmin)->get(route('admin.curriculum-exercises.index'))->getContent(),
            'legacy evidence overview' => $this->actingAs($contentAdmin)->get(route('admin.legacy-evidence.index'))->getContent(),
            'admin modules' => $this->actingAs($admin)->get(route('superadmin.modules.index'))->getContent(),
            'admin lessons' => $this->actingAs($admin)->get(route('superadmin.lessons.index'))->getContent(),
            'admin vocabulary' => $this->actingAs($admin)->get(route('superadmin.vocabularies.index'))->getContent(),
            'admin materials' => $this->actingAs($admin)->get(route('superadmin.materials.index'))->getContent(),
            'admin exercises' => $this->actingAs($admin)->get(route('superadmin.exercises.index'))->getContent(),
        ];

        foreach ($pages as $name => $html) {
            $this->assertMarkupContracts($name, $html);
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

    private function assertMarkupContracts(string $page, string $html): void
    {
        $document = new DOMDocument;
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertTrue($loaded, "{$page}: rendered HTML could not be parsed.");

        $xpath = new DOMXPath($document);
        $this->assertNotSame('', trim($xpath->evaluate('string(/html/@lang)')), "{$page}: missing document language.");
        $this->assertNotSame('', trim($xpath->evaluate('string(/html/head/title)')), "{$page}: missing document title.");
        $this->assertSame(0, $xpath->query('//a[@href="#"]')->length, "{$page}: placeholder link found.");
        $this->assertSame(0, $xpath->query('//img[not(@alt)]')->length, "{$page}: image without alt found.");

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
    }
}
