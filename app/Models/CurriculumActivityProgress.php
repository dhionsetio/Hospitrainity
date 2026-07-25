<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculumActivityProgress extends Model
{
    protected $table = 'curriculum_activity_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'started_at' => 'datetime',
            'attempted_at' => 'datetime',
            'self_checked_at' => 'datetime',
            'completed_at' => 'datetime',
            'baseline_skipped_at' => 'datetime',
            'review_due_at' => 'immutable_datetime',
            'last_reviewed_at' => 'immutable_datetime',
        ];
    }
}
