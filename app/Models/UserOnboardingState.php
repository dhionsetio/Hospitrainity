<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboardingState extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'started_at' => 'datetime',
            'skipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'restarted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
