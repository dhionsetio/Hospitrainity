<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumAttempt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'attempted_at' => 'datetime',
            'self_checked_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CurriculumResponse::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CurriculumAttemptEvent::class);
    }
}
