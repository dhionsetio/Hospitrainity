<?php

namespace App\Services;

use App\Enums\CourseOfferingStatus;
use App\Models\CourseOffering;
use App\Models\CourseOfferingEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class CourseOfferingLifecycle
{
    /** @var array<string, list<CourseOfferingStatus>> */
    private const ALLOWED = [
        CourseOfferingStatus::Draft->value => [
            CourseOfferingStatus::EnrollmentOpen,
            CourseOfferingStatus::Archived,
        ],
        CourseOfferingStatus::EnrollmentOpen->value => [
            CourseOfferingStatus::Active,
            CourseOfferingStatus::Closed,
        ],
        CourseOfferingStatus::Active->value => [
            CourseOfferingStatus::Closed,
        ],
        CourseOfferingStatus::Closed->value => [
            CourseOfferingStatus::Archived,
        ],
        CourseOfferingStatus::Archived->value => [],
    ];

    public function __construct(private readonly CourseAccessService $access) {}

    public function transition(
        User $actor,
        CourseOffering $offering,
        CourseOfferingStatus $expected,
        CourseOfferingStatus $target,
        string $reason,
    ): CourseOffering {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Class lifecycle reason must contain 1 to 500 characters.');
        }

        return DB::transaction(function () use ($actor, $offering, $expected, $target, $reason): CourseOffering {
            $locked = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
            if (! $this->access->canManageOffering($actor, $locked)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            if ($locked->status !== $expected) {
                throw new RuntimeException('Class lifecycle transition refused because the current state changed.');
            }
            if (! in_array($target, self::ALLOWED[$expected->value], true)) {
                throw new RuntimeException('The requested Class lifecycle transition is not allowed.');
            }

            $locked->forceFill(['status' => $target])->saveQuietly();
            CourseOfferingEvent::query()->create([
                'course_offering_id' => $locked->getKey(),
                'institution_id' => $locked->institution_id,
                'from_status' => $expected,
                'to_status' => $target,
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
            ]);

            $locked->refresh();

            return $locked;
        });
    }
}
