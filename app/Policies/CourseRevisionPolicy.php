<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\User;
use App\Services\CourseAccessService;
use Illuminate\Auth\Access\Response;

class CourseRevisionPolicy
{
    public function __construct(private readonly CourseAccessService $access) {}

    public function create(User $actor, Course $course): bool
    {
        return $this->access->canCreateRevision($actor, $course);
    }

    public function view(User $actor, CourseRevision $revision): Response
    {
        $course = Course::query()->find($revision->course_id);

        return $course !== null && $this->access->canViewCourse($actor, $course)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
