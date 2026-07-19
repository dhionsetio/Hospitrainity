@if ($errors->any())
    <div id="admin-form-errors" class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900" role="alert" aria-live="assertive" tabindex="-1">
        <p class="font-semibold">{{ __('Please correct the following errors:') }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
