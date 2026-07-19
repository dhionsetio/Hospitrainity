<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumDraftBlock extends Model
{
    protected $fillable = [
        'curriculum_draft_id', 'curriculum_draft_entity_id', 'curriculum_asset_id', 'block_uuid', 'block_type',
        'position', 'payload', 'revision', 'archived_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'revision' => 'integer', 'archived_at' => 'datetime'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CurriculumDraft::class, 'curriculum_draft_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(CurriculumDraftEntity::class, 'curriculum_draft_entity_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CurriculumAsset::class, 'curriculum_asset_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
