<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\ListLearnerProgressRequest;
use App\Models\AdministrationAudit;
use App\Models\User;
use App\Services\ProgressAdministrationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearnerProgressController extends Controller
{
    public function __construct(private readonly ProgressAdministrationService $progress) {}

    public function index(ListLearnerProgressRequest $request): View
    {
        $filters = $request->validated();

        return view('superadmin.progress.index', [
            'learners' => $this->progress->identityLearners($filters),
            'overview' => $this->progress->aggregateOverview(),
            'options' => $this->progress->filterOptions(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, User $learner): View
    {
        $this->authorize('viewLearnerProgress', $learner);
        $selection = $request->validate([
            'package' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9._-]+\z/'],
            'version' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/\A[A-Za-z0-9._-]+\z/'],
        ]);

        return view('progress.learner', [
            'detail' => $this->progress->learnerDetail(
                $learner,
                $selection['package'] ?? null,
                $selection['version'] ?? null,
            ),
            'administrationRoutePrefix' => 'superadmin',
            'detailRouteName' => 'superadmin.progress.learners.show',
            'backRouteName' => 'superadmin.progress.index',
        ]);
    }

    public function export(ListLearnerProgressRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $learners = $this->progress->identityLearners($filters);

        AdministrationAudit::query()->create([
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $request->user()->id,
            'event' => 'superadmin.progress_exported',
            'old_role' => $request->user()->role,
            'new_role' => $request->user()->role,
            'reason' => 'Exported learner progress CSV',
            'metadata' => [
                'count' => $learners->count(),
            ],
            'created_at' => now(),
        ]);

        $filename = 'hospitrainity_learner_progress_'.date('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($learners) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Learner ID',
                'Name',
                'Email',
                'Role',
                'Institution',
                'Registered At',
            ]);

            $sanitize = static function ($value): string {
                $str = (string) $value;
                if (preg_match('/^[\=\+\-\@\t\r]/', $str)) {
                    return "'".$str;
                }

                return $str;
            };

            foreach ($learners as $learner) {
                fputcsv($handle, [
                    $sanitize($learner->id),
                    $sanitize($learner->name),
                    $sanitize($learner->email),
                    $sanitize($learner->role->value ?? (string) $learner->role),
                    $sanitize($learner->instansi ?: '-'),
                    $sanitize($learner->created_at?->toIso8601String() ?? '-'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
