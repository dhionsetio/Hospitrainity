<?php

namespace App\Http\Controllers;

use App\Models\CurriculumEntity;
use App\Services\LearningContentScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CanonicalCurriculumAssetController extends Controller
{
    public function show(Request $request, LearningContentScope $contentScope, string $sha256, string $extension): BinaryFileResponse
    {
        abort_unless(preg_match('/^[0-9a-f]{64}$/', $sha256) === 1, 404);
        abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'mp3', 'wav'], true), 404);
        $scope = $contentScope->current($request, $request->user());
        $package = $scope['package'];
        abort_unless($package !== null, 404);
        $relative = "assets/{$sha256}.{$extension}";
        $referenced = CurriculumEntity::query()->where('curriculum_package_id', $package->id)
            ->whereIn('entity_type', ['lesson-section', 'activity', 'prompt-item'])->published()->get(['entity_type', 'payload'])
            ->contains(function (CurriculumEntity $entity) use ($relative, $sha256, $scope, $contentScope): bool {
                if (! $contentScope->allowsEntity($scope, $entity)) {
                    return false;
                }
                $assets = $entity->entity_type === 'lesson-section'
                    ? array_values(array_filter(array_map(static fn (array $block): mixed => $block['asset'] ?? null, $entity->payload['blocks'] ?? []), 'is_array'))
                    : array_values(array_filter([$entity->payload['audio_asset'] ?? null], 'is_array'));
                foreach ($assets as $asset) {
                    if (($asset['path'] ?? null) === $relative && ($asset['sha256'] ?? null) === $sha256) {
                        return true;
                    }
                }

                return false;
            });
        abort_unless($referenced, 404);
        $root = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $package->source_path) === 1
            ? $package->source_path
            : base_path(str_replace('/', DIRECTORY_SEPARATOR, $package->source_path));
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        abort_unless(is_file($path) && hash_equals($sha256, (string) hash_file('sha256', $path)), 404);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return response()->file($path, [
            'Content-Type' => is_string($mime) ? $mime : 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
