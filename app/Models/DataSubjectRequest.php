<?php

namespace App\Models;

use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DataSubjectRequest extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => DataSubjectRequestType::class,
            'status' => DataSubjectRequestStatus::class,
            'request_note' => 'encrypted',
            'decision_note' => 'encrypted',
            'identity_verified_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'executing_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DataSubjectRequestEvent::class)->orderBy('created_at');
    }

    public function export(): HasOne
    {
        return $this->hasOne(DataExport::class);
    }

    public function erasureSteps(): HasMany
    {
        return $this->hasMany(AccountErasureStep::class);
    }
}
