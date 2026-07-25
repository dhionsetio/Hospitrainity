<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarmUpReflectionRequest;
use App\Models\WarmUpReflection;
use App\Models\WarmUpReflectionAttachment;
use App\Services\Reflections\WarmUpReflectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WarmUpReflectionController extends Controller
{
    public function __construct(
        private readonly WarmUpReflectionService $service,
    ) {}

    public function store(StoreWarmUpReflectionRequest $request, string $section, int $prompt): RedirectResponse
    {
        try {
            $files = $request->file('attachments', []);
            if ($files instanceof \Illuminate\Http\UploadedFile) {
                $files = [$files];
            }

            $reflection = $this->service->save(
                $request->user(),
                $section,
                $prompt,
                $request->validated(),
                is_array($files) ? $files : [],
            );

            $message = $reflection->state === 'submitted'
                ? __('reflections.submitted_successfully')
                : __('reflections.saved_as_draft');

            return back()->with('status', $message);
        } catch (RuntimeException $exception) {
            $msg = $exception->getMessage();
            if ($msg !== '') {
                throw ValidationException::withMessages(['body' => $msg]);
            }
            throw $exception;
        }
    }

    public function attachment(
        Request $request,
        WarmUpReflection $reflection,
        WarmUpReflectionAttachment $attachment,
    ): BinaryFileResponse {
        Gate::authorize('view', $reflection);
        abort_unless($attachment->warm_up_reflection_id === $reflection->id, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->storage_path), 404);

        $response = response()->file($disk->path($attachment->storage_path), [
            'Content-Type' => $attachment->detected_mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
