<?php

namespace App\Models;

use App\Models\Concerns\Completable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocabularyItem extends Model
{
    use Completable;
    use HasFactory;

    protected $fillable = ['vocabulary_id', 'term', 'details', 'media_url', 'order'];

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }
}
