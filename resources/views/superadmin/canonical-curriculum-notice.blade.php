@php($activeCanonicalPackage = $activeCanonicalPackage ?? \App\Models\CurriculumPackage::active())

@if ($activeCanonicalPackage)
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="note">
        <p class="font-semibold">{{ __('admin.canonical_active_notice') }}</p>
        <p class="mt-1">{{ __('admin.canonical_active_notice_detail') }}</p>
        <p class="mt-2 break-all font-mono text-xs">{{ $activeCanonicalPackage->content_version }} · {{ $activeCanonicalPackage->lifecycle_status }} · {{ $activeCanonicalPackage->source_tree_sha256 }}</p>
    </div>
@endif
