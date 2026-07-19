<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_uses_supported_copy_and_current_branding(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Practice Hospitality English at Your Own Pace')
            ->assertSee('Hospitrainity')
            ->assertDontSee('StayReady')
            ->assertDontSee('thousands of learners')
            ->assertDontSee('AI tutors')
            ->assertDontSee('supportive community')
            ->assertDontSee('customized study plan')
            ->assertDontSee('testimonial', false)
            ->assertDontSee('free trial')
            ->assertDontSee('placehold.co', false)
            ->assertDontSee('pravatar.cc', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_blade_sources_have_no_legacy_split_brand_or_placeholder_links(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = $file->getContents();
            $path = $file->getRelativePathname();

            $this->assertDoesNotMatchRegularExpression('/Stay\s*<span[^>]*>Ready<\/span>/i', $source, $path);
            $this->assertStringNotContainsString('href="#"', $source, $path);
            $this->assertStringNotContainsString('>Document<', $source, $path);
        }
    }

    public function test_indonesian_locale_renders_localized_titles_and_admin_copy(): void
    {
        $this->withSession(['locale' => 'id'])
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="id"', false)
            ->assertSee('<title>Hospitrainity - Pelatihan Bahasa Inggris Perhotelan</title>', false)
            ->assertSee('Latih Bahasa Inggris Perhotelan Sesuai Kecepatan Anda');

        $admin = User::factory()->create(['role' => 'superadmin']);
        $this->withSession(['locale' => 'id'])
            ->actingAs($admin)
            ->get(route('superadmin.modules.index'))
            ->assertOk()
            ->assertSee('<title>Kelola Modul - Hospitrainity</title>', false)
            ->assertSee('Manajemen Modul')
            ->assertSee('Buat, edit, dan hapus modul pembelajaran.');
    }
}
