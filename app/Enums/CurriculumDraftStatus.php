<?php

namespace App\Enums;

enum CurriculumDraftStatus: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Validating],
            self::Validating => [self::Draft, self::InReview],
            self::InReview => [self::Draft, self::Approved],
            self::Approved => [self::Draft, self::Published],
            self::Published => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedNext(), true);
    }
}
