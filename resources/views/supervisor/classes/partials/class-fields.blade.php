<label class="block font-semibold text-neutral-900">{{ __('classes.class_title') }}<input name="class_title" value="{{ old('class_title') }}" required maxlength="180" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
<label class="block font-semibold text-neutral-900">{{ __('classes.class_key') }}<input name="class_key" value="{{ old('class_key') }}" required maxlength="100" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300" autocomplete="off"></label>
<label class="block font-semibold text-neutral-900">{{ __('classes.term_label') }}<input name="term_label" value="{{ old('term_label') }}" maxlength="120" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
<label class="block font-semibold text-neutral-900">{{ __('classes.timezone') }}<input name="timezone" value="{{ old('timezone') }}" maxlength="64" placeholder="Asia/Jakarta" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"><span class="mt-1 block text-xs font-normal text-neutral-500">{{ __('classes.timezone_hint') }}</span></label>
<label class="block font-semibold text-neutral-900">
    {{ __('classes.primary_instructor') }}
    <select name="primary_instructor_membership_id" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300">
        <option value="">{{ __('classes.unassigned') }}</option>
        @foreach($instructors as $instructor)
            <option value="{{ $instructor->id }}" @selected((string) old('primary_instructor_membership_id') === (string) $instructor->id)>{{ $instructor->user->name }}</option>
        @endforeach
    </select>
</label>
