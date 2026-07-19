<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\ListAdministrationAuditsRequest;
use App\Services\AdministrationAuditReviewService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdministrationAuditController extends Controller
{
    public function __construct(private readonly AdministrationAuditReviewService $audits) {}

    public function index(ListAdministrationAuditsRequest $request): View
    {
        $filters = $request->validated();
        $entries = $this->audits->paginate($filters);
        Log::notice('administration.audit_reviewed', [
            'actor_user_id' => $request->user()->id,
            'filter_keys' => array_keys(array_filter(
                $filters,
                static fn (mixed $value): bool => $value !== null && $value !== '',
            )),
            'page' => max(1, (int) $request->query('page', 1)),
            'visible_rows' => $entries->count(),
        ]);

        return view('superadmin.audits.index', [
            'entries' => $entries,
            'eventOptions' => $this->audits->eventOptions($filters['category'] ?? null),
            'filters' => $filters,
        ]);
    }
}
