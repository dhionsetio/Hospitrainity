<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\AuditPayloadSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AdministrationAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'event',
        'old_role',
        'new_role',
        'reason',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_role' => UserRole::class,
            'new_role' => UserRole::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $audit): void {
            $audit->reason = AuditPayloadSanitizer::text($audit->reason, 1000);
            $audit->user_agent = AuditPayloadSanitizer::singleLine($audit->user_agent, 255);
            $audit->metadata = AuditPayloadSanitizer::metadata($audit->metadata);
        });
        static::updating(static function (): never {
            throw new LogicException('Administration audit records are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Administration audit records are append-only.');
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
}
