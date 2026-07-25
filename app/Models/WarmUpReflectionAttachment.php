<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CarbonImmutable|null $scanned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WarmUpReflectionAttachment extends Model
{
    use HasUuids;

    protected $fillable = [
        'warm_up_reflection_id',
        'kind',
        'disk',
        'storage_path',
        'original_name',
        'detected_mime',
        'byte_size',
        'sha256',
        'scan_status',
        'upload_security_record_id',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'immutable_datetime',
        ];
    }

    public function reflection(): BelongsTo
    {
        return $this->belongsTo(WarmUpReflection::class, 'warm_up_reflection_id');
    }
}
