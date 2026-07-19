@php
    $payload = $payload ?? [];
    $textTypes = ['paragraph', 'heading', 'callout', 'list_item', 'instruction'];
    $links = $payload['links'] ?? [];
    $linkRows = max(2, count($links));
@endphp

@if(in_array($type, $textTypes, true))
    <label class="block text-sm font-semibold">{{ __('admin.block_text') }}<textarea name="text" required maxlength="10000" rows="4" class="mt-1 block w-full rounded-md border-neutral-300">{{ $payload['text'] ?? '' }}</textarea></label>
@elseif($type === 'dialogue_turn')
    <label class="block text-sm font-semibold">{{ __('admin.speaker') }}<input name="speaker" required maxlength="160" value="{{ $payload['speaker'] ?? '' }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
    <label class="block text-sm font-semibold">{{ __('admin.block_text') }}<textarea name="text" required maxlength="10000" rows="3" class="mt-1 block w-full rounded-md border-neutral-300">{{ $payload['text'] ?? '' }}</textarea></label>
@elseif($type === 'source_table')
    <label class="block text-sm font-semibold">{{ __('admin.table_caption') }}<input name="caption" required maxlength="500" value="{{ $payload['caption'] ?? '' }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
    <label class="block text-sm font-semibold">{{ __('admin.table_header_tsv') }}<textarea name="table_header" required maxlength="5000" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 font-mono text-sm">{{ implode("\t", $payload['header'] ?? []) }}</textarea></label>
    <label class="block text-sm font-semibold">{{ __('admin.table_rows_tsv') }}<textarea name="table_rows" required maxlength="30000" rows="6" class="mt-1 block w-full rounded-md border-neutral-300 font-mono text-sm">{{ implode("\n", array_map(fn($row) => implode("\t", $row), $payload['rows'] ?? [])) }}</textarea></label>
@elseif($type === 'external_link')
    <div class="space-y-2"><p class="text-sm font-semibold">{{ __('admin.external_links') }}</p>@for($i = 0; $i < $linkRows; $i++)<div class="grid gap-2 md:grid-cols-2"><input name="link_text[]" maxlength="500" value="{{ $links[$i]['text'] ?? '' }}" placeholder="{{ __('admin.link_label') }}" class="rounded-md border-neutral-300"><input name="link_target[]" type="url" maxlength="2048" value="{{ $links[$i]['target'] ?? '' }}" placeholder="https://" class="rounded-md border-neutral-300"></div>@endfor</div>
@elseif($type === 'activity_embed')
    <label class="block text-sm font-semibold">{{ __('admin.activity') }}<select name="activity_code" required class="mt-1 block w-full rounded-md border-neutral-300">@foreach($activities as $activity)<option value="{{ $activity->code }}" @selected(($payload['activity_code'] ?? null) === $activity->code)>{{ $activity->code }} &middot; {{ $activity->payload['title'] ?? '' }}</option>@endforeach</select></label>
@endif

<label class="block text-sm font-semibold">{{ __('admin.attached_asset_optional') }}<select name="asset_public_id" class="mt-1 block w-full rounded-md border-neutral-300"><option value="">{{ __('admin.no_attached_asset') }}</option>@foreach($assets as $asset)<option value="{{ $asset->public_id }}" @selected(($selectedAssetId ?? null) === $asset->public_id)>{{ $asset->display_name }} ({{ $asset->kind->value }})</option>@endforeach</select></label>
<label class="block text-sm font-semibold">{{ __('admin.provenance_note') }}<textarea name="provenance_note" required minlength="3" maxlength="500" rows="2" class="mt-1 block w-full rounded-md border-neutral-300">{{ $payload['provenance_note'] ?? '' }}</textarea></label>
