<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class CurriculumStepPlanner
{
    public const SECTIONS_PER_STEP = 5;

    /**
     * @param  iterable<int, array<string, mixed>>  $sections
     * @return Collection<int, covariant array<string, mixed>>
     */
    public function group(iterable $sections): Collection
    {
        $chunks = collect($sections)->values()->chunk(self::SECTIONS_PER_STEP)->values();
        $total = $chunks->count();

        return $chunks->map(static function (Collection $chunk, int $index) use ($total): array {
            $stepSections = $chunk->values();

            return [
                'number' => $index + 1,
                'total' => $total,
                'count' => $stepSections->count(),
                'sections' => $stepSections,
                'first' => $stepSections->first(),
                'last' => $stepSections->last(),
            ];
        });
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $sections
     * @return array<string, mixed>|null
     */
    public function context(iterable $sections, string $sectionCode): ?array
    {
        foreach ($this->group($sections) as $step) {
            $position = $step['sections']->search(
                static fn (array $section): bool => $section['code'] === $sectionCode,
            );

            if ($position !== false) {
                return $step + [
                    'position' => $position + 1,
                    'is_first_section' => $position === 0,
                    'is_last_section' => $position === $step['count'] - 1,
                ];
            }
        }

        return null;
    }
}
