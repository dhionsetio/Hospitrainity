<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchDocument extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(SearchIndexGeneration::class, 'search_index_generation_id');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(SearchDocumentTerm::class);
    }
}
