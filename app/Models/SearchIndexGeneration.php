<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchIndexGeneration extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_count' => 'integer',
            'is_active' => 'boolean',
            'built_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SearchDocument::class);
    }
}
