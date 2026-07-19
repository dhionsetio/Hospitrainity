<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFormStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_error_reopens_modal_with_accessible_summary_and_old_input(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($admin)
            ->from(route('superadmin.modules.index'))
            ->post(route('superadmin.modules.store'), [
                '_form_mode' => 'create',
                'title' => 'Preserved title',
                'description' => '',
                'level' => 'beginner',
            ]);

        $response->assertRedirect(route('superadmin.modules.index'))->assertSessionHasErrors('description');

        $page = $this->get(route('superadmin.modules.index'));
        $page->assertOk()
            ->assertSee('id="admin-form-errors"', false)
            ->assertSee('role="alert"', false);

        $state = $this->adminState($page->getContent());
        $this->assertTrue($state['isModalOpen']);
        $this->assertFalse($state['isEditMode']);
        $this->assertSame('Preserved title', $state['entity']['title']);
    }

    public function test_failed_image_material_edit_restores_database_owned_media_url(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $material = Material::factory()->create(['type' => 'Gambar']);
        $item = MaterialItem::factory()->create([
            'material_id' => $material->id,
            'url' => '/storage/curriculum/materials/images/current.png',
        ]);

        $this->actingAs($admin)
            ->from(route('superadmin.materials.index'))
            ->put(route('superadmin.materials.update', $material), [
                '_form_mode' => 'edit',
                '_record_id' => $material->id,
                'lesson_id' => $material->lesson_id,
                'type' => 'Gambar',
                'items' => [['id' => $item->id, 'title' => 'Kept', 'description' => '']],
            ])->assertSessionHasErrors('items.0.description');

        $page = $this->get(route('superadmin.materials.index'));
        $page->assertOk()
            ->assertSee('id="admin-form-errors"', false);

        $state = $this->adminState($page->getContent());
        $this->assertTrue($state['isModalOpen']);
        $this->assertTrue($state['isEditMode']);
        $this->assertSame('/storage/curriculum/materials/images/current.png', $state['entity']['items'][0]['url']);
    }

    /** @return array<string, mixed> */
    private function adminState(string $html): array
    {
        $matched = preg_match('/data-admin-state="([^"]+)"/', $html, $matches);

        $this->assertSame(1, $matched, 'The page did not include encoded admin form state.');

        return json_decode(
            (string) base64_decode(html_entity_decode($matches[1]), true),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
