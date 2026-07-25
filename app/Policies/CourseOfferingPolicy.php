<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Services\CourseAccessService;
use Illuminate\Auth\Access\Response;

class CourseOfferingPolicy
{
    public function __construct(private readonly CourseAccessService $access) {}

    public function create(User $actor, Course $course): bool
    {
        return $this->access->canCreateOffering($actor, $course);
    }

    public function view(User $actor, CourseOffering $offering): Response
    {
        return $this->access->canViewOffering($actor, $offering)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $actor, CourseOffering $offering): Response
    {
        return $this->access->canManageOffering($actor, $offering)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function manageRoster(User $actor, CourseOffering $offering): Response
    {
        return $this->update($actor, $offering);
    }

    public function transition(User $actor, CourseOffering $offering): Response
    {
        return $this->update($actor, $offering);
    }
}
