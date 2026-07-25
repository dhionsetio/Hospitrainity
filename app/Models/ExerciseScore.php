<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'learning_scope_key',
        'institution_membership_id',
        'exercise_id',
        'score',
        'max_score',
        'response_data',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'max_score' => 'integer',
            'response_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function institutionMembership(): BelongsTo
    {
        return $this->belongsTo(InstitutionMembership::class);
    }
}
