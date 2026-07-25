<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property CarbonImmutable|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WarmUpReflection extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $reflection): void {
            if ($reflection->getOriginal('state') === 'submitted') {
                throw new LogicException('Submitted warm-up reflections are immutable.');
            }

            if ($reflection->isDirty([
                'user_id',
                'response_key',
                'learning_scope_key',
                'institution_membership_id',
                'course_offering_id',
                'course_enrollment_id',
                'course_revision_id',
                'curriculum_package_id',
                'chapter_code',
                'section_code',
                'section_source_sha256',
                'prompt_index',
                'prompt_fingerprint',
            ])) {
                throw new LogicException('Warm-up reflection ownership and curriculum context are immutable.');
            }
        });

        static::deleting(function (WarmUpReflection $reflection) {
            $service = app(\App\Services\Reflections\WarmUpReflectionService::class);
            foreach ($reflection->attachments as $attachment) {
                $service->forgetAttachment($attachment);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'response_key',
        'learning_scope_key',
        'institution_membership_id',
        'course_offering_id',
        'course_enrollment_id',
        'course_revision_id',
        'curriculum_package_id',
        'chapter_code',
        'section_code',
        'section_source_sha256',
        'prompt_index',
        'prompt_fingerprint',
        'state',
        'body',
        'body_hmac_sha256',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class, 'curriculum_package_id');
    }

    public function courseRevision(): BelongsTo
    {
        return $this->belongsTo(CourseRevision::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WarmUpReflectionAttachment::class);
    }
}
