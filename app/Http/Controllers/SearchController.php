<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::in(SearchService::TYPES)],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));
        $type = $validated['type'] ?? null;
        $results = $query === '' ? null : $search->search($query, $type, app()->getLocale());

        return view('search.index', compact('query', 'type', 'results'));
    }
}
