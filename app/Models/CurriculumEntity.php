<?php

namespace App\Models;

use App\Models\Concerns\Completable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumEntity extends Model
{
    use Completable;

    public $timestamps = false;

    protected $fillable = [
        'curriculum_package_id',
        'entity_uuid',
        'code',
        'entity_type',
        'parent_code',
        'position',
        'lifecycle_status',
        'content_version',
        'source_path',
        'source_sha256',
        'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    /** @return array<string, mixed> */
    public function payloadData(): array
    {
        $payload = $this->getAttribute('payload');

        return is_array($payload) ? $payload : [];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'curriculum_package_id');
    }

    public function scopeInActivePackage(Builder $query): Builder
    {
        return $query->whereHas('package', static fn (Builder $package): Builder => $package->where('is_active', true));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('lifecycle_status', 'published');
    }
}
