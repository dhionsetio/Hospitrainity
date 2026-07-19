<?php

namespace App\Models;

use App\Support\AuditPayloadSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CurriculumDraftEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'curriculum_draft_id', 'actor_id', 'event_type', 'from_status', 'to_status',
        'revision', 'reason', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'revision' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->reason = AuditPayloadSanitizer::text($event->reason, 1000);
            $event->metadata = AuditPayloadSanitizer::metadata($event->metadata);
        });
        static::updating(static function (): never {
            throw new LogicException('Curriculum audit events are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Curriculum audit events are append-only.');
        });
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CurriculumDraft::class, 'curriculum_draft_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
