<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property CarbonImmutable $expires_at
 * @property string|null $course_offering_id
 */
class InstitutionInvitation extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $invitation): void {
            if ($invitation->isDirty([
                'institution_id',
                'course_offering_id',
                'issued_by_user_id',
                'target_email_ciphertext',
                'target_email_hash',
                'token_hash',
                'expires_at',
                'use_limit',
            ])) {
                throw new LogicException('Invitation scope, target, token, and expiry are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Invitation records are retained as bounded evidence and cannot be deleted directly.');
        });
    }

    protected $fillable = [
        'institution_id',
        'course_offering_id',
        'issued_by_user_id',
        'target_email_ciphertext',
        'target_email_hash',
        'token_hash',
        'expires_at',
        'use_limit',
        'use_count',
        'accepted_at',
        'accepted_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'target_email_ciphertext' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'use_limit' => 'integer',
            'use_count' => 'integer',
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

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function isRedeemable(): bool
    {
        return $this->revoked_at === null
            && $this->accepted_at === null
            && $this->use_count < $this->use_limit
            && $this->expires_at->isFuture();
    }
}
