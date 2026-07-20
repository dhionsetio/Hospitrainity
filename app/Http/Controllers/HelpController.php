<?php

namespace App\Http\Controllers;

use App\Services\HelpContentRegistry;
use App\Services\RoleLandingResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(
        Request $request,
        HelpContentRegistry $content,
        RoleLandingResolver $landing,
    ): View {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $query = mb_strtolower(trim((string) ($validated['q'] ?? '')));
        $topics = $content->topics();
        $glossary = $content->glossary();

        if ($query !== '') {
            $topics = $topics->filter(fn (array $topic): bool => str_contains(
                mb_strtolower($topic['title'].' '.$topic['summary'].' '.collect($topic['sections'])->pluck('body')->flatten()->implode(' ')),
                $query,
            ))->values();
            $glossary = $glossary->filter(fn (array $entry): bool => str_contains(
                mb_strtolower($entry['term'].' '.$entry['definition']),
                $query,
            ))->values();
        }

        $backUrl = $request->user() === null ? url('/') : $landing->url($request->user());
        $backLabel = $request->user() === null ? __('Return to Hospitrainity') : __('Return to dashboard');

        return view('help.index', compact('topics', 'glossary', 'query', 'backUrl', 'backLabel'));
    }

    public function show(string $slug, HelpContentRegistry $content): View
    {
        $topic = $content->topic($slug);
        abort_if($topic === null, 404);

        return view('help.show', compact('topic'));
    }

    public function glossary(HelpContentRegistry $content): View
    {
        return view('help.glossary', ['entries' => $content->glossary()]);
    }

    public function about(HelpContentRegistry $content): View
    {
        return view('help.about', ['document' => $content->about()]);
    }
}
