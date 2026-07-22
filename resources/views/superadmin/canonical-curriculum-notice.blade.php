@php($activeCanonicalPackage = $activeCanonicalPackage ?? \App\Models\CurriculumPackage::active())

@if ($activeCanonicalPackage)
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="note">
        <p class="font-semibold">{{ Auth::user()->isSuperAdmin() ? __('admin.canonical_active_notice') : __('admin.learning_content_active_notice') }}</p>
        <p class="mt-1">{{ Auth::user()->isSuperAdmin() ? __('admin.canonical_active_notice_detail') : __('admin.learning_content_active_notice_detail') }}</p>
        @if(Auth::user()->isSuperAdmin())
            <p class="mt-2 break-all font-mono text-xs">{{ $activeCanonicalPackage->content_version }} <span aria-hidden="true">·</span> {{ $activeCanonicalPackage->lifecycle_status }} <span aria-hidden="true">·</span> {{ $activeCanonicalPackage->source_tree_sha256 }}</p>
        @endif
    </div>
@endif
