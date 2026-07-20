<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchDocumentTerm extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SearchDocument::class);
    }
}
