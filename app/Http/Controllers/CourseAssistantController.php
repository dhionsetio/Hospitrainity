<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskCourseAssistantRequest;
use App\Services\Assistant\CourseAssistantService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseAssistantController extends Controller
{
    public function index(Request $request): View
    {
        return view('assistant.index', [
            'question' => '',
            'result' => null,
        ]);
    }

    public function ask(AskCourseAssistantRequest $request, CourseAssistantService $assistant): View
    {
        $question = (string) $request->validated('question');

        return view('assistant.index', [
            'question' => $question,
            'result' => $assistant->ask($request->user(), $question),
        ]);
    }
}
