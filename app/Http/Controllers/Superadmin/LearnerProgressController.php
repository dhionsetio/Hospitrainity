<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\ListLearnerProgressRequest;
use App\Models\User;
use App\Services\ProgressAdministrationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
