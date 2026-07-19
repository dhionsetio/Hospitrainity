<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Rules\YouTubeUrl;
use App\Services\PublicMediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class MaterialController extends Controller
{
    private const IMAGE_DIRECTORY = 'curriculum/materials/images';

    private const AUDIO_DIRECTORY = 'curriculum/materials/audio';

    public function __construct(private readonly PublicMediaManager $media) {}

    public function index(): View
    {
        $this->authorize('viewAny', Material::class);
        $materials = Material::with(['lesson', 'items'])
            ->orderBy('lesson_id')
            ->curriculumOrder()
            ->paginate(10);
        $lessons = Lesson::orderBy('title')->get();
        $failedMaterialState = null;

        $oldInput = request()->session()->getOldInput();
        if (($oldInput['_form_mode'] ?? null) === 'edit' && (int) ($oldInput['_record_id'] ?? 0) > 0) {
            $record = Material::with('items')->find((int) $oldInput['_record_id']);
            if ($record !== null) {
                $currentItems = $record->items->keyBy('id');
                $oldItems = is_array($oldInput['items'] ?? null) ? $oldInput['items'] : [];
                $items = collect($oldItems)->filter(static fn ($item): bool => is_array($item))->map(function (array $item) use ($currentItems): array {
                    $current = isset($item['id']) ? $currentItems->get((int) $item['id']) : null;
                    $item['url'] = $current?->url ?? ($item['url'] ?? null);
                    $item['audio_url'] = $current?->audio_url;
                    $item['order'] = $item['order'] ?? $current?->order ?? 0;

                    return $item;
                })->all();
                $failedMaterialState = [
                    'id' => $record->id,
                    'lesson_id' => $oldInput['lesson_id'] ?? $record->lesson_id,
                    'type' => $record->type,
                    'order' => $oldInput['order'] ?? $record->order,
                    'items' => $items !== [] ? $items : [['title' => '', 'description' => '', 'url' => null, 'audio_url' => null, 'order' => 0]],
                ];
            }
        }

        return view('superadmin.materials.index', compact('materials', 'lessons', 'failedMaterialState'));
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        $this->authorize('create', Material::class);
        $data = $request->validated();
        $type = $data['type'];

        try {
            $items = $this->prepareItems($request, $data['items'], $type);

            DB::transaction(function () use ($data, $items, $type): void {
                $material = Material::create([
                    'lesson_id' => $data['lesson_id'],
                    'type' => $type,
                    'order' => (int) ($data['order'] ?? 0),
                ]);
                $material->items()->createMany($items);
                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.materials.index')->with('success', __('Material created successfully.'));
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);
        $data = $request->validated();
        $type = $material->type;
        $material->load('items');
        $currentItems = $material->items->keyBy('id');

        try {
            $incomingIds = collect($data['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $itemsToDelete = $material->items->whereNotIn('id', $incomingIds);
            foreach ($itemsToDelete as $item) {
                $this->media->retire($item->url);
                $this->media->retire($item->audio_url);
            }

            $preparedItems = $this->prepareItems($request, $data['items'], $type, $currentItems->all());

            DB::transaction(function () use ($data, $itemsToDelete, $material, $preparedItems): void {
                // Type is deliberately immutable: it defines the item/media contract.
                $material->update([
                    'lesson_id' => $data['lesson_id'],
                    'order' => (int) ($data['order'] ?? $material->order),
                ]);

                foreach ($itemsToDelete as $item) {
                    $item->delete();
                }

                foreach ($preparedItems as $itemData) {
                    $itemId = $itemData['id'];
                    unset($itemData['id']);

                    if ($itemId === null) {
                        $material->items()->create($itemData);
                    } else {
                        $material->items()->whereKey($itemId)->firstOrFail()->update($itemData);
                    }
                }

                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.materials.index')->with('success', __('Material updated successfully.'));
    }

    public function destroy(Material $material): RedirectResponse
    {
        $this->authorize('delete', $material);
        $material->load('items');

        foreach ($material->items as $item) {
            $this->media->retire($item->url);
            $this->media->retire($item->audio_url);
        }

        try {
            DB::transaction(function () use ($material): void {
                $material->delete();
                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.materials.index')->with('success', __('Material deleted successfully.'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, MaterialItem>  $currentItems
     * @return array<int, array<string, mixed>>
     */
    private function prepareItems(
        StoreMaterialRequest|UpdateMaterialRequest $request,
        array $items,
        string $type,
        array $currentItems = [],
    ): array {
        $prepared = [];

        foreach ($items as $index => $itemData) {
            $itemId = isset($itemData['id']) ? (int) $itemData['id'] : null;
            $current = $itemId === null ? null : ($currentItems[$itemId] ?? null);
            $url = $current?->url;
            $audioUrl = $current?->audio_url;

            if ($type === 'Video') {
                $url = YouTubeUrl::canonicalize($itemData['url']);
                $audioUrl = null;
            } elseif ($request->hasFile("items.{$index}.file")) {
                $this->media->retire($url);
                $directory = $type === 'Gambar' ? self::IMAGE_DIRECTORY : self::AUDIO_DIRECTORY;
                $url = $this->media->stage($request->file("items.{$index}.file"), $directory);
            }

            if ($request->hasFile("items.{$index}.audio_file")) {
                $this->media->retire($audioUrl);
                $audioUrl = $this->media->stage(
                    $request->file("items.{$index}.audio_file"),
                    self::AUDIO_DIRECTORY,
                );
            }

            $prepared[] = [
                'id' => $itemId,
                'title' => $itemData['title'] ?? null,
                'description' => $itemData['description'],
                'url' => $url,
                'audio_url' => $audioUrl,
                'order' => (int) ($itemData['order'] ?? $current?->order ?? 0),
            ];
        }

        return $prepared;
    }
}
