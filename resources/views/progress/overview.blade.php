<section aria-labelledby="progress-overview-title">
    <h2 id="progress-overview-title" class="sr-only">{{ __('admin.progress_overview') }}</h2>
    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            'total_learners' => 'total_users',
            'active_learners' => 'learners_with_progress',
            'tracked_activities' => 'tracked_activities',
            'completed_activities' => 'completed_activities',
            'attempt_count' => 'attempts',
        ] as $key => $label)
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <dt class="text-sm font-medium text-neutral-500">{{ __('admin.'.$label) }}</dt>
                <dd class="mt-1 text-3xl font-semibold tabular-nums text-neutral-900">{{ number_format($overview[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
    <div class="mt-4 rounded-lg bg-white p-5 shadow-sm">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-neutral-500">{{ auth()->user()?->isSuperAdmin() ? __('admin.active_version_completion') : __('admin.overall_progress') }}</dt>
                <dd class="mt-1 font-semibold text-neutral-900">
                    {{ $overview['active_completion_percent'] }}%
                    @if(auth()->user()?->isSuperAdmin())
                        @if($overview['active_package'])
                            <span class="font-normal text-neutral-500">({{ $overview['active_package']->package_name }} {{ $overview['active_package']->content_version }})</span>
                        @else
                            <span class="font-normal text-neutral-500">({{ __('admin.no_active_canonical_package') }})</span>
                        @endif
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-neutral-500">{{ __('admin.last_activity') }}</dt>
                <dd class="mt-1 font-semibold text-neutral-900">
                    {{ $overview['last_activity_at'] ? \Illuminate\Support\Carbon::parse($overview['last_activity_at'])->format('Y-m-d H:i') : __('admin.no_activity_recorded') }}
                </dd>
            </div>
        </dl>
    </div>
</section>
