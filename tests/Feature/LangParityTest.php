<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

class LangParityTest extends TestCase
{
    public function test_en_and_id_translation_keys_match(): void
    {
        $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        $this->assertIsArray($en);
        $this->assertIsArray($id);

        $missingInId = array_values(array_diff(array_keys($en), array_keys($id)));
        $missingInEn = array_values(array_diff(array_keys($id), array_keys($en)));

        $this->assertSame([], $missingInId, 'Keys in en.json missing from id.json: '.implode(', ', $missingInId));
        $this->assertSame([], $missingInEn, 'Keys in id.json missing from en.json: '.implode(', ', $missingInEn));
    }

    public function test_admin_translation_group_keys_match(): void
    {
        $en = Arr::dot(require lang_path('en/admin.php'));
        $id = Arr::dot(require lang_path('id/admin.php'));

        $missingInId = array_values(array_diff(array_keys($en), array_keys($id)));
        $missingInEn = array_values(array_diff(array_keys($id), array_keys($en)));

        $this->assertSame([], $missingInId, 'Admin keys missing from Indonesian: '.implode(', ', $missingInId));
        $this->assertSame([], $missingInEn, 'Admin keys missing from English: '.implode(', ', $missingInEn));
    }
}
