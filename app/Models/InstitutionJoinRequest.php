<?php

namespace App\Models;

use App\Enums\InstitutionJoinRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property InstitutionJoinRequestStatus $status
 * @property string|null $course_offering_id
 */
class InstitutionJoinRequest extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            if ($request->isDirty(['institution_id', 'course_offering_id', 'user_id', 'join_code_id', 'requested_at'])) {
                throw new LogicException('Join-request identity and provenance are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Join requests are retained as bounded evidence; direct deletion is prohibited.');
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => InstitutionJoinRequestStatus::class,
            'requested_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function joinCode(): BelongsTo
    {
        return $this->belongsTo(InstitutionJoinCode::class, 'join_code_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
