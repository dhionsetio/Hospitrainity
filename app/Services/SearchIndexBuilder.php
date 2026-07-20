<?php

namespace App\Services;

use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\SearchDocument;
use App\Models\SearchDocumentTerm;
use App\Models\SearchIndexGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class SearchIndexBuilder
{
    private const INDEX_VERSION = '1.0.1';

    public function __construct(private readonly HelpContentRegistry $help) {}

    /** @return array{id: string, source_fingerprint: string, document_count: int, changed: bool} */
    public function rebuild(): array
    {
        if (! Schema::hasTable('search_index_generations')) {
            throw new RuntimeException('Search schema is not installed.');
        }

        $fingerprint = $this->sourceFingerprint();
        $active = SearchIndexGeneration::query()->where('is_active', true)->first();
        if ($active !== null && hash_equals($fingerprint, $active->source_fingerprint)) {
            return [
                'id' => (string) $active->getKey(),
                'source_fingerprint' => $fingerprint,
                'document_count' => $active->document_count,
                'changed' => false,
            ];
        }

        $documents = $this->documents();

        return DB::transaction(function () use ($fingerprint, $documents): array {
            $generation = SearchIndexGeneration::query()->create([
                'source_fingerprint' => $fingerprint,
                'document_count' => 0,
                'is_active' => false,
                'built_at' => now(),
            ]);

            foreach ($documents as $document) {
                $searchable = $document['searchable'];
                unset($document['searchable']);
                $row = SearchDocument::query()->create($document + [
                    'search_index_generation_id' => $generation->getKey(),
                    'content_sha256' => hash('sha256', $searchable),
                ]);

                foreach ($this->weightedTerms($document['title'], $document['summary'], $searchable) as $term => $weight) {
                    SearchDocumentTerm::query()->create([
                        'search_document_id' => $row->getKey(),
                        'term' => $term,
                        'weight' => $weight,
                    ]);
                }
            }

            $generation->forceFill(['document_count' => count($documents)])->save();
            SearchIndexGeneration::query()->where('is_active', true)->update(['is_active' => false]);
            $generation->forceFill(['is_active' => true])->save();

            return [
                'id' => (string) $generation->getKey(),
                'source_fingerprint' => $fingerprint,
                'document_count' => count($documents),
                'changed' => true,
            ];
        }, 3);
    }

    /** @return list<string> */
    public function tokens(string $value, int $limit = 12): array
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/u', $normalized, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_unique(array_filter(
            $parts,
            static fn (string $term): bool => mb_strlen($term) >= 2 && mb_strlen($term) <= 64,
        )), 0, max(1, min($limit, 512)));
    }

    /** @return array<string, int> */
    private function weightedTerms(string $title, string $summary, string $searchable): array
    {
        $terms = [];
        foreach ([[$title, 30], [$summary, 20], [$searchable, 10]] as [$text, $weight]) {
            foreach ($this->tokens($text, 512) as $term) {
                $terms[$term] = max($terms[$term] ?? 0, $weight);
            }
        }

        ksort($terms, SORT_STRING);

        return $terms;
    }

    /** @return list<array<string, mixed>> */
    private function documents(): array
    {
        $documents = [];
        $sort = 0;
        foreach (config('help.supported_locales', ['id', 'en']) as $locale) {
            foreach ($this->help->topics($locale) as $topic) {
                $body = collect($topic['sections'])->pluck('body')->flatten()->implode(' ');
                $documents[] = $this->document('help', $topic['slug'], $topic['slug'], $locale, 'public', $topic['title'], $topic['summary'], $topic['title'].' '.$topic['summary'].' '.$body, $sort++);
            }
            foreach ($this->help->glossary($locale) as $entry) {
                $documents[] = $this->document('glossary', $entry['slug'], $entry['slug'], $locale, 'public', $entry['term'], $entry['definition'], $entry['term'].' '.$entry['definition'], $sort++);
            }
        }

        $package = CurriculumPackage::active();
        if ($package === null) {
            return $documents;
        }

        $entities = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->whereIn('entity_type', ['chapter', 'lesson-section', 'activity'])
            ->published()
            ->orderBy('entity_type')
            ->orderByRaw('position is null')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($entities->where('entity_type', 'chapter') as $chapter) {
            $payload = $chapter->payloadData();
            $title = (string) ($payload['title'] ?? $chapter->code);
            $summary = __('Published Module :number', ['number' => (int) ($payload['module'] ?? 0)], locale: 'en');
            $documents[] = $this->document('module', (string) $chapter->code, (string) $chapter->code, 'en', 'authenticated', $title, $summary, $title.' '.$summary, $sort++);
        }

        foreach ($entities->where('entity_type', 'lesson-section') as $section) {
            $payload = $section->payloadData();
            $title = (string) ($payload['title'] ?? $section->code);
            $blockText = $this->blockText($payload['blocks'] ?? []);
            $summary = Str::limit($blockText === '' ? __('Published lesson section', locale: 'en') : $blockText, 240);
            $documents[] = $this->document('section', (string) $section->code, (string) $section->code, 'en', 'authenticated', $title, $summary, $title.' '.$blockText, $sort++);

            foreach ($this->vocabularyRows($payload['blocks'] ?? []) as $index => $row) {
                $term = trim((string) ($row[0] ?? ''));
                if ($term === '') {
                    continue;
                }
                $definition = trim(implode(' · ', array_filter(array_map('strval', array_slice($row, 1)))));
                $documents[] = $this->document('vocabulary', (string) $section->code.':'.$index, (string) $section->code, 'en', 'authenticated', $term, $definition, $term.' '.$definition.' '.$title, $sort++);
            }
        }

        foreach ($entities->where('entity_type', 'activity') as $activity) {
            $payload = $activity->payloadData();
            $title = (string) ($payload['title'] ?? $activity->code);
            $guidance = trim((string) ($payload['guidance'] ?? ''));
            $summary = $guidance === '' ? __('Published learning activity', locale: 'en') : Str::limit($guidance, 240);
            $documents[] = $this->document('activity', (string) $activity->code, (string) $activity->code, 'en', 'authenticated', $title, $summary, $title.' '.$summary, $sort++);
        }

        return $documents;
    }

    /** @return array<string, mixed> */
    private function document(string $type, string $key, string $reference, string $locale, string $audience, string $title, string $summary, string $searchable, int $sort): array
    {
        return [
            'source_type' => $type,
            'source_key' => $key,
            'route_reference' => $reference,
            'locale' => $locale,
            'audience' => $audience,
            'title' => Str::limit(trim($title), 255, ''),
            'summary' => Str::limit(trim($summary), 1000, ''),
            'published' => true,
            'sort_order' => $sort,
            'searchable' => Str::limit(trim($searchable), 10000, ''),
        ];
    }

    private function blockText(mixed $blocks): string
    {
        if (! is_array($blocks)) {
            return '';
        }

        $parts = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            foreach (['text', 'caption'] as $key) {
                if (is_string($block[$key] ?? null)) {
                    $parts[] = $block[$key];
                }
            }
            foreach ($block['runs'] ?? [] as $run) {
                if (is_array($run) && is_string($run['text'] ?? null)) {
                    $parts[] = $run['text'];
                }
            }
            foreach ($block['header'] ?? [] as $cell) {
                if (is_string($cell)) {
                    $parts[] = $cell;
                }
            }
            foreach ($block['rows'] ?? [] as $row) {
                if (is_array($row)) {
                    $parts[] = implode(' ', array_map('strval', $row));
                }
            }
        }

        return trim(implode(' ', $parts));
    }

    /** @param mixed $blocks @return list<list<mixed>> */
    private function vocabularyRows(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'source_table') {
                continue;
            }
            $firstHeader = mb_strtolower(trim((string) ($block['header'][0] ?? '')));
            if ($firstHeader === 'word or phrase' && is_array($block['rows'] ?? null)) {
                return array_values(array_filter($block['rows'], 'is_array'));
            }
        }

        return [];
    }

    private function sourceFingerprint(): string
    {
        $package = CurriculumPackage::active();

        return hash('sha256', implode('|', [
            self::INDEX_VERSION,
            $package === null ? 'no-active-curriculum' : $package->source_tree_sha256,
            $this->help->fingerprint(),
        ]));
    }
}
