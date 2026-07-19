<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumPackage;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $activeCanonicalPackage = CurriculumPackage::active();
        $activeEntityCounts = $activeCanonicalPackage?->entities()
            ->whereIn('entity_type', ['chapter', 'lesson-section', 'activity'])
            ->where('lifecycle_status', 'published')
            ->selectRaw('entity_type, COUNT(*) as aggregate')
            ->groupBy('entity_type')
            ->pluck('aggregate', 'entity_type') ?? collect();

        $stats = [
            'total_learners' => User::where('role', UserRole::Learner->value)->count(),
            'total_supervisors' => User::where('role', UserRole::Supervisor->value)->count(),
            'total_institutions' => User::query()
                ->whereNotNull('instansi')
                ->where('instansi', '!=', '')
                ->distinct()
                ->count('instansi'),
            'active_canonical_chapters' => (int) ($activeEntityCounts['chapter'] ?? 0),
        ];

        $canonicalStatus = [
            'content_version' => $activeCanonicalPackage?->content_version,
            'lifecycle_status' => $activeCanonicalPackage?->lifecycle_status,
            'schema_version' => $activeCanonicalPackage?->schema_version,
            'imported_at' => $activeCanonicalPackage?->imported_at,
            'chapters' => (int) ($activeEntityCounts['chapter'] ?? 0),
            'sections' => (int) ($activeEntityCounts['lesson-section'] ?? 0),
            'activities' => (int) ($activeEntityCounts['activity'] ?? 0),
            'total_versions' => CurriculumPackage::query()->count(),
            'draft_lifecycle_versions' => CurriculumPackage::query()
                ->where('lifecycle_status', 'draft')
                ->count(),
            'inactive_versions' => CurriculumPackage::query()
                ->where('is_active', false)
                ->count(),
        ];

        $legacyEvidenceCounts = [
            'modules' => Module::query()->count(),
            'lessons' => Lesson::query()->count(),
            'vocabularies' => Vocabulary::query()->count(),
            'materials' => Material::query()->count(),
            'exercises' => Exercise::query()->count(),
        ];

        $draftWorkspaceCounts = [
            'total' => CurriculumDraft::query()->count(),
            'editable' => CurriculumDraft::query()->where('status', 'draft')->count(),
            'in_review' => CurriculumDraft::query()->whereIn('status', ['validating', 'in_review', 'approved'])->count(),
            'published' => CurriculumDraft::query()->where('status', 'published')->count(),
            'available_entities' => CurriculumDraftEntity::query()->whereNull('archived_at')->count(),
            'archived_entities' => CurriculumDraftEntity::query()->whereNotNull('archived_at')->count(),
        ];

        return view('superadmin.dashboard', compact(
            'activeCanonicalPackage',
            'canonicalStatus',
            'legacyEvidenceCounts',
            'draftWorkspaceCounts',
            'stats',
        ));
    }
}
