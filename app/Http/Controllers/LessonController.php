<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\Vocabulary;
use App\Services\CanonicalCurriculumRepository;
use App\Services\PublicMediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class LessonController extends Controller
{
    public function __construct(
        private readonly PublicMediaManager $media,
        private readonly CanonicalCurriculumRepository $canonicalCurriculum,
    ) {}

    /**
     * MENAMPILKAN HALAMAN DETAIL: Daftar kategori vocab, material, dll.
     */
    public function show(Lesson $lesson): View
    {
        $this->abortIfCanonicalCurriculumIsActive();
        $this->authorize('view', $lesson);
        $lesson->load(['module', 'vocabularies.items', 'materials.items', 'exercises']);

        return view('lesson', compact('lesson'));
    }

    /**
     * MENAMPILKAN HALAMAN LATIHAN: Tampilan seperti Duolingo.
     */
    public function practice(Lesson $lesson, Vocabulary $vocabulary): View
    {
        $this->abortIfCanonicalCurriculumIsActive();
        $this->authorize('view', $vocabulary);
        // Di sini, kita memuat 'items' dari KATEGORI TERTENTU yang dipilih.
        $vocabulary->load('items');

        return view('practice', compact('lesson', 'vocabulary'));
    }

    public function material(Lesson $lesson, Material $material): View
    {
        $this->abortIfCanonicalCurriculumIsActive();
        $this->authorize('view', $material);
        // Di sini, kita memuat 'items' dari KATEGORI TERTENTU yang dipilih.
        $material->load('items');

        return view('material', compact('lesson', 'material'));
    }

    public function practiceExercises(Lesson $lesson): View
    {
        $this->abortIfCanonicalCurriculumIsActive();
        $this->authorize('view', $lesson);
        $exercises = $lesson->exercises()->get();

        return view('exercise', compact('lesson', 'exercises'));
    }

    public function index(): View
    {
        $this->authorize('viewAny', Lesson::class);
        $lessons = Lesson::with('module')
            ->orderBy('module_id')
            ->curriculumOrder()
            ->paginate(10);
        $modules = Module::orderBy('title')->get(); // Ambil modules untuk dropdown di modal

        return view('superadmin.lessons.index', compact('lessons', 'modules'));
    }

    public function store(StoreLessonRequest $request): RedirectResponse
    {
        $this->authorize('create', Lesson::class);
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            Lesson::create([
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['slug']),
                'module_id' => $data['module_id'],
                'order' => (int) ($data['order'] ?? 0),
            ]);
        }, attempts: 3);

        return redirect()->route('superadmin.lessons.index')->with('success', __('Lesson created successfully.'));
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $this->authorize('update', $lesson);
        $data = $request->validated();

        DB::transaction(function () use ($data, $lesson): void {
            $lesson->update([
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['slug'], $lesson->id),
                'module_id' => $data['module_id'],
                'order' => (int) ($data['order'] ?? $lesson->order),
            ]);
        }, attempts: 3);

        return redirect()->route('superadmin.lessons.index')->with('success', __('Lesson updated successfully.'));
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->authorize('delete', $lesson);
        $lesson->load(['vocabularies.items', 'materials.items']);

        foreach ($lesson->vocabularies as $vocabulary) {
            foreach ($vocabulary->items as $item) {
                $this->media->retire($item->media_url);
            }
        }
        foreach ($lesson->materials as $material) {
            foreach ($material->items as $item) {
                $this->media->retire($item->url);
                $this->media->retire($item->audio_url);
            }
        }

        try {
            DB::transaction(function () use ($lesson): void {
                $lesson->delete();
                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.lessons.index')->with('success', __('Lesson deleted successfully.'));
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'lesson';
        $slug = $base;
        $suffix = 2;

        while (
            Lesson::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function abortIfCanonicalCurriculumIsActive(): void
    {
        abort_if(
            $this->canonicalCurriculum->isActive(),
            410,
            __('This legacy curriculum route was retired when the canonical package was activated.'),
        );
    }
}
