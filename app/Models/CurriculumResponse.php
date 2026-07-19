<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumResponse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'response_present' => 'boolean',
            'is_correct' => 'boolean',
            'self_checked' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CurriculumAttempt::class, 'curriculum_attempt_id');
    }
}
