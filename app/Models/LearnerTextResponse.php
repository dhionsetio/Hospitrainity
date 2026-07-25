<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property CarbonImmutable|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CurriculumEntity|null $activity
 * @property-read CurriculumEntity|null $prompt
 */
class LearnerTextResponse extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $response): void {
            if ($response->getOriginal('state') === 'submitted') {
                throw new LogicException('Submitted learner responses are immutable.');
            }

            if ($response->isDirty([
                'user_id',
                'response_key',
                'learning_scope_key',
                'institution_membership_id',
                'course_offering_id',
                'course_enrollment_id',
                'course_revision_id',
                'curriculum_package_id',
                'activity_entity_id',
                'prompt_entity_id',
                'activity_source_sha256',
                'prompt_source_sha256',
                'kind',
            ])) {
                throw new LogicException('Learner response ownership and curriculum context are immutable.');
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
        'activity_entity_id',
        'prompt_entity_id',
        'activity_source_sha256',
        'prompt_source_sha256',
        'kind',
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

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CurriculumEntity::class, 'activity_entity_id');
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(CurriculumEntity::class, 'prompt_entity_id');
    }

    public function courseRevision(): BelongsTo
    {
        return $this->belongsTo(CourseRevision::class);
    }
}
