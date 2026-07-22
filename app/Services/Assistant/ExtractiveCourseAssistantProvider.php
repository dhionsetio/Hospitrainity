<?php

namespace App\Services\Assistant;

final class ExtractiveCourseAssistantProvider implements CourseAssistantProvider
{
    public function available(): bool
    {
        return true;
    }

    public function answer(string $question, array $sources): ?array
    {
        $first = $sources[0] ?? null;
        if ($first === null) {
            return null;
        }

        return [
            'answer' => trim($first['excerpt']),
            'cited_source_indexes' => [0],
        ];
    }
}
