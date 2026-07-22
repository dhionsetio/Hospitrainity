<?php

namespace App\Services\Assistant;

use App\Models\CurriculumEntity;
use App\Models\User;
use App\Services\LearningContentScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CurriculumSourceSearch
{
    /** @var list<string> */
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'can', 'do', 'for', 'from', 'how', 'i', 'in', 'is',
        'it', 'me', 'my', 'of', 'on', 'or', 'please', 'tell', 'that', 'the', 'this', 'to', 'what', 'when',
        'where', 'which', 'who', 'why', 'with', 'you', 'your',
        'apa', 'bagaimana', 'dan', 'dari', 'di', 'ini', 'itu', 'ke', 'saya', 'tolong', 'untuk', 'yang',
    ];

    public function __construct(private readonly LearningContentScope $contentScope) {}

    /** @return list<array{title: string, excerpt: string, url: string}> */
    public function search(User $user, string $question): array
    {
        $scope = $this->contentScope->current(request(), $user);
        $package = $scope['package'];
        if ($package === null) {
            return [];
        }

        $terms = $this->terms($question);
        if ($terms === []) {
            return [];
        }

        $entities = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->whereIn('entity_type', ['chapter', 'lesson-section', 'activity', 'prompt-item'])
            ->published()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $chapters = $entities->where('entity_type', 'chapter')->keyBy('code');
        $sections = $entities->where('entity_type', 'lesson-section')->keyBy('code');
        $activities = $entities->where('entity_type', 'activity')->keyBy('code');

        return $entities
            ->filter(fn (CurriculumEntity $entity): bool => $this->allowed(
                $entity,
                $scope['allowed_chapter_codes'],
                $sections,
                $activities,
            ))
            ->map(function (CurriculumEntity $entity) use ($terms, $chapters, $sections, $activities): ?array {
                $source = $this->source($entity, $chapters, $sections, $activities, $terms);
                if ($source === null) {
                    return null;
                }

                $titleWords = $this->normalise($source['title']);
                $excerptWords = $this->normalise($source['excerpt']);
                $score = 0;
                foreach ($terms as $term) {
                    if ($this->containsTerm($titleWords, $term)) {
                        $score += 4;
                    }
                    if ($this->containsTerm($excerptWords, $term)) {
                        $score += 1;
                    }
                }
                if ($score === 0) {
                    return null;
                }

                return $source + ['score' => $score, 'tie_breaker' => (int) $entity->getKey()];
            })
            ->filter()
            ->sort(static fn (array $left, array $right): int => [$right['score'], $left['tie_breaker']] <=> [$left['score'], $right['tie_breaker']])
            ->take((int) config('course_assistant.maximum_results', 3))
            ->map(static fn (array $source): array => [
                'title' => $source['title'],
                'excerpt' => $source['excerpt'],
                'url' => $source['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|null  $allowedChapterCodes
     * @param  Collection<string, CurriculumEntity>  $sections
     * @param  Collection<string, CurriculumEntity>  $activities
     */
    private function allowed(
        CurriculumEntity $entity,
        ?array $allowedChapterCodes,
        Collection $sections,
        Collection $activities,
    ): bool {
        if ($allowedChapterCodes === null) {
            return true;
        }

        $chapterCode = '';
        if ($entity->entity_type === 'chapter') {
            $chapterCode = (string) $entity->code;
        } elseif ($entity->entity_type === 'lesson-section') {
            $chapterCode = (string) $entity->parent_code;
        } elseif ($entity->entity_type === 'activity') {
            $section = $sections->get($entity->parent_code);
            $chapterCode = $section instanceof CurriculumEntity ? (string) $section->parent_code : '';
        } elseif ($entity->entity_type === 'prompt-item') {
            $activity = $activities->get($entity->parent_code);
            $section = $activity instanceof CurriculumEntity ? $sections->get($activity->parent_code) : null;
            $chapterCode = $section instanceof CurriculumEntity ? (string) $section->parent_code : '';
        }

        return in_array($chapterCode, $allowedChapterCodes, true);
    }

    /**
     * @param  Collection<string, CurriculumEntity>  $chapters
     * @param  Collection<string, CurriculumEntity>  $sections
     * @param  Collection<string, CurriculumEntity>  $activities
     * @param  list<string>  $terms
     * @return array{title: string, excerpt: string, url: string}|null
     */
    private function source(
        CurriculumEntity $entity,
        Collection $chapters,
        Collection $sections,
        Collection $activities,
        array $terms,
    ): ?array {
        $payload = $entity->payloadData();
        $passages = match ($entity->entity_type) {
            'chapter' => [(string) ($payload['title'] ?? '')],
            'lesson-section' => $this->sectionPassages($payload),
            'activity' => array_values(array_filter([
                (string) ($payload['title'] ?? ''),
                is_string($payload['guidance'] ?? null) ? $payload['guidance'] : '',
            ])),
            'prompt-item' => [is_string($payload['stem'] ?? null) ? $payload['stem'] : ''],
            default => [],
        };
        $excerpt = $this->bestPassage($passages, $terms);
        if ($excerpt === '') {
            return null;
        }

        if ($entity->entity_type === 'chapter') {
            $title = (string) ($payload['title'] ?? '');
            $url = route('curriculum.chapters.show', $entity->code);
        } elseif ($entity->entity_type === 'lesson-section') {
            $title = (string) ($payload['title'] ?? '');
            $url = route('curriculum.sections.show', $entity->code);
        } else {
            $activity = $entity->entity_type === 'activity' ? $entity : $activities->get($entity->parent_code);
            if (! $activity instanceof CurriculumEntity) {
                return null;
            }
            $title = (string) ($activity->payloadData()['title'] ?? '');
            $url = route('curriculum.activities.show', $activity->code);
        }

        if ($title === '') {
            $section = $entity->entity_type === 'activity'
                ? $sections->get($entity->parent_code)
                : null;
            $chapter = $section instanceof CurriculumEntity ? $chapters->get($section->parent_code) : null;
            $title = (string) ($chapter?->payloadData()['title'] ?? 'Course material');
        }

        return [
            'title' => $title,
            'excerpt' => Str::limit($excerpt, (int) config('course_assistant.maximum_excerpt_characters', 360)),
            'url' => $url,
        ];
    }

    /** @param array<string, mixed> $payload @return list<string> */
    private function sectionPassages(array $payload): array
    {
        $passages = [];
        if (is_string($payload['title'] ?? null)) {
            $passages[] = $payload['title'];
        }
        foreach ($payload['blocks'] ?? [] as $block) {
            if (! is_array($block)) {
                continue;
            }
            foreach (['text', 'caption'] as $field) {
                if (is_string($block[$field] ?? null)) {
                    $passages[] = $block[$field];
                }
            }
            if (is_array($block['header'] ?? null)) {
                $passages[] = implode(' | ', array_filter($block['header'], 'is_string'));
            }
            foreach ($block['rows'] ?? [] as $row) {
                if (is_array($row)) {
                    $passages[] = implode(' | ', array_filter($row, 'is_string'));
                }
            }
        }

        return array_values(array_filter($passages, static fn (string $passage): bool => trim($passage) !== ''));
    }

    /** @param list<string> $passages @param list<string> $terms */
    private function bestPassage(array $passages, array $terms): string
    {
        $best = '';
        $bestScore = 0;
        foreach ($passages as $passage) {
            $normalised = $this->normalise($passage);
            $score = count(array_filter($terms, fn (string $term): bool => $this->containsTerm($normalised, $term)));
            if ($score > $bestScore) {
                $best = $passage;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** @return list<string> */
    private function terms(string $question): array
    {
        $parts = preg_split('/[^\pL\pN]+/u', $this->normalise($question), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = array_values(array_unique(array_filter(
            $parts,
            static fn (string $term): bool => mb_strlen($term) >= 2 && ! in_array($term, self::STOP_WORDS, true),
        )));

        return array_slice($terms, 0, 12);
    }

    private function normalise(string $text): string
    {
        return mb_strtolower(Str::squish($text));
    }

    private function containsTerm(string $text, string $term): bool
    {
        return preg_match('/(?<![\pL\pN])'.preg_quote($term, '/').'(?![\pL\pN])/u', $text) === 1;
    }
}
