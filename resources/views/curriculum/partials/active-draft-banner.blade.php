@php($activeRelease = $activePackage->release)
@if(!app()->isProduction() && (
    strtolower(trim((string) $activePackage->lifecycle_status)) === 'draft'
    || $activeRelease === null
    || $activeRelease->state !== \App\Enums\CurriculumReleaseState::Active
    || $activeRelease->preview_only
))
    <aside class="border-y border-amber-400 bg-amber-100 px-4 py-3 text-amber-950" role="note" aria-label="{{ __('Non-production draft preview') }}">
        <div class="container mx-auto">
            <p class="font-semibold">{{ __('Non-production draft preview') }}</p>
            <p class="text-sm">{{ __('These learning materials have not completed the required human approval gates and must not be treated as a production release.') }}</p>
        </div>
    </aside>
@endif
