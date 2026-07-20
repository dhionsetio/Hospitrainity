<?php

namespace App\Models;

use App\Support\AuditPayloadSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class IdentityAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'institution_id',
        'invitation_id',
        'event',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $audit): void {
            $audit->metadata = AuditPayloadSanitizer::metadata($audit->metadata);
        });
        static::updating(static function (): never {
            throw new LogicException('Identity audit records are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Identity audit records are append-only.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(InstitutionInvitation::class);
    }
}
