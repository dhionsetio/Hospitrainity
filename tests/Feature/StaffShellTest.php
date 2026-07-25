<?php

namespace Tests\Feature;

use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StaffShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_desktop_navigation_keeps_search_visible_and_secondary_actions_collapsed(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $this->grantInstitutionRole($supervisor, InstitutionRole::Instructor);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        foreach ([
            'supervisor' => [$supervisor, route('supervisor.dashboard')],
            'superadmin' => [$superadmin, route('superadmin.dashboard')],
        ] as $name => [$user, $url]) {
            $response = $this->actingAs($user)->get($url)->assertOk();
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($document);

            $aside = $xpath->query('//aside[contains(concat(" ", normalize-space(@class), " "), " hsp-shell-rail ")]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $aside, "{$name}: the desktop rail must be present.");

            $accountPanel = $xpath->query('//div[@id="hsp-shell-account-panel"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $accountPanel, "{$name}: the account disclosure panel is missing.");
            $this->assertSame(1, $xpath->query('.//nav[@aria-label="Account and preferences"]', $accountPanel)->length);

            $searchUrl = route('search.index');
            $this->assertSame(1, $xpath->query('//a[@href="'.$searchUrl.'"]')->length, "{$name}: Search must remain accessible.");

            foreach ([
                route('onboarding.show'),
                route('preferences.edit'),
                route('security.index'),
                route('work-context.index'),
                route('help.index'),
            ] as $secondaryUrl) {
                $this->assertSame(1, $xpath->query('.//a[@href="'.$secondaryUrl.'"]', $accountPanel)->length, "{$name}: missing secondary action {$secondaryUrl}.");
            }

            $this->assertSame(1, $xpath->query('.//form[@method="POST" and @action="'.route('logout').'"]//button[@type="submit"]', $accountPanel)->length, "{$name}: Logout must remain a POST action.");
        }
    }

    public function test_fixed_height_staff_views_use_normal_document_flow(): void
    {
        foreach ([
            'superadmin/dashboard.blade.php',
            'superadmin/modules/index.blade.php',
            'superadmin/lessons/index.blade.php',
            'superadmin/vocabularies/index.blade.php',
            'superadmin/materials/index.blade.php',
            'superadmin/exercises/index.blade.php',
        ] as $view) {
            $contents = File::get(resource_path('views/'.$view));

            $this->assertStringNotContainsString('md:h-screen', $contents, "{$view} must not constrain the staff shell to one viewport.");
            $this->assertStringNotContainsString('md:overflow-y-auto md:p-10', $contents, "{$view} must use normal page scrolling.");
        }
    }
}
