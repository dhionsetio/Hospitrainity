<?php

namespace App\Models;

use App\Enums\CurriculumApprovalGate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CurriculumReleaseApproval extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $approval): void {
            $reviewerName = trim((string) $approval->reviewer_name);
            $qualification = trim((string) $approval->reviewer_qualification);
            if ($approval->recorded_by_user_id === null
                || $reviewerName === ''
                || mb_strlen($reviewerName) > 255
                || $qualification === ''
                || mb_strlen($qualification) > 255
                || preg_match('/\A[0-9a-f]{64}\z/', (string) $approval->evidence_sha256) !== 1) {
                throw new LogicException('Release approval evidence requires a recorder, named reviewer, qualification, and lowercase SHA-256 hash.');
            }

            $approval->reviewer_name = $reviewerName;
            $approval->reviewer_qualification = $qualification;
        });
        static::updating(static function (): never {
            throw new LogicException('Curriculum release approvals are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Curriculum release approvals are immutable.');
        });
    }

    protected $fillable = [
        'curriculum_release_id',
        'gate',
        'recorded_by_user_id',
        'reviewer_name',
        'reviewer_qualification',
        'evidence_sha256',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'gate' => CurriculumApprovalGate::class,
            'approved_at' => 'immutable_datetime',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(CurriculumRelease::class, 'curriculum_release_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
