<?php

namespace App\Policies;

use App\Enums\CurriculumDraftStatus;
use App\Models\CurriculumDraft;
use App\Models\CurriculumPackage;
use App\Models\User;

class CurriculumDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function view(User $user, CurriculumDraft $draft): bool
    {
        return $user->isContentAdministrator();
    }

    public function update(User $user, CurriculumDraft $draft): bool
    {
        return $user->isContentAdministrator() && $draft->status === CurriculumDraftStatus::Draft;
    }

    public function validate(User $user, CurriculumDraft $draft): bool
    {
        return $user->isContentAdministrator() && $draft->status === CurriculumDraftStatus::Draft;
    }

    public function preview(User $user, CurriculumDraft $draft): bool
    {
        return $user->isContentAdministrator();
    }

    public function requestChanges(User $user, CurriculumDraft $draft): bool
    {
        return $user->isContentAdministrator()
            && in_array($draft->status, [CurriculumDraftStatus::InReview, CurriculumDraftStatus::Approved], true);
    }

    public function approve(User $user, CurriculumDraft $draft): bool
    {
        return $user->isSuperAdmin() && $draft->status === CurriculumDraftStatus::InReview;
    }

    public function publish(User $user, CurriculumDraft $draft): bool
    {
        // B01 containment: editorial approval is not release approval. Keep the
        // legacy route fail-closed until named evidence exists for every gate.
        return false;
    }

    public function rollback(User $user, CurriculumDraft $draft): bool
    {
        // B01/B17 containment: production rollback requires an independently
        // rehearsed approved-release workflow, not the legacy draft action.
        return ! app()->isProduction()
            && $user->isSuperAdmin()
            && $draft->status === CurriculumDraftStatus::Published
            && $draft->publication_run_id !== null
            && $draft->published_package_id !== null
            && CurriculumPackage::query()->whereKey($draft->published_package_id)->where('is_active', true)->exists();
    }
}
