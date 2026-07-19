<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CurriculumProgressService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SpvDashboardController extends Controller
{
    private const LEARNERS_PER_PAGE = 20;

    public function __construct(private readonly CurriculumProgressService $progress) {}

    public function index(): View
    {
        $supervisor = Auth::user();

        $institution = (string) $supervisor->instansi;
        $users = User::query()
            ->select(['id', 'name', 'instansi', 'email', 'role'])
            ->where('role', UserRole::Learner->value)
            ->when(
                trim($institution) === '',
                static fn ($query) => $query->whereRaw('1 = 0'),
                static fn ($query) => $query->where('instansi', $institution),
            )
            ->orderBy('id')
            ->paginate(self::LEARNERS_PER_PAGE)
            ->withQueryString();

        $progressByUser = $this->progress->overallForUsers($users->getCollection());
        $users->getCollection()->each(function (User $user) use ($progressByUser): void {
            $user->overall_progress = $progressByUser[$user->getKey()] ?? 0;
        });

        return view('supervisor.dashboard', compact('users'));
    }
}
