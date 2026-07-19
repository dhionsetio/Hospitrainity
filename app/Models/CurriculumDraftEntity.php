<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumDraftEntity extends Model
{
    protected $fillable = [
        'curriculum_draft_id', 'source_entity_id', 'entity_uuid', 'code', 'entity_type',
        'parent_code', 'position', 'source_path', 'payload', 'revision', 'archived_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'revision' => 'integer', 'archived_at' => 'datetime'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CurriculumDraft::class, 'curriculum_draft_id');
    }

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(CurriculumEntity::class, 'source_entity_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CurriculumDraftBlock::class)->orderBy('position')->orderBy('id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
