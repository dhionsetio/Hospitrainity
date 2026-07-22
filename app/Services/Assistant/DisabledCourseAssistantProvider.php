<?php

namespace App\Services\Assistant;

final class DisabledCourseAssistantProvider implements CourseAssistantProvider
{
    public function available(): bool
    {
        return false;
    }

    public function answer(string $question, array $sources): ?array
    {
        return null;
    }
}
