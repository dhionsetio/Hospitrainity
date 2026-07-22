@extends('layouts.app')

@section('title', $offering->title.' - Hospitrainity')
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php
        $primaryAssignment = $offering->teachingAssignments->first(fn ($assignment) => $assignment->role === \App\Enums\TeachingAssignmentRole::Primary);
        $pendingRequests = $offering->joinRequests->filter(fn ($joinRequest) => $joinRequest->status === \App\Enums\InstitutionJoinRequestStatus::Pending);
        $availableCodes = $offering->joinCodes->filter(fn ($code) => $code->isRedeemable());
        $availableInvitations = $offering->invitations->filter(fn ($invitation) => $invitation->isRedeemable());
    @endphp
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('supervisor.sidebar')

        <main class="min-w-0 flex-1 p-4 pb-24 sm:p-6 md:p-10">
            <div class="mx-auto max-w-6xl">
                <a href="{{ route('supervisor.classes.index') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-800 hover:text-indigo-950"><i class="fas fa-arrow-left" aria-hidden="true"></i>{{ __('classes.back_to_classes') }}</a>

                <header class="mt-4 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="font-semibold text-indigo-700">{{ $offering->course->title }}</p>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold uppercase tracking-wide text-neutral-700 shadow-sm">{{ __('classes.status.'.$offering->status->value) }}</span>
                        </div>
                        <h1 class="mt-2 text-3xl font-bold text-neutral-950">{{ $offering->title }}</h1>
                        @if($offering->term_label)<p class="mt-2 text-neutral-600">{{ $offering->term_label }}</p>@endif
                    </div>
                    <a href="{{ route('supervisor.classes.preview', $offering) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-indigo-700 bg-white px-5 py-2.5 font-semibold text-indigo-800 hover:bg-indigo-50"><i class="fas fa-eye" aria-hidden="true"></i>{{ __('classes.preview') }}</a>
                </header>

                @if(session('status'))<div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 font-semibold text-emerald-950" role="status">{{ session('status') }}</div>@endif
                @if($errors->any())
                    <div class="mt-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950" role="alert">
                        <p class="font-bold">{{ __('Please correct the highlighted fields.') }}</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @unless($canManage)<p class="mt-6 rounded-lg border border-neutral-300 bg-white p-4 font-semibold text-neutral-700">{{ __('classes.read_only') }}</p>@endunless

                <div class="mt-8 grid gap-6 xl:grid-cols-2">
                    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="settings-heading">
                        <h2 id="settings-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.settings') }}</h2>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-neutral-500">{{ __('classes.class_key') }}</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $offering->key }}</dd></div>
                            <div><dt class="text-neutral-500">{{ __('classes.timezone') }}</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $offering->timezone ?? $institution->timezone ?? 'UTC' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-neutral-500">{{ __('classes.primary_instructor') }}</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $primaryAssignment?->membership?->user?->name ?? __('classes.unassigned') }}</dd></div>
                        </dl>
                        @if($canManage)
                            <form method="POST" action="{{ route('supervisor.classes.update', $offering) }}" class="mt-5 space-y-4">
                                @csrf @method('PATCH')
                                <label class="block font-semibold text-neutral-900">{{ __('classes.class_title') }}<input name="title" value="{{ old('title', $offering->title) }}" required maxlength="180" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.term_label') }}<input name="term_label" value="{{ old('term_label', $offering->term_label) }}" maxlength="120" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.timezone') }}<input name="timezone" value="{{ old('timezone', $offering->timezone) }}" maxlength="64" placeholder="Asia/Jakarta" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"><span class="mt-1 block text-xs font-normal text-neutral-500">{{ __('classes.timezone_hint') }}</span></label>
                                <button class="min-h-11 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800">{{ __('classes.save_settings') }}</button>
                            </form>
                        @endif
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="lifecycle-heading">
                        <h2 id="lifecycle-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.lifecycle') }}</h2>
                        <p class="mt-2 text-sm text-neutral-600">{{ __('classes.lifecycle_intro') }}</p>
                        @if($canManage && $transitionTargets !== [])
                            <form method="POST" action="{{ route('supervisor.classes.transition', $offering) }}" class="mt-5 space-y-4">
                                @csrf
                                <input type="hidden" name="expected_status" value="{{ $offering->status->value }}">
                                <label class="block font-semibold text-neutral-900">{{ __('classes.target_state') }}<select name="target_status" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300">@foreach($transitionTargets as $target)<option value="{{ $target->value }}">{{ __('classes.status.'.$target->value) }}</option>@endforeach</select></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.transition_reason') }}<textarea name="reason" required maxlength="500" rows="3" class="mt-2 w-full rounded-lg border-neutral-300">{{ old('reason') }}</textarea></label>
                                <button class="min-h-11 rounded-lg bg-neutral-900 px-5 py-2.5 font-semibold text-white hover:bg-neutral-800">{{ __('classes.change_lifecycle') }}</button>
                            </form>
                        @elseif($transitionTargets === [])
                            <p class="mt-5 rounded-lg border border-dashed border-neutral-300 p-4 text-neutral-600">{{ __('classes.no_transitions') }}</p>
                        @endif
                        @if($offering->events->isNotEmpty())
                            <details class="mt-5 rounded-lg border border-neutral-200 p-4"><summary class="min-h-11 cursor-pointer font-semibold text-neutral-900">{{ __('classes.history') }}</summary><ol class="mt-3 space-y-3 text-sm">@foreach($offering->events->reverse() as $event)<li><span class="font-semibold">{{ __('classes.status.'.$event->from_status->value) }} → {{ __('classes.status.'.$event->to_status->value) }}</span><span class="block text-neutral-600">{{ $event->reason }} · {{ $event->created_at->format('Y-m-d H:i') }}</span></li>@endforeach</ol></details>
                        @endif
                    </section>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-2">
                    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="class-content-heading">
                        <h2 id="class-content-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.class_content') }}</h2>
                        <p class="mt-2 text-sm text-neutral-600">{{ __('classes.class_content_intro') }}</p>

                        <h3 class="mt-5 font-bold text-neutral-950">{{ __('classes.current_modules') }}</h3>
                        <ol class="mt-3 space-y-2">
                            @foreach($offering->revision->modules as $revisionModule)
                                <li class="flex items-start gap-3 rounded-lg bg-neutral-50 px-4 py-3 text-neutral-800">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-800">{{ $loop->iteration }}</span>
                                    <span class="pt-0.5 font-semibold">{{ $revisionModule->curriculumEntity->payloadData()['title'] }}</span>
                                </li>
                            @endforeach
                        </ol>

                        @if($canReviseContent && $activePackage !== null)
                            @php
                                $selectedModuleIds = collect(old('module_ids', $offering->revision->modules->pluck('curriculum_entity_id')->all()))
                                    ->map(fn ($id) => (string) $id);
                            @endphp
                            <details class="mt-5 rounded-lg border border-neutral-200 p-4" @if($errors->has('content') || $errors->has('module_ids')) open @endif>
                                <summary class="flex min-h-11 cursor-pointer items-center font-bold text-neutral-950">{{ __('classes.change_modules') }}</summary>
                                <p class="mt-2 text-sm text-neutral-600">{{ __('classes.change_modules_hint') }}</p>
                                <form method="POST" action="{{ route('supervisor.classes.content-revisions.store', $offering) }}" class="mt-4 space-y-4">
                                    @csrf
                                    <input type="hidden" name="curriculum_package_id" value="{{ $activePackage->getKey() }}">
                                    <fieldset>
                                        <legend class="font-semibold text-neutral-900">{{ __('classes.current_modules') }}</legend>
                                        <div class="mt-2 space-y-2">
                                            @foreach($availableModules as $module)
                                                <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-neutral-200 p-3 hover:bg-neutral-50">
                                                    <input type="checkbox" name="module_ids[]" class="mt-1 shrink-0 rounded border-neutral-400 text-indigo-700 focus:ring-indigo-600" value="{{ $module->getKey() }}" @checked($selectedModuleIds->contains((string) $module->getKey()))>
                                                    <span class="font-semibold text-neutral-900">{{ $module->payloadData()['title'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <label class="block font-semibold text-neutral-900">{{ __('classes.revision_title') }}<input name="revision_title" value="{{ old('revision_title', $offering->title) }}" required maxlength="180" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
                                    <label class="block font-semibold text-neutral-900">{{ __('classes.content_change_reason') }}<textarea name="reason" required maxlength="500" rows="3" class="mt-2 w-full rounded-lg border-neutral-300">{{ old('reason') }}</textarea></label>
                                    <button class="min-h-11 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800">{{ __('classes.save_content_version') }}</button>
                                </form>
                            </details>
                        @elseif($canManage)
                            <p class="mt-5 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700">{{ __('classes.content_locked') }}</p>
                        @endif
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="class-instructions-heading">
                        <h2 id="class-instructions-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.instructions') }}</h2>
                        <p class="mt-2 text-sm text-neutral-600">{{ __('classes.instructions_intro') }}</p>

                        @if($canManage && ! in_array($offering->status, [\App\Enums\CourseOfferingStatus::Closed, \App\Enums\CourseOfferingStatus::Archived], true))
                            <form method="POST" action="{{ route('supervisor.classes.instructions.store', $offering) }}" class="mt-5 space-y-4 rounded-lg bg-neutral-50 p-4">
                                @csrf
                                <label class="block font-semibold text-neutral-900">{{ __('classes.instruction_title') }}<input name="instruction_title" value="{{ old('instruction_title') }}" required maxlength="180" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.instruction_text') }}<textarea name="instruction_body" required maxlength="5000" rows="4" class="mt-2 w-full rounded-lg border-neutral-300">{{ old('instruction_body') }}</textarea></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.instruction_module') }}<select name="instruction_module_id" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"><option value="">{{ __('classes.class_wide') }}</option>@foreach($offering->revision->modules as $revisionModule)<option value="{{ $revisionModule->curriculum_entity_id }}" @selected((string) old('instruction_module_id') === (string) $revisionModule->curriculum_entity_id)>{{ $revisionModule->curriculumEntity->payloadData()['title'] }}</option>@endforeach</select></label>
                                <button class="min-h-11 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800">{{ __('classes.post_instruction') }}</button>
                            </form>
                        @endif

                        <div class="mt-5 divide-y divide-neutral-200">
                            @forelse($offering->announcements as $announcement)
                                <article class="py-4 first:pt-0 last:pb-0">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-indigo-700">{{ $announcement->module?->payloadData()['title'] ?? __('classes.class_wide') }}</p>
                                            <h3 class="mt-1 font-bold text-neutral-950">{{ $announcement->title }}</h3>
                                        </div>
                                        @if($announcement->archived_at !== null)<span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-neutral-600">{{ __('classes.archived') }}</span>@endif
                                    </div>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-neutral-700">{{ $announcement->body }}</p>
                                    <p class="mt-2 text-xs text-neutral-500">{{ __('classes.posted_by', ['name' => $announcement->createdBy?->name ?? __('Instructor')]) }}</p>
                                    @if($canManage && $announcement->archived_at === null)
                                        <form method="POST" action="{{ route('supervisor.classes.instructions.archive', [$offering, $announcement]) }}" class="mt-3">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="expected_revision" value="{{ $announcement->revision }}">
                                            <button class="min-h-11 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-800 hover:bg-neutral-50">{{ __('classes.archive_instruction') }}</button>
                                        </form>
                                    @endif
                                </article>
                            @empty
                                <p class="py-4 text-sm text-neutral-600">{{ __('classes.no_instructions') }}</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <section class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="teaching-team-heading">
                    <h2 id="teaching-team-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.teaching_team') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('classes.teaching_team_intro') }}</p>
                    <ul class="mt-5 grid gap-3 md:grid-cols-2">
                        @foreach($offering->teachingAssignments as $assignment)
                            <li class="flex items-center justify-between gap-4 rounded-lg border border-neutral-200 p-4"><div><p class="font-bold text-neutral-950">{{ $assignment->membership->user->name }}</p><p class="mt-1 text-sm text-neutral-600">{{ __('classes.role.'.$assignment->role->value) }}</p></div>@if($canManage && $assignment->role === \App\Enums\TeachingAssignmentRole::CoInstructor)<form method="POST" action="{{ route('supervisor.classes.instructors.destroy', [$offering, $assignment]) }}">@csrf @method('DELETE')<button class="min-h-11 rounded-lg border border-red-300 px-4 py-2 font-semibold text-red-800 hover:bg-red-50">{{ __('classes.revoke') }}</button></form>@endif</li>
                        @endforeach
                    </ul>
                    @if($canManage)
                        <form method="POST" action="{{ route('supervisor.classes.instructors.store', $offering) }}" class="mt-5 grid gap-4 rounded-lg bg-neutral-50 p-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                            @csrf
                            <label class="font-semibold text-neutral-900">{{ __('classes.assign_instructor') }}<select name="membership_id" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"><option value="">{{ __('classes.unassigned') }}</option>@foreach($instructors as $instructor)<option value="{{ $instructor->id }}">{{ $instructor->user->name }}</option>@endforeach</select></label>
                            <label class="font-semibold text-neutral-900">{{ __('classes.assignment_role') }}<select name="role" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"><option value="co_instructor">{{ __('classes.role.co_instructor') }}</option><option value="primary">{{ __('classes.role.primary') }}</option></select></label>
                            <button class="min-h-11 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800">{{ __('classes.assign_instructor') }}</button>
                            <p class="text-xs text-neutral-600 md:col-span-3">{{ __('classes.replace_primary') }}</p>
                        </form>
                    @endif
                </section>

                <section class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="roster-heading">
                    <h2 id="roster-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.roster') }}</h2>
                    <p class="mt-2 max-w-3xl text-sm text-neutral-600">{{ __('classes.roster_intro') }}</p>
                    @if($canManage && $offering->acceptsEnrollments())
                        @if($availableLearners->isNotEmpty())
                            <form method="POST" action="{{ route('supervisor.classes.enrollments.store', $offering) }}" class="mt-5 grid gap-4 rounded-lg bg-neutral-50 p-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                                @csrf
                                <label class="font-semibold text-neutral-900">{{ __('classes.select_learner') }}<select name="membership_id" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300">@foreach($availableLearners as $learner)<option value="{{ $learner->id }}">{{ $learner->user->name }}</option>@endforeach</select></label>
                                <label class="font-semibold text-neutral-900">{{ __('classes.enrollment_reason') }}<input name="reason" required maxlength="500" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
                                <button class="min-h-11 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800">{{ __('classes.enroll') }}</button>
                            </form>
                        @else
                            <p class="mt-5 rounded-lg border border-dashed border-neutral-300 p-4 text-sm text-neutral-600">{{ __('classes.no_available_learners') }}</p>
                        @endif
                    @endif
                    <div class="mt-5 space-y-4">
                        @forelse($enrollments as $enrollment)
                            <article class="rounded-lg border border-neutral-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-bold text-neutral-950">{{ $enrollment->membership->user->name }}</h3><p class="mt-1 text-sm text-neutral-600">{{ __('classes.learner_role') }} · {{ __('classes.joined') }} {{ $enrollment->enrolled_at->format('Y-m-d') }}</p></div><span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-neutral-700">{{ __('classes.status.'.$enrollment->status->value) }}</span></div>
                                <a href="{{ route('supervisor.classes.enrollments.progress', [$offering, $enrollment]) }}" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-4 py-2 text-sm font-semibold text-indigo-800 hover:bg-indigo-50">{{ __('View Class progress') }}</a>
                                @if($canManage && $enrollment->status !== \App\Enums\CourseEnrollmentStatus::Withdrawn)
                                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                        <details class="rounded-lg border border-neutral-200 p-3"><summary class="min-h-11 cursor-pointer font-semibold text-neutral-900">{{ __('classes.change_status') }}</summary><form method="POST" action="{{ route('supervisor.classes.enrollments.status', [$offering, $enrollment]) }}" class="mt-3 space-y-3">@csrf @method('PATCH')<select name="status" required class="min-h-11 w-full rounded-lg border-neutral-300">@if($enrollment->status === \App\Enums\CourseEnrollmentStatus::Suspended)<option value="active">{{ __('classes.status.active') }}</option>@else<option value="suspended">{{ __('classes.status.suspended') }}</option>@endif<option value="withdrawn">{{ __('classes.status.withdrawn') }}</option></select><textarea name="reason" required maxlength="500" rows="2" aria-label="{{ __('classes.enrollment_reason') }}" class="w-full rounded-lg border-neutral-300"></textarea><button class="min-h-11 rounded-lg border border-indigo-700 px-4 py-2 font-semibold text-indigo-800">{{ __('classes.apply_status') }}</button></form></details>
                                        @if($transferTargets->isNotEmpty())<details class="rounded-lg border border-neutral-200 p-3"><summary class="min-h-11 cursor-pointer font-semibold text-neutral-900">{{ __('classes.transfer') }}</summary><form method="POST" action="{{ route('supervisor.classes.enrollments.transfer', [$offering, $enrollment]) }}" class="mt-3 space-y-3">@csrf<select name="target_offering_id" required aria-label="{{ __('classes.transfer_target') }}" class="min-h-11 w-full rounded-lg border-neutral-300">@foreach($transferTargets as $target)<option value="{{ $target->id }}">{{ $target->title }}</option>@endforeach</select><textarea name="reason" required maxlength="500" rows="2" aria-label="{{ __('classes.enrollment_reason') }}" class="w-full rounded-lg border-neutral-300"></textarea><button class="min-h-11 rounded-lg border border-indigo-700 px-4 py-2 font-semibold text-indigo-800">{{ __('classes.transfer_learner') }}</button></form></details>@endif
                                    </div>
                                @endif
                                @if($enrollment->events->isNotEmpty())<details class="mt-3"><summary class="min-h-11 cursor-pointer text-sm font-semibold text-neutral-700">{{ __('classes.history') }}</summary><ol class="mt-2 space-y-2 text-sm text-neutral-600">@foreach($enrollment->events->reverse() as $event)<li>{{ $event->from_status ? __('classes.status.'.$event->from_status->value).' → ' : '' }}{{ __('classes.status.'.$event->to_status->value) }} · {{ $event->reason }} · {{ $event->created_at->format('Y-m-d H:i') }}</li>@endforeach</ol></details>@endif
                            </article>
                        @empty
                            <p class="rounded-lg border border-dashed border-neutral-300 p-6 text-center text-neutral-600">{{ __('classes.no_roster') }}</p>
                        @endforelse
                    </div>
                    @if($enrollments->hasPages())
                        <div class="mt-5">{{ $enrollments->links() }}</div>
                    @endif
                </section>

                @if($canManage && $offering->acceptsEnrollments())
                    <section class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="connections-heading">
                        <h2 id="connections-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.connections') }}</h2><p class="mt-2 max-w-3xl text-sm text-neutral-600">{{ __('classes.connections_intro') }}</p>
                        @if(session('issued_join_code'))<div class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status"><p class="font-semibold">{{ __('classes.issued_code') }}</p><code class="mt-2 block break-all text-lg font-bold">{{ session('issued_join_code') }}</code></div>@endif
                        <div class="mt-5 grid gap-5 lg:grid-cols-2">
                            <form method="POST" action="{{ route('supervisor.classes.invitations.store', $offering) }}" class="rounded-lg border border-neutral-200 p-4">@csrf<h3 class="font-bold text-neutral-950">{{ __('classes.send_invitation') }}</h3><label class="mt-3 block font-semibold text-neutral-900">{{ __('classes.invite_email') }}<input type="email" name="email" required maxlength="255" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label><button class="mt-4 min-h-11 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white">{{ __('classes.send_invitation') }}</button></form>
                            <form method="POST" action="{{ route('supervisor.classes.join-codes.store', $offering) }}" class="rounded-lg border border-neutral-200 p-4">@csrf<h3 class="font-bold text-neutral-950">{{ __('classes.create_code') }}</h3><div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach(['duration_days' => 'days', 'duration_hours' => 'hours', 'duration_minutes' => 'minutes', 'duration_seconds' => 'seconds'] as $field => $label)<label class="text-sm font-semibold text-neutral-900">{{ __('classes.'.$label) }}<input type="number" name="{{ $field }}" min="0" value="{{ $field === 'duration_hours' ? 1 : 0 }}" required class="mt-1 min-h-11 w-full rounded-lg border-neutral-300"></label>@endforeach</div><label class="mt-3 block font-semibold text-neutral-900">{{ __('classes.use_limit') }}<input type="number" name="use_limit" min="1" max="500" value="30" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label><button class="mt-4 min-h-11 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white">{{ __('classes.create_code') }}</button></form>
                        </div>
                        <div class="mt-6 grid gap-5 xl:grid-cols-3">
                            <div><h3 class="font-bold text-neutral-950">{{ __('classes.pending_requests') }}</h3><ul class="mt-3 space-y-3">@forelse($pendingRequests as $joinRequest)<li class="rounded-lg border border-neutral-200 p-3"><p class="font-semibold text-neutral-900">{{ $joinRequest->user->name }}</p><div class="mt-3 flex gap-2"><form method="POST" action="{{ route('supervisor.classes.join-requests.update', [$offering, $joinRequest]) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="min-h-11 rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">{{ __('classes.approve') }}</button></form><form method="POST" action="{{ route('supervisor.classes.join-requests.update', [$offering, $joinRequest]) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button class="min-h-11 rounded-lg border border-red-300 px-4 py-2 font-semibold text-red-800">{{ __('classes.reject') }}</button></form></div></li>@empty<li class="text-sm text-neutral-600">{{ __('classes.no_pending_requests') }}</li>@endforelse</ul></div>
                            <div><h3 class="font-bold text-neutral-950">{{ __('classes.active_codes') }}</h3><ul class="mt-3 space-y-3">@forelse($availableCodes as $code)<li class="rounded-lg border border-neutral-200 p-3"><p class="font-semibold text-neutral-900">{{ __('classes.code_suffix', ['suffix' => $code->display_suffix]) }}</p><p class="mt-1 text-sm text-neutral-600">{{ __('classes.uses', ['used' => $code->use_count, 'limit' => $code->use_limit]) }} · {{ __('classes.expires', ['time' => $code->expires_at->format('Y-m-d H:i')]) }}</p><form method="POST" action="{{ route('supervisor.classes.join-codes.destroy', [$offering, $code]) }}" class="mt-2">@csrf @method('DELETE')<button class="min-h-11 font-semibold text-red-800 underline">{{ __('classes.revoke') }}</button></form></li>@empty<li class="text-sm text-neutral-600">{{ __('None') }}</li>@endforelse</ul></div>
                            <div><h3 class="font-bold text-neutral-950">{{ __('classes.active_invitations') }}</h3><ul class="mt-3 space-y-3">@forelse($availableInvitations as $invitation)<li class="rounded-lg border border-neutral-200 p-3"><p class="font-semibold text-neutral-900">{{ $maskedInvitationTargets[$invitation->getKey()] }}</p><p class="mt-1 text-sm text-neutral-600">{{ __('classes.invited_by', ['name' => $invitation->issuer->name]) }} · {{ __('classes.expires', ['time' => $invitation->expires_at->format('Y-m-d H:i')]) }}</p><form method="POST" action="{{ route('supervisor.classes.invitations.destroy', [$offering, $invitation]) }}" class="mt-2">@csrf @method('DELETE')<button class="min-h-11 font-semibold text-red-800 underline">{{ __('classes.revoke') }}</button></form></li>@empty<li class="text-sm text-neutral-600">{{ __('None') }}</li>@endforelse</ul></div>
                        </div>
                    </section>
                @endif

                @if($canManage)
                    <section class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="copy-heading"><h2 id="copy-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.copy') }}</h2><p class="mt-2 max-w-3xl text-sm text-neutral-600">{{ __('classes.copy_intro') }}</p><form method="POST" action="{{ route('supervisor.classes.copy', $offering) }}" class="mt-5 grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">@csrf<label class="font-semibold text-neutral-900">{{ __('classes.class_title') }}<input name="class_title" required maxlength="180" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label><label class="font-semibold text-neutral-900">{{ __('classes.class_key') }}<input name="class_key" required maxlength="100" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label><label class="font-semibold text-neutral-900">{{ __('classes.primary_instructor') }}<select name="primary_instructor_membership_id" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300">@foreach($instructors as $instructor)<option value="{{ $instructor->id }}">{{ $instructor->user->name }}</option>@endforeach</select></label><button class="min-h-11 rounded-lg bg-neutral-900 px-5 py-2.5 font-semibold text-white">{{ __('classes.copy_class') }}</button></form></section>
                @endif
            </div>
        </main>
    </div>
@endsection
