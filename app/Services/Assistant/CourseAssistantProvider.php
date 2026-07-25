<?php

namespace App\Services\Assistant;

interface CourseAssistantProvider
{
    public function available(): bool;

    /**
     * @param  list<array{title: string, excerpt: string, url: string}>  $sources
     * @return array<string, mixed>|null
     */
    public function answer(string $question, array $sources): ?array;
}
