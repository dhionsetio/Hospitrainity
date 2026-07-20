<?php

namespace App\Services;

use App\Models\SearchDocument;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SearchService
{
    public const TYPES = ['module', 'section', 'vocabulary', 'activity', 'help', 'glossary'];

    public function __construct(private readonly SearchIndexBuilder $index) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function search(string $query, ?string $type, string $locale): LengthAwarePaginator
    {
        $tokens = array_slice($this->index->tokens($query), 0, 6);
        if ($tokens === []) {
            return new LengthAwarePaginator([], 0, 12, 1, ['path' => request()->url(), 'query' => request()->query()]);
        }

        $matches = DB::table('search_document_terms as term')
            ->join('search_documents as document', 'document.id', '=', 'term.search_document_id')
            ->join('search_index_generations as generation', 'generation.id', '=', 'document.search_index_generation_id')
            ->where('generation.is_active', true)
            ->where('document.published', true)
            ->whereIn('document.audience', ['public', 'authenticated'])
            ->whereIn('term.term', $tokens)
            ->where(function ($scope) use ($locale): void {
                $scope->where('document.locale', $locale);
                if ($locale !== 'en') {
                    $scope->orWhere(function ($englishCurriculum): void {
                        $englishCurriculum->where('document.locale', 'en')
                            ->whereIn('document.source_type', ['module', 'section', 'vocabulary', 'activity']);
                    });
                }
            })
            ->when($type !== null, fn ($builder) => $builder->where('document.source_type', $type))
            ->groupBy('document.id')
            ->select('document.id')
            ->selectRaw('COUNT(DISTINCT term.term) AS matched_terms')
            ->selectRaw('SUM(term.weight) AS relevance')
            ->orderByDesc('matched_terms')
            ->orderByDesc('relevance')
            ->orderBy('document.id')
            ->paginate(12)
            ->withQueryString();

        $documents = SearchDocument::query()->whereIn('id', $matches->getCollection()->pluck('id'))->get()->keyBy('id');
        $matches->setCollection($matches->getCollection()->map(function ($match) use ($documents): array {
            $document = $documents->get($match->id);

            return [
                'type' => $document->source_type,
                'title' => $document->title,
                'summary' => $document->summary,
                'locale' => $document->locale,
                'matched_terms' => (int) $match->matched_terms,
                'url' => $this->url($document),
            ];
        }));

        return $matches;
    }

    private function url(SearchDocument $document): string
    {
        return match ($document->source_type) {
            'module' => route('curriculum.chapters.show', $document->route_reference),
            'section', 'vocabulary' => route('curriculum.sections.show', $document->route_reference),
            'activity' => route('curriculum.activities.show', $document->route_reference),
            'help' => route('help.show', $document->route_reference),
            'glossary' => route('glossary.index').'#term-'.$document->route_reference,
            default => throw new RuntimeException('Unsupported indexed search document type.'),
        };
    }
}
