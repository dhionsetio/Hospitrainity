<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property CarbonImmutable $expires_at
 * @property string|null $course_offering_id
 */
class InstitutionJoinCode extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $code): void {
            if ($code->isDirty(['institution_id', 'course_offering_id', 'issued_by_user_id', 'token_hash', 'expires_at', 'use_limit'])) {
                throw new LogicException('Join-code scope and issuance evidence are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Join codes must be revoked; direct deletion is prohibited.');
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(InstitutionJoinRequest::class, 'join_code_id');
    }

    public function isRedeemable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->use_count < $this->use_limit;
    }
}
