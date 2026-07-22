<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

class LangParityTest extends TestCase
{
    /** @var list<string> */
    private const RETIRED_PUBLIC_COPY_KEYS = [
        'Join thousands of learners who are mastering English with our interactive lessons, personalized feedback, and supportive community.',
        'Supportive Community',
        'Connect with fellow learners from around the world, practice together, and stay motivated on your journey.',
        'What Our Learners Say',
        'By creating an account, you agree to our Terms of Service and Privacy Policy.',
    ];

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

    public function test_php_translation_group_keys_match(): void
    {
        $enFiles = collect(glob(lang_path('en/*.php')) ?: [])->map(fn (string $path): string => basename($path))->sort()->values();
        $idFiles = collect(glob(lang_path('id/*.php')) ?: [])->map(fn (string $path): string => basename($path))->sort()->values();

        $this->assertSame($enFiles->all(), $idFiles->all(), 'English and Indonesian PHP translation groups differ.');

        foreach ($enFiles as $file) {
            $en = Arr::dot(require lang_path('en/'.$file));
            $id = Arr::dot(require lang_path('id/'.$file));
            $missingInId = array_values(array_diff(array_keys($en), array_keys($id)));
            $missingInEn = array_values(array_diff(array_keys($id), array_keys($en)));

            $this->assertSame([], $missingInId, "$file keys missing from Indonesian: ".implode(', ', $missingInId));
            $this->assertSame([], $missingInEn, "$file keys missing from English: ".implode(', ', $missingInEn));
        }
    }

    public function test_retired_or_unverified_public_copy_is_not_kept_as_translation_inventory(): void
    {
        foreach (['en.json', 'id.json'] as $file) {
            $translations = json_decode(file_get_contents(lang_path($file)), true);

            $this->assertIsArray($translations);

            foreach (self::RETIRED_PUBLIC_COPY_KEYS as $key) {
                $this->assertArrayNotHasKey($key, $translations, $file);
            }
        }
    }
}
