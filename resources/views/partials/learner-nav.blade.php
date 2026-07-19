<nav class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-3 flex justify-between items-center">
            <!-- Logo -->
            <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.show', $curriculumPreview) : route('dashboard') }}" class="text-2xl font-bold text-indigo-600">Hospitrainity</a>

            <!-- Profile Section -->
            <div class="flex items-center gap-4">
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
