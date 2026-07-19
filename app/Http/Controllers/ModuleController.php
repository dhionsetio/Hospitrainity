<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\CanonicalCurriculumRepository;
use App\Services\PublicMediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ModuleController extends Controller
{
    public function __construct(
        private readonly PublicMediaManager $media,
        private readonly CanonicalCurriculumRepository $canonicalCurriculum,
    ) {}

    /**
     * Menampilkan detail sebuah modul beserta pelajarannya.
     */
    public function show(Module $module): View
    {
        abort_if($this->canonicalCurriculum->isActive(), 410, __('This legacy curriculum route was retired when the canonical package was activated.'));
        $this->authorize('view', $module);
        // Mengambil data modul beserta relasi pelajarannya (lessons).
        // Ini akan membuat query lebih efisien.
        $module->load('lessons');

        return view('module', compact('module'));
    }

    public function index(): View
    {
        $this->authorize('viewAny', Module::class);
        $modules = Module::withCount('lessons')->curriculumOrder()->paginate(10);

        return view('superadmin.modules.index', compact('modules'));
    }

    public function store(StoreModuleRequest $request): RedirectResponse
    {
        $this->authorize('create', Module::class);
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            Module::create([
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title']),
                'description' => $data['description'],
                'level' => $data['level'],
                'order' => (int) ($data['order'] ?? 0),
                'is_published' => $request->boolean('is_published'),
            ]);
        }, attempts: 3);

        return redirect()->route('superadmin.modules.index')->with('success', __('Module created successfully.'));
    }

    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        $this->authorize('update', $module);
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $module): void {
            $module->update([
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title'], $module->id),
                'description' => $data['description'],
                'level' => $data['level'],
                'order' => (int) ($data['order'] ?? $module->order),
                'is_published' => $request->boolean('is_published'),
            ]);
        }, attempts: 3);

        return redirect()->route('superadmin.modules.index')->with('success', __('Module updated successfully.'));
    }

    /**
     * Build a URL slug that is guaranteed unique in the modules
     * table. Titles are unique, but Str::slug() can collapse two distinct titles
     * (e.g. "Cafe" and "Cafe!") onto the same slug, which would violate the
     * slug unique index. Append -2, -3, ... until the slug is free. On update the
     * module's own row is ignored so re-saving keeps a stable slug.
     */
    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'module';
        }

        $slug = $base;
        $suffix = 2;
        while (
            Module::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function destroy(Module $module): RedirectResponse
    {
        $this->authorize('delete', $module);
        $module->load(['lessons.vocabularies.items', 'lessons.materials.items']);

        foreach ($module->lessons as $lesson) {
            $this->retireLessonMedia($lesson);
        }

        try {
            DB::transaction(function () use ($module): void {
                $module->delete();
                $this->media->queueRetirements();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->media->rollbackStaged();
            throw $exception;
        }

        $this->media->finalize();

        return redirect()->route('superadmin.modules.index')->with('success', __('Module deleted successfully.'));
    }

    private function retireLessonMedia(Lesson $lesson): void
    {
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
    }
}
