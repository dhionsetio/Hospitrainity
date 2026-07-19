<?php

namespace App\Models;

use App\Enums\CurriculumAssetKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CurriculumAsset extends Model
{
    protected $fillable = [
        'curriculum_draft_id', 'curriculum_asset_blob_id', 'display_name', 'kind',
        'accessibility_text', 'rights_basis', 'source_url', 'created_by', 'archived_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['kind' => CurriculumAssetKind::class, 'archived_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CurriculumDraft::class, 'curriculum_draft_id');
    }

    public function blob(): BelongsTo
    {
        return $this->belongsTo(CurriculumAssetBlob::class, 'curriculum_asset_blob_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CurriculumDraftBlock::class);
    }
}
