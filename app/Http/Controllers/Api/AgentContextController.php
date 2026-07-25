<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AgentContextRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class AgentContextController extends Controller
{
    public function index(Request $request, AgentContextRepository $contexts): JsonResponse
    {
        $validated = $request->validate([
            'include' => ['sometimes', 'string', 'max:200'],
        ]);

        try {
            $included = $contexts->resolveIncludes($validated['include'] ?? null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['include' => $exception->getMessage()]);
        }

        return response()->json($contexts->document($included));
    }

    public function show(string $section, AgentContextRepository $contexts): JsonResponse
    {
        return response()->json($contexts->document([$section]));
    }

    public function openApi(AgentContextRepository $contexts): JsonResponse
    {
        return response()->json($contexts->openApi());
    }
}
