<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Institution;
use App\Models\User;
use App\Services\CourseAccessService;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    public function __construct(private readonly CourseAccessService $access) {}

    public function create(User $actor, Institution $institution): bool
    {
        return $this->access->canCreateCourse($actor, $institution);
    }

    public function view(User $actor, Course $course): Response
    {
        return $this->access->canViewCourse($actor, $course)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $actor, Course $course): Response
    {
        return $course->archived_at === null && $this->access->canManageCourse($actor, $course)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function archive(User $actor, Course $course): Response
    {
        return $course->archived_at === null && $this->access->canManageCourse($actor, $course)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
