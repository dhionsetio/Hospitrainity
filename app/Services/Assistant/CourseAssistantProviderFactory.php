<?php

namespace App\Services\Assistant;

use RuntimeException;

final class CourseAssistantProviderFactory
{
    public function make(): CourseAssistantProvider
    {
        return match (config('course_assistant.provider', 'extractive')) {
            'extractive' => new ExtractiveCourseAssistantProvider,
            'disabled' => new DisabledCourseAssistantProvider,
            default => throw new RuntimeException('The configured course assistant provider is not approved or installed.'),
        };
    }
}
