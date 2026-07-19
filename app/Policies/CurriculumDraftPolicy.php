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
        return $user->isSuperAdmin() && $draft->status === CurriculumDraftStatus::Approved;
    }

    public function rollback(User $user, CurriculumDraft $draft): bool
    {
        return $user->isSuperAdmin()
            && $draft->status === CurriculumDraftStatus::Published
            && $draft->publication_run_id !== null
            && $draft->published_package_id !== null
            && CurriculumPackage::query()->whereKey($draft->published_package_id)->where('is_active', true)->exists();
    }
}
