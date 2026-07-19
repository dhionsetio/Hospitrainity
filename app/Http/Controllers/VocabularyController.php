<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVocabularyRequest;
use App\Http\Requests\UpdateVocabularyRequest;
use App\Models\Lesson;
use App\Models\Vocabulary;
use App\Services\PublicMediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class VocabularyController extends Controller
{
    private const MEDIA_DIRECTORY = 'curriculum/vocabulary';

    public function __construct(private readonly PublicMediaManager $media) {}

    public function index(): View
    {
        $this->authorize('viewAny', Vocabulary::class);
        $vocabularies = Vocabulary::with(['lesson', 'items'])
            ->orderBy('lesson_id')
            ->curriculumOrder()
            ->paginate(10);
        $lessons = Lesson::orderBy('title')->get();
        $failedVocabularyState = null;

        $oldInput = request()->session()->getOldInput();
        if (($oldInput['_form_mode'] ?? null) === 'edit' && (int) ($oldInput['_record_id'] ?? 0) > 0) {
            $record = Vocabulary::with('items')->find((int) $oldInput['_record_id']);
            if ($record !== null) {
                $currentItems = $record->items->keyBy('id');
                $oldItems = is_array($oldInput['items'] ?? null) ? $oldInput['items'] : [];
                $items = collect($oldItems)->filter(static fn ($item): bool => is_array($item))->map(function (array $item) use ($currentItems): array {
                    $current = isset($item['id']) ? $currentItems->get((int) $item['id']) : null;
                    $item['media_url'] = $current?->media_url;
                    $item['order'] = $item['order'] ?? $current?->order ?? 0;

                    return $item;
                })->all();
                $failedVocabularyState = [
                    'id' => $record->id,
                    'lesson_id' => $oldInput['lesson_id'] ?? $record->lesson_id,
                    'category' => $oldInput['category'] ?? $record->category,
                    'order' => $oldInput['order'] ?? $record->order,
                    'items' => $items !== [] ? $items : [['term' => '', 'details' => '', 'media_url' => null, 'order' => 0]],
                ];
            }
        }

        return view('superadmin.vocabularies.index', compact('vocabularies', 'lessons', 'failedVocabularyState'));
    }

    public function store(StoreVocabularyRequest $request): RedirectResponse
    {
        $this->authorize('create', Vocabulary::class);
        $data = $request->validated();

        try {
            $items = [];
            foreach ($data['items'] as $index => $itemData) {
                $items[] = [
                    'term' => $itemData['term'],
                    'details' => $itemData['details'] ?? null,
                    'order' => (int) ($itemData['order'] ?? 0),
                    'media_url' => $request->hasFile("items.{$index}.media")
                        ? $this->media->stage($request->file("items.{$index}.media"), self::MEDIA_DIRECTORY)
                        : null,
                ];
            }

            DB::transaction(function () use ($data, $items): void {
                $vocabulary = Vocabulary::create([
                    'lesson_id' => $data['lesson_id'],
                    'category' => $data['category'],
                    'order' => (int) ($data['order'] ?? 0),
                ]);
                $vocabulary->items()->createMany($items);
                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.vocabularies.index')->with('success', __('Vocabulary created successfully.'));
    }

    public function update(UpdateVocabularyRequest $request, Vocabulary $vocabulary): RedirectResponse
    {
        $this->authorize('update', $vocabulary);
        $data = $request->validated();
        $vocabulary->load('items');
        $currentItems = $vocabulary->items->keyBy('id');

        try {
            $incomingIds = collect($data['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $itemsToDelete = $vocabulary->items->whereNotIn('id', $incomingIds);
            foreach ($itemsToDelete as $item) {
                $this->media->retire($item->media_url);
            }

            $preparedItems = [];
            foreach ($data['items'] as $index => $itemData) {
                $itemId = isset($itemData['id']) ? (int) $itemData['id'] : null;
                $current = $itemId === null ? null : $currentItems->get($itemId);
                $mediaUrl = $current?->media_url;

                if ($request->hasFile("items.{$index}.media")) {
                    $this->media->retire($mediaUrl);
                    $mediaUrl = $this->media->stage(
                        $request->file("items.{$index}.media"),
                        self::MEDIA_DIRECTORY,
                    );
                }

                $preparedItems[] = [
                    'id' => $itemId,
                    'term' => $itemData['term'],
                    'details' => $itemData['details'] ?? null,
                    'media_url' => $mediaUrl,
                    'order' => (int) ($itemData['order'] ?? $current?->order ?? 0),
                ];
            }

            DB::transaction(function () use ($data, $itemsToDelete, $preparedItems, $vocabulary): void {
                $vocabulary->update([
                    'lesson_id' => $data['lesson_id'],
                    'category' => $data['category'],
                    'order' => (int) ($data['order'] ?? $vocabulary->order),
                ]);

                foreach ($itemsToDelete as $item) {
                    $item->delete();
                }

                foreach ($preparedItems as $itemData) {
                    $itemId = $itemData['id'];
                    unset($itemData['id']);

                    if ($itemId === null) {
                        $vocabulary->items()->create($itemData);
                    } else {
                        $vocabulary->items()->whereKey($itemId)->firstOrFail()->update($itemData);
                    }
                }

                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.vocabularies.index')->with('success', __('Vocabulary updated successfully.'));
    }

    public function destroy(Vocabulary $vocabulary): RedirectResponse
    {
        $this->authorize('delete', $vocabulary);
        $vocabulary->load('items');

        foreach ($vocabulary->items as $item) {
            $this->media->retire($item->media_url);
        }

        try {
            DB::transaction(function () use ($vocabulary): void {
                $vocabulary->delete();
                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.vocabularies.index')->with('success', __('Vocabulary deleted successfully.'));
    }
}
