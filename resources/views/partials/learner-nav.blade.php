<nav class="bg-white shadow-md">
        <div class="container mx-auto flex flex-wrap items-center justify-between gap-y-3 px-6 py-3">
            <!-- Logo -->
            <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.show', $curriculumPreview) : route('dashboard') }}" class="text-2xl font-bold text-indigo-600">Hospitrainity</a>

            <!-- Profile Section -->
            <div class="flex items-center gap-4">
                @if(!isset($curriculumPreview) && app(\App\Services\WorkContext::class)->current(request(), Auth::user()) === \App\Enums\WorkContextRole::Learner)
                    @php($learningService = app(\App\Services\LearningContext::class))
                    @php($learningMemberships = $learningService->availableMemberships(Auth::user()))
                    @php($learningContext = $learningService->current(request(), Auth::user()))
                    @if($learningMemberships->isNotEmpty())
                        <form method="POST" action="{{ route('learning-context.select') }}" class="order-last flex w-full items-center gap-2 sm:order-none sm:w-auto" aria-label="{{ __('Switch learning context') }}">
                            @csrf
                            <label for="learner-context" class="sr-only">{{ __('Learning context') }}</label>
                            <select id="learner-context" name="context" class="min-w-0 flex-1 rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm sm:max-w-56">
                                <option value="personal" @selected($learningContext['membership_id'] === null)>{{ __('Personal self-study') }}</option>
                                @foreach($learningMemberships as $membership)
                                    <option value="membership:{{ $membership->id }}" @selected($learningContext['membership_id'] === $membership->id)>{{ $membership->institution->displayName(app()->getLocale()) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md border border-indigo-600 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">{{ __('Switch') }}</button>
                        </form>
                    @endif
                @endif
                <p class="sr-only text-neutral-700 sm:not-sr-only">{{ Auth::user()->name }}</p>

                <!-- Wadah Relative untuk Dropdown -->
                <div class="relative">
                    <button id="profile-button" type="button" class="flex items-center rounded" aria-controls="dropdown-menu" aria-expanded="false" aria-haspopup="menu" aria-label="{{ __('Open user menu') }}">
                        <span aria-hidden="true" class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
                            {{ mb_strtoupper(mb_substr(Auth::user()->name ?? 'G', 0, 1)) }}
                        </span>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="dropdown-menu" role="menu" aria-labelledby="profile-button" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                        <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.show', $curriculumPreview) : route('dashboard') }}" role="menuitem" tabindex="-1" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">{{ isset($curriculumPreview) ? __('admin.return_to_draft') : __('Dashboard') }}</a>
                        @if(!isset($curriculumPreview) && app(\App\Services\WorkContext::class)->current(request(), Auth::user()) === \App\Enums\WorkContextRole::Learner)
                            <a href="{{ route('institution-enrollment.index') }}" role="menuitem" tabindex="-1" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">{{ __('Institution connections') }}</a>
                        @endif
                        @if(!isset($curriculumPreview) && app(\App\Services\WorkContext::class)->hasAlternativeRole(Auth::user()))
                            <a href="{{ route('work-context.index') }}" role="menuitem" tabindex="-1" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">{{ __('Switch role') }}</a>
                        @endif
                        <a href="{{ route('security.index') }}" role="menuitem" tabindex="-1" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">{{ __('Account security') }}</a>
                        <div role="separator" class="border-t border-neutral-200 my-1"></div>

                        <!-- FORM LOGOUT DIMULAI DI SINI -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" role="menuitem" tabindex="-1"
                                class="block w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>
