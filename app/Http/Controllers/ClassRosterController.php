<?php

namespace App\Http\Controllers;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\TeachingAssignmentRole;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\InstitutionMembership;
use App\Models\TeachingAssignment;
use App\Services\CourseEnrollmentService;
use App\Services\InstitutionContext;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassRosterController extends Controller
{
    public function enroll(
        Request $request,
        CourseOffering $offering,
        InstitutionContext $context,
        CourseEnrollmentService $enrollments,
    ): RedirectResponse {
        $validated = $request->validate([
            'membership_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $institution = $context->current($request, $request->user());
        abort_unless($offering->institution_id === $institution->getKey(), 404);
        $membership = InstitutionMembership::query()
            ->where('institution_id', $institution->getKey())
            ->findOrFail($validated['membership_id']);
        $enrollments->enroll($request->user(), $offering, $membership, $validated['reason']);

        return back()->with('status', __('classes.messages.enrolled'));
    }

    public function status(
        Request $request,
        CourseOffering $offering,
        CourseEnrollment $enrollment,
        CourseEnrollmentService $enrollments,
    ): RedirectResponse {
        abort_unless($enrollment->course_offering_id === $offering->getKey(), 404);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(CourseEnrollmentStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $enrollments->changeStatus(
            $request->user(),
            $enrollment,
            CourseEnrollmentStatus::from($validated['status']),
            $validated['reason'],
        );

        return back()->with('status', __('classes.messages.roster_updated'));
    }

    public function transfer(
        Request $request,
        CourseOffering $offering,
        CourseEnrollment $enrollment,
        CourseEnrollmentService $enrollments,
    ): RedirectResponse {
        abort_unless($enrollment->course_offering_id === $offering->getKey(), 404);
        $validated = $request->validate([
            'target_offering_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $target = CourseOffering::query()
            ->where('institution_id', $offering->institution_id)
            ->findOrFail($validated['target_offering_id']);
        $enrollments->transfer($request->user(), $enrollment, $target, $validated['reason']);

        return back()->with('status', __('classes.messages.transferred'));
    }

    public function assignInstructor(
        Request $request,
        CourseOffering $offering,
        InstitutionContext $context,
        TeachingAssignmentService $assignments,
    ): RedirectResponse {
        $validated = $request->validate([
            'membership_id' => ['required', 'integer'],
            'role' => ['required', Rule::enum(TeachingAssignmentRole::class)],
        ]);
        $institution = $context->current($request, $request->user());
        abort_unless($offering->institution_id === $institution->getKey(), 404);
        $membership = InstitutionMembership::query()
            ->where('institution_id', $institution->getKey())
            ->findOrFail($validated['membership_id']);
        $assignments->assign(
            $request->user(),
            $offering,
            $membership,
            TeachingAssignmentRole::from($validated['role']),
        );

        return back()->with('status', __('classes.messages.instructor_assigned'));
    }

    public function revokeInstructor(
        Request $request,
        CourseOffering $offering,
        TeachingAssignment $assignment,
        TeachingAssignmentService $assignments,
    ): RedirectResponse {
        abort_unless($assignment->course_offering_id === $offering->getKey(), 404);
        $assignments->revoke($request->user(), $assignment);

        return back()->with('status', __('classes.messages.instructor_revoked'));
    }
}
