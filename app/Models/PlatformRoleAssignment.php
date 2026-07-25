<?php

namespace App\Models;

use App\Enums\PlatformRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PlatformRoleAssignment extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $assignment): void {
            if ($assignment->isDirty(['user_id', 'role', 'assigned_by_user_id', 'assigned_at'])) {
                throw new LogicException('Platform role assignment evidence is immutable; revoke or restore the assignment.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Platform role assignments must be revoked; direct deletion is prohibited.');
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => PlatformRole::class,
            'assigned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
