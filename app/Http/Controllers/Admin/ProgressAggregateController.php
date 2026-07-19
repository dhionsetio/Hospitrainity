<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProgressAdministrationService;
use Illuminate\View\View;

class ProgressAggregateController extends Controller
{
    public function __construct(private readonly ProgressAdministrationService $progress) {}

    public function index(): View
    {
        $this->authorize('viewAggregateProgress', User::class);

        return view('admin.progress.index', [
            'overview' => $this->progress->aggregateOverview(),
        ]);
    }
}
