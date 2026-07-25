@if(is_array($value))
    @if($value === [])
        <span class="text-neutral-600">{{ __('No stored content') }}</span>
    @elseif(array_is_list($value))
        <ol class="list-decimal space-y-2 pl-5">
            @foreach($value as $item)
                <li class="pl-1">
                    @include('superadmin.partials.legacy-structured-value', ['value' => $item])
                </li>
            @endforeach
        </ol>
    @else
        <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[minmax(9rem,auto)_1fr]">
            @foreach($value as $key => $item)
                <dt class="font-semibold text-neutral-800">{{ \Illuminate\Support\Str::headline((string) $key) }}</dt>
                <dd class="min-w-0 break-words text-neutral-800">
                    @include('superadmin.partials.legacy-structured-value', ['value' => $item])
                </dd>
            @endforeach
        </dl>
    @endif
@elseif(is_bool($value))
    {{ $value ? __('Yes') : __('No') }}
@elseif($value === null || $value === '')
    <span class="text-neutral-600">{{ __('Not available') }}</span>
@else
    <span class="whitespace-pre-wrap">{{ $value }}</span>
@endif
