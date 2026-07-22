@extends('layouts.app')

@section('title', Auth::user()->isSuperAdmin() ? __('Superadmin Dashboard - Hospitrainity') : __('Admin Dashboard - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = Auth::user()->isAdmin() || $activeCanonicalPackage !== null)
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            @include('superadmin.canonical-curriculum-notice')
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-800">{{ __('admin.welcome_name', ['name' => Auth::user()->name]) }}</h1>
                <p class="text-neutral-500">{{ __('admin.platform_summary') }}</p>
                @include('partials.next-action', ['nextAction' => $nextAction])
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-500">{{ __('admin.total_users') }}</p>
                        <p class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['total_learners'] }}</p>
                    </div>
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                        <i class="fas fa-user fa-lg" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-500">{{ __('admin.total_supervisors') }}</p>
                        <p class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['total_supervisors'] }}</p>
                    </div>
                    <div class="bg-green-100 text-green-600 p-3 rounded-full">
                        <i class="fas fa-user-shield fa-lg" aria-hidden="true"></i>
                    </div>
                </div>
                @if(Auth::user()->isSuperAdmin())
                    <a href="{{ route('superadmin.institutions.index') }}" class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between hover:bg-neutral-50 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-neutral-500">{{ __('admin.total_institutions') }}</p>
                            <p class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['total_institutions'] }}</p>
                        </div>
                        <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">
                            <i class="fas fa-building fa-lg" aria-hidden="true"></i>
                        </div>
                    </a>
                @else
                    <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-neutral-500">{{ __('admin.total_institutions') }}</p>
                            <p class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['total_institutions'] }}</p>
                        </div>
                        <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">
                            <i class="fas fa-building fa-lg" aria-hidden="true"></i>
                        </div>
                    </div>
                @endif
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-500">{{ Auth::user()->isSuperAdmin() ? __('admin.active_canonical_chapters') : __('admin.learning_modules') }}</p>
                        <p class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['active_canonical_chapters'] }}</p>
                        @if(Auth::user()->isSuperAdmin())
                            <p class="mt-1 text-xs text-neutral-500">{{ $canonicalStatus['content_version'] ?? __('admin.no_active_canonical_package') }}</p>
                        @endif
                    </div>
                    <div class="bg-indigo-100 text-indigo-600 p-3 rounded-full">
                        <i class="fas fa-layer-group fa-lg" aria-hidden="true"></i>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
                <section class="rounded-lg bg-white p-6 shadow-md" aria-labelledby="canonical-status-title">
                    <h2 id="canonical-status-title" class="text-xl font-semibold text-neutral-900">{{ Auth::user()->isSuperAdmin() ? __('admin.canonical_content_counts') : __('admin.learning_content') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ Auth::user()->isSuperAdmin() ? __('Open canonical content to review published work or begin an authorized draft workflow.') : __('admin.learning_content_summary') }}</p>
                    @if(Auth::user()->isSuperAdmin())
                    <details class="mt-5 rounded-lg border border-neutral-300 p-4">
                        <summary class="cursor-pointer font-semibold text-indigo-800">{{ __('Technical evidence') }}</summary>
                    <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-neutral-500">{{ __('admin.active_delivery_version') }}</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $canonicalStatus['content_version'] ?? __('admin.no_active_canonical_package') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500">{{ __('admin.package_lifecycle_status') }}</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $canonicalStatus['lifecycle_status'] ?? __('admin.not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500">{{ __('admin.schema_version') }}</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $canonicalStatus['schema_version'] ?? __('admin.not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500">{{ __('admin.imported_at') }}</dt>
                            <dd class="mt-1 font-medium text-neutral-900">{{ $canonicalStatus['imported_at']?->format('Y-m-d H:i') ?? __('admin.not_available') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500">{{ __('admin.chapters') }}</dt>
                            <dd class="mt-1 text-2xl font-semibold text-neutral-900">{{ $canonicalStatus['chapters'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500">{{ __('admin.sections') }}</dt>
                            <dd class="mt-1 text-2xl font-semibold text-neutral-900">{{ $canonicalStatus['sections'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500">{{ __('admin.activities') }}</dt>
                            <dd class="mt-1 text-2xl font-semibold text-neutral-900">{{ $canonicalStatus['activities'] }}</dd>
                        </div>
                    </dl>

                    <h3 class="mt-6 border-t border-neutral-200 pt-4 text-sm font-semibold uppercase tracking-wide text-neutral-600">{{ __('admin.canonical_version_counts') }}</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><dt class="text-sm text-neutral-500">{{ __('admin.total_canonical_versions') }}</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $canonicalStatus['total_versions'] }}</dd></div>
                        <div><dt class="text-sm text-neutral-500">{{ __('admin.draft_lifecycle_versions') }}</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $canonicalStatus['draft_lifecycle_versions'] }}</dd></div>
                        <div><dt class="text-sm text-neutral-500">{{ __('admin.inactive_versions') }}</dt><dd class="mt-1 font-semibold text-neutral-900">{{ $canonicalStatus['inactive_versions'] }}</dd></div>
                    </dl>
                    </details>
                    @else
                        <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div><dt class="text-sm text-neutral-500">{{ __('admin.modules') }}</dt><dd class="mt-1 text-2xl font-semibold text-neutral-900">{{ $canonicalStatus['chapters'] }}</dd></div>
                            <div><dt class="text-sm text-neutral-500">{{ __('admin.lessons') }}</dt><dd class="mt-1 text-2xl font-semibold text-neutral-900">{{ $canonicalStatus['sections'] }}</dd></div>
                            <div><dt class="text-sm text-neutral-500">{{ __('admin.activities') }}</dt><dd class="mt-1 text-2xl font-semibold text-neutral-900">{{ $canonicalStatus['activities'] }}</dd></div>
                        </dl>
                    @endif
                </section>

                <section class="rounded-lg bg-white p-6 shadow-md" aria-labelledby="draft-workspace-title">
                    <h2 id="draft-workspace-title" class="text-xl font-semibold text-neutral-900">{{ __('admin.canonical_authoring_workspaces') }}</h2>
                    <p class="mt-1 text-sm text-neutral-600">{{ __('admin.canonical_authoring_workspaces_description') }}</p>
                    <dl class="mt-5 divide-y divide-neutral-200 rounded-md border border-neutral-200">
                        @foreach([
                            'total' => 'draft_workspaces_total',
                            'editable' => 'draft_workspaces_editable',
                            'in_review' => 'draft_workspaces_in_review',
                            'published' => 'draft_workspaces_published',
                            'available_entities' => 'available_draft_entities',
                            'archived_entities' => 'archived_draft_entities',
                        ] as $countKey => $labelKey)
                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                <dt class="text-sm text-neutral-600">{{ __('admin.'.$labelKey) }}</dt>
                                <dd class="font-semibold tabular-nums text-neutral-900">{{ $draftWorkspaceCounts[$countKey] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <a href="{{ route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.index') }}" class="mt-5 inline-block font-semibold text-indigo-700 underline">{{ __('admin.open_canonical_content') }}</a>
                </section>

                @if(Auth::user()->isSuperAdmin())
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-6 shadow-md" aria-labelledby="legacy-evidence-title">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="legacy-evidence-title" class="text-xl font-semibold text-amber-950">{{ __('admin.legacy_evidence') }}</h2>
                            <p class="mt-1 text-sm text-amber-900">{{ __('admin.legacy_evidence_summary') }}</p>
                        </div>
                        <span class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-semibold text-amber-900">{{ __('admin.read_only') }}</span>
                    </div>
                    <dl class="mt-5 divide-y divide-amber-200 rounded-md border border-amber-200 bg-white">
                        @foreach([
                            'modules' => 'legacy_modules',
                            'lessons' => 'legacy_lessons',
                            'vocabularies' => 'legacy_vocabulary',
                            'materials' => 'legacy_materials',
                            'exercises' => 'legacy_exercises',
                        ] as $countKey => $labelKey)
                            <div class="flex items-center justify-between gap-4 px-4 py-3">
                                <dt class="text-sm text-neutral-600">{{ __("admin.{$labelKey}") }}</dt>
                                <dd class="font-semibold tabular-nums text-neutral-900">{{ $legacyEvidenceCounts[$countKey] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <a href="{{ route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.legacy-evidence.index') }}" class="mt-5 inline-block font-semibold text-indigo-800 underline">{{ __('admin.open_legacy_evidence') }}</a>
                </section>
                @endif
            </div>
        </main>
    </div>
@endsection
