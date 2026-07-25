<?php

namespace App\Models;

use App\Enums\CourseOfferingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CourseOfferingEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Class lifecycle events are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Class lifecycle events are append-only evidence.');
        });
    }

    protected $fillable = [
        'course_offering_id',
        'institution_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => CourseOfferingStatus::class,
            'to_status' => CourseOfferingStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
