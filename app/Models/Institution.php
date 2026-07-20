<?php

namespace App\Models;

use App\Enums\InstitutionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Institution extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $institution): void {
            if ($institution->isDirty(['id', 'key'])) {
                throw new LogicException('Institution IDs and normalized keys are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Institutions must be archived through an approved lifecycle; direct deletion is prohibited.');
        });
    }

    protected $fillable = [
        'key',
        'name_id',
        'name_en',
        'status',
        'owner_user_id',
        'verified_at',
        'verification_method',
    ];

    protected function casts(): array
    {
        return [
            'status' => InstitutionStatus::class,
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(InstitutionMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(InstitutionInvitation::class);
    }

    public function joinCodes(): HasMany
    {
        return $this->hasMany(InstitutionJoinCode::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(InstitutionJoinRequest::class);
    }

    public function displayName(string $locale = 'en'): string
    {
        return $locale === 'id' ? $this->name_id : $this->name_en;
    }
}
