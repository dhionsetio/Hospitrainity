<?php

use App\Http\Controllers\Api\AgentContextController;
use App\Http\Middleware\EnsureAgentContextAccess;
use App\Services\AgentContextRepository;
use Illuminate\Support\Facades\Route;

$rateLimit = max(1, (int) config('agent_context.rate_limit_per_minute', 30));

// Throttle before authentication so repeated invalid-token attempts consume
// the same bounded request budget as successful reads.
Route::middleware(["throttle:{$rateLimit},1", EnsureAgentContextAccess::class])
    ->prefix('v1/agent-context')
    ->name('api.agent-context.')
    ->group(function (): void {
        Route::get('/', [AgentContextController::class, 'index'])->name('index');
        Route::get('/openapi', [AgentContextController::class, 'openApi'])->name('openapi');
        Route::get('/{section}', [AgentContextController::class, 'show'])
            ->whereIn('section', AgentContextRepository::SECTIONS)
            ->name('show');
    });
