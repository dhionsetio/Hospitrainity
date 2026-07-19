<aside class="border-y border-violet-300 bg-violet-50 px-4 py-3 text-violet-950" role="note" aria-label="{{ __('admin.draft_preview') }}">
    <div class="container mx-auto flex flex-wrap items-center justify-between gap-3">
        <p><strong>{{ __('admin.draft_preview') }}:</strong> {{ __('admin.preview_does_not_record_progress') }}</p>
        <a href="{{ route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.show', $curriculumPreview) }}" class="rounded-md border border-violet-500 bg-white px-4 py-2 font-semibold text-violet-900 hover:bg-violet-100">{{ __('admin.return_to_draft') }}</a>
    </div>
</aside>
