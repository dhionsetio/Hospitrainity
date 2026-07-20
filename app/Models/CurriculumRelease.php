<?php

namespace App\Models;

use App\Enums\CurriculumReleaseState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CurriculumRelease extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $release): void {
            if ($release->isDirty(['curriculum_package_id', 'source_tree_sha256'])) {
                throw new LogicException('Curriculum release package identity and source checksum are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Curriculum release records are immutable; use a lifecycle transition.');
        });
    }

    protected $fillable = [
        'curriculum_package_id',
        'state',
        'preview_only',
        'source_tree_sha256',
        'activated_by_user_id',
        'activated_at',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => CurriculumReleaseState::class,
            'preview_only' => 'boolean',
            'activated_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'curriculum_package_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CurriculumReleaseApproval::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CurriculumReleaseEvent::class);
    }
}
