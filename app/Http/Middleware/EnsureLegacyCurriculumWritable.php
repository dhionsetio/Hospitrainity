<?php

namespace App\Http\Middleware;

use App\Models\CurriculumPackage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyCurriculumWritable
{
    /**
     * Retained legacy rows are audit/rollback evidence once a canonical package
     * is active. Reject writes at the server boundary so a stale form or direct
     * request cannot mutate data that no longer controls learner delivery.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            CurriculumPackage::query()->where('is_active', true)->exists(),
            410,
            __('Legacy curriculum writes were retired when the canonical package was activated.'),
        );

        return $next($request);
    }
}
