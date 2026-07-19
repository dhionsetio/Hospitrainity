<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumAttemptEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CurriculumAttempt::class, 'curriculum_attempt_id');
    }
}
