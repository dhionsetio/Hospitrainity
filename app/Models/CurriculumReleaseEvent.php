<?php

namespace App\Models;

use App\Enums\CurriculumReleaseState;
use App\Support\AuditPayloadSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CurriculumReleaseEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'curriculum_release_id',
        'actor_user_id',
        'event',
        'from_state',
        'to_state',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_state' => CurriculumReleaseState::class,
            'to_state' => CurriculumReleaseState::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->reason = AuditPayloadSanitizer::text($event->reason, 1000);
            $event->metadata = AuditPayloadSanitizer::metadata($event->metadata);
        });
        static::updating(static function (): never {
            throw new LogicException('Curriculum release events are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Curriculum release events are append-only.');
        });
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(CurriculumRelease::class, 'curriculum_release_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
