<?php

namespace App\Services\Assistant;

use App\Models\User;

final class CourseAssistantService
{
    public function __construct(
        private readonly CurriculumSourceSearch $search,
        private readonly CourseAssistantProviderFactory $providers,
    ) {}

    /** @return array{status: string, answer: string|null, sources: list<array{title: string, excerpt: string, url: string}>} */
    public function ask(User $user, string $question): array
    {
        $sources = $this->search->search($user, $question);
        if ($sources === []) {
            return ['status' => 'no_answer', 'answer' => null, 'sources' => []];
        }

        $provider = $this->providers->make();
        if (! $provider->available()) {
            return ['status' => 'sources_found', 'answer' => null, 'sources' => $sources];
        }

        $answer = $provider->answer($question, $sources);
        $answerText = $answer['answer'] ?? null;
        $citedIndexes = $answer['cited_source_indexes'] ?? null;
        if (! is_string($answerText) || trim($answerText) === '' || ! is_array($citedIndexes)) {
            return ['status' => 'sources_found', 'answer' => null, 'sources' => $sources];
        }

        $cited = array_values(array_unique(array_filter(
            $citedIndexes,
            static fn (mixed $index): bool => is_int($index) && array_key_exists($index, $sources),
        )));
        if ($cited === []) {
            return ['status' => 'sources_found', 'answer' => null, 'sources' => $sources];
        }

        return [
            'status' => 'answered',
            'answer' => $answerText,
            'sources' => array_values(array_intersect_key($sources, array_flip($cited))),
        ];
    }
}
