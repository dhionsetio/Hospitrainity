<?php

namespace App\Http\Controllers;

use App\Services\SecurityEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CspReportController extends Controller
{
    public function __invoke(Request $request, SecurityEventRecorder $events): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 16_384, 413);
        $report = $request->json('csp-report', $request->json()->all());
        $events->record('browser.csp_violation', 'reported', $request->user(), null, $request, [
            'effective_directive' => is_array($report) ? ($report['effective-directive'] ?? null) : null,
            'disposition' => is_array($report) ? ($report['disposition'] ?? null) : null,
            'status_code' => is_array($report) ? ($report['status-code'] ?? null) : null,
        ], 'warning');

        return response()->json([], 202);
    }
}
