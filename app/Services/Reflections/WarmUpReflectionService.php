<?php

namespace App\Services\Reflections;

use App\Models\CourseOffering;
use App\Models\PendingMediaDeletion;
use App\Models\User;
use App\Models\WarmUpReflection;
use App\Models\WarmUpReflectionAttachment;
use App\Services\CanonicalCurriculumRepository;
use App\Services\LearningContext;
use App\Services\UploadSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

final class WarmUpReflectionService
{
    public function __construct(
        private readonly CanonicalCurriculumRepository $curriculum,
        private readonly LearningContext $learningContext,
        private readonly ReflectionMediaInspector $inspector,
        private readonly UploadSecurityService $uploadSecurity,
    ) {}

    /** @return array<string, mixed> */
    public function definition(User $user, string $sectionCode, int $promptIndex): array
    {
        $section = $this->curriculum->section($sectionCode);
        abort_if($section === null, 404);
        abort_unless($section['is_warm_up'] ?? false, 404);

        $blocks = array_values(array_filter(
            $section['blocks'] ?? [],
            static function (array $b): bool {
                if (($b['type'] ?? '') === 'list_item') {
                    return true;
                }
                $text = $b['text'] ?? '';
                if (is_string($text) && trim($text) !== '') {
                    return true;
                }

                return isset($b['runs']) && is_array($b['runs']) && count($b['runs']) > 0;
            }
        ));

        abort_if(! isset($blocks[$promptIndex]), 404);
        $promptBlock = $blocks[$promptIndex];
        $promptText = trim((string) ($promptBlock['text'] ?? ''));
        if ($promptText === '' && isset($promptBlock['runs']) && is_array($promptBlock['runs'])) {
            $promptText = trim(implode('', array_map(
                static fn (array $run): string => is_string($run['text'] ?? null) ? $run['text'] : '',
                $promptBlock['runs']
            )));
        }
        abort_if($promptText === '', 404);

        $promptFingerprint = hash('sha256', mb_strtolower($promptText));
        $sectionEntity = \App\Models\CurriculumEntity::query()
            ->where('curriculum_package_id', $section['package']->id)
            ->where('entity_type', 'lesson-section')
            ->where('code', $sectionCode)
            ->first();
        abort_if($sectionEntity === null, 404);

        $sectionSourceSha256 = (string) $sectionEntity->source_sha256;
        if (preg_match('/^[0-9a-f]{64}$/', $sectionSourceSha256) !== 1) {
            throw new RuntimeException('The reflection section source evidence is incomplete.');
        }

        $context = $this->learningContext->current(request(), $user);
        $courseRevisionId = null;
        if (is_string($context['course_offering_id'] ?? null)) {
            $courseRevisionId = CourseOffering::query()
                ->whereKey($context['course_offering_id'])
                ->value('course_revision_id');
            if (! is_string($courseRevisionId)) {
                throw new RuntimeException('The selected Class does not have a Course Revision.');
            }
        }

        return [
            'section' => $section,
            'chapter_code' => (string) $section['chapter']['code'],
            'section_code' => $sectionCode,
            'section_source_sha256' => $sectionSourceSha256,
            'prompt_index' => $promptIndex,
            'prompt_block' => $promptBlock,
            'prompt_text' => $promptText,
            'prompt_fingerprint' => $promptFingerprint,
            'context' => $context,
            'course_revision_id' => $courseRevisionId,
            'package' => $section['package'],
        ];
    }

    /**
     * @return Collection<int, WarmUpReflection>
     */
    public function existingForSection(?User $user, string $sectionCode): Collection
    {
        if ($user === null) {
            return collect();
        }

        $context = $this->learningContext->current(request(), $user);
        $scopeKey = (string) ($context['scope_key'] ?? 'personal');

        return WarmUpReflection::query()
            ->where('user_id', $user->id)
            ->where('learning_scope_key', $scopeKey)
            ->where('section_code', $sectionCode)
            ->with('attachments')
            ->get()
            ->keyBy('prompt_index');
    }

    public function draft(User $user, array $definition): ?WarmUpReflection
    {
        $scopeKey = (string) ($definition['context']['scope_key'] ?? 'personal');

        return WarmUpReflection::query()
            ->where('user_id', $user->id)
            ->where('learning_scope_key', $scopeKey)
            ->where('section_code', $definition['section_code'])
            ->where('prompt_index', $definition['prompt_index'])
            ->with('attachments')
            ->first();
    }

    /**
     * @param array{response_key: string, intent: string, body?: string|null, recording_kind?: string|null} $input
     * @param array<int, UploadedFile> $files
     */
    public function save(User $user, string $sectionCode, int $promptIndex, array $input, array $files = []): WarmUpReflection
    {
        $definition = $this->definition($user, $sectionCode, $promptIndex);
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required to encrypt warm-up reflections.');
        }

        $scopeKey = (string) ($definition['context']['scope_key'] ?? 'personal');
        $privateDiskName = (string) config('learning_reflection.disk', 'learner_media_private');
        $quarantinePrefix = trim((string) config('learning_reflection.quarantine_prefix', 'reflections/quarantine'), '/');
        $blobPrefix = trim((string) config('learning_reflection.blob_prefix', 'reflections/blobs'), '/');

        return DB::transaction(function () use (
            $user,
            $definition,
            $scopeKey,
            $input,
            $files,
            $appKey,
            $privateDiskName,
            $quarantinePrefix,
            $blobPrefix
        ): WarmUpReflection {
            $reflection = WarmUpReflection::query()
                ->where('user_id', $user->id)
                ->where('learning_scope_key', $scopeKey)
                ->where('chapter_code', $definition['chapter_code'])
                ->where('section_code', $definition['section_code'])
                ->where('prompt_index', $definition['prompt_index'])
                ->lockForUpdate()
                ->first();

            if ($reflection !== null && $reflection->state === 'submitted') {
                throw new LogicException('Submitted warm-up reflections are immutable.');
            }

            $rawBody = is_string($input['body'] ?? null) ? trim((string) $input['body']) : null;
            $body = $rawBody !== '' ? $rawBody : null;
            $bodyHmac = $body !== null ? hash_hmac('sha256', $body, $appKey) : null;

            if ($reflection === null) {
                $reflection = new WarmUpReflection;
                $reflection->fill([
                    'user_id' => $user->id,
                    'response_key' => $input['response_key'],
                    'learning_scope_key' => $scopeKey,
                    'institution_membership_id' => $definition['context']['institution_membership_id'] ?? null,
                    'course_offering_id' => $definition['context']['course_offering_id'] ?? null,
                    'course_enrollment_id' => $definition['context']['course_enrollment_id'] ?? null,
                    'course_revision_id' => $definition['course_revision_id'],
                    'curriculum_package_id' => $definition['package']->id,
                    'chapter_code' => $definition['chapter_code'],
                    'section_code' => $definition['section_code'],
                    'section_source_sha256' => $definition['section_source_sha256'],
                    'prompt_index' => $definition['prompt_index'],
                    'prompt_fingerprint' => $definition['prompt_fingerprint'],
                ]);
            }

            $reflection->body = $body;
            $reflection->body_hmac_sha256 = $bodyHmac;

            if (($input['intent'] ?? 'save') === 'submit') {
                $reflection->state = 'submitted';
                $reflection->submitted_at = now();
            }

            $reflection->save();

            if (count($files) > 0) {
                $disk = Storage::disk($privateDiskName);
                $declaredKind = $input['recording_kind'] ?? null;

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }
                    $metadata = $this->inspector->inspect($file, $declaredKind);
                    $scan = $this->uploadSecurity->inspect($file, $user, 'reflection_media', $metadata);

                    $uploadId = (string) Str::uuid();
                    $quarantinePath = "{$quarantinePrefix}/{$uploadId}.{$metadata['extension']}";
                    $stored = $disk->putFileAs(dirname($quarantinePath), $file, basename($quarantinePath));

                    if ($stored !== $quarantinePath || ! $disk->exists($quarantinePath)) {
                        throw new RuntimeException('The reflection media file could not be stored in private quarantine.');
                    }

                    $blobPath = "{$blobPrefix}/".substr($metadata['sha256'], 0, 2)."/{$metadata['sha256']}.{$metadata['extension']}";

                    try {
                        if ($disk->exists($blobPath)) {
                            $disk->delete($quarantinePath);
                        } elseif (! $disk->move($quarantinePath, $blobPath)) {
                            throw new RuntimeException('The validated reflection media could not be promoted.');
                        }

                        $attachment = new WarmUpReflectionAttachment;
                        $attachment->fill([
                            'warm_up_reflection_id' => $reflection->id,
                            'kind' => $metadata['kind'],
                            'disk' => $privateDiskName,
                            'storage_path' => $blobPath,
                            'original_name' => $metadata['original_name'],
                            'detected_mime' => $metadata['mime'],
                            'byte_size' => $metadata['bytes'],
                            'sha256' => $metadata['sha256'],
                            'scan_status' => $scan->status,
                            'upload_security_record_id' => $scan->id,
                            'scanned_at' => now(),
                        ]);
                        $attachment->save();

                        $this->uploadSecurity->markPromoted($scan);
                    } catch (\Throwable $exception) {
                        if ($disk->exists($quarantinePath)) {
                            $disk->delete($quarantinePath);
                        }
                        throw $exception;
                    }
                }
            }

            $maxAllowed = (int) config('learning_reflection.max_attachments_per_prompt', 3);
            if ($reflection->attachments()->count() > $maxAllowed) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'attachments' => __('reflections.validation.max_attachments_exceeded'),
                ]);
            }

            return $reflection->load('attachments');
        }, attempts: 3);
    }

    public function forgetAttachment(WarmUpReflectionAttachment $attachment): void
    {
        $diskName = $attachment->disk;
        $storagePath = $attachment->storage_path;

        DB::transaction(function () use ($attachment, $diskName, $storagePath) {
            $attachment->delete();

            $remainingCount = WarmUpReflectionAttachment::query()
                ->where('disk', $diskName)
                ->where('storage_path', $storagePath)
                ->count();

            if ($remainingCount === 0) {
                \App\Models\PendingMediaDeletion::query()->firstOrCreate([
                    'disk' => $diskName,
                    'path' => $storagePath,
                ]);
            }
        });
    }
}
