<?php

namespace App\Services\Curriculum;

use App\Models\CurriculumDraft;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CurriculumDraftPackageBuilder
{
    public function __construct(
        private readonly CurriculumDraftProjection $projection,
        private readonly CanonicalPackageReader $reader,
        private readonly StandaloneGenerator $standalone,
        private readonly Filesystem $files,
    ) {}

    /** @return array{package: CanonicalPackage, root: string, evidence_path: string, evidence: array<string, mixed>} */
    public function build(CurriculumDraft $draft, bool $immutable = false): array
    {
        $draft->loadMissing('basePackage');
        if ($draft->basePackage === null) {
            throw new RuntimeException('An empty workspace cannot be validated until it contains the complete canonical framework and assessment contract. Clone an active package or complete the missing ADM-3 import work first.');
        }
        if (! preg_match('/^\d+\.\d+\.\d+$/', $draft->content_version)) {
            throw new RuntimeException('Published content_version must be a release semantic version such as 0.4.1 and cannot contain a draft suffix.');
        }

        $baseRoot = $this->absolutePath($draft->basePackage->source_path);
        if (! is_dir($baseRoot)) {
            throw new RuntimeException('The base canonical package directory is unavailable.');
        }
        $parent = $immutable
            ? rtrim(config('curriculum.published_package_directory'), '\\/').DIRECTORY_SEPARATOR.$draft->package_name.DIRECTORY_SEPARATOR.$draft->content_version
            : rtrim(config('curriculum.draft_validation_directory'), '\\/').DIRECTORY_SEPARATOR.$draft->public_id.DIRECTORY_SEPARATOR."revision-{$draft->revision}";
        $root = $parent.DIRECTORY_SEPARATOR.'package';
        $evidencePath = $parent.DIRECTORY_SEPARATOR.'evidence.json';
        if ($immutable && (is_dir($root) || is_file($evidencePath))) {
            throw new RuntimeException('The immutable publication artifact already exists for this content version.');
        }
        if (! $immutable && is_dir($parent)) {
            $this->files->deleteDirectory($parent);
        }
        if (! $this->files->copyDirectory($baseRoot, $root)) {
            throw new RuntimeException('Unable to clone the canonical package into private draft storage.');
        }

        try {
            $this->materializeAssets($draft, $root);
            $this->writeProjection($draft, $root);
            $evidence = $this->evidence($draft, $root, $evidencePath);
            $this->writeJson($evidencePath, $evidence);
            $package = $this->reader->read($root, $evidencePath);
            $rendered = $this->standalone->render($package);
            $evidence['standalone'] = [
                'path' => config('curriculum.standalone_output'),
                'byte_count' => strlen($rendered),
                'sha256' => hash('sha256', $rendered),
            ];
            $this->writeJson($evidencePath, $evidence);
            $package = $this->reader->read($root, $evidencePath);

            return compact('package', 'root', 'evidencePath', 'evidence');
        } catch (\Throwable $exception) {
            if ($immutable) {
                $this->files->deleteDirectory($parent);
            }
            throw $exception;
        }
    }

    public function discardImmutable(CurriculumDraft $draft): void
    {
        $root = rtrim(config('curriculum.published_package_directory'), '\\/');
        $target = $root.DIRECTORY_SEPARATOR.$draft->package_name.DIRECTORY_SEPARATOR.$draft->content_version;
        $normalizedRoot = strtolower(str_replace('\\', '/', $root));
        $normalizedTarget = strtolower(str_replace('\\', '/', $target));
        if (! str_starts_with($normalizedTarget.'/', rtrim($normalizedRoot, '/').'/')) {
            throw new RuntimeException('Refusing to discard a publication artifact outside private publication storage.');
        }
        if (is_dir($target) && ! $this->files->deleteDirectory($target)) {
            throw new RuntimeException('Unable to discard the failed private publication artifact.');
        }
    }

    private function writeProjection(CurriculumDraft $draft, string $root): void
    {
        $allDraftEntities = $draft->entities()->get(['source_path', 'archived_at']);
        foreach ($allDraftEntities as $entity) {
            if ($entity->archived_at === null || str_contains($entity->source_path, '#') || ! str_ends_with($entity->source_path, '.json')) {
                continue;
            }
            $path = $this->withinRoot($root, $entity->source_path);
            if (is_file($path)) {
                $this->files->delete($path);
            }
        }

        $entities = $this->projection->entities($draft, forPublication: true);
        $documents = [];
        foreach ($entities as $entity) {
            if (! str_contains($entity['source_path'], '#') && str_ends_with($entity['source_path'], '.json')) {
                $documents[$entity['source_path']] = $entity['payload'];
            }
        }

        $outcomes = array_values(array_map(
            fn (array $entity): array => $entity['payload'],
            array_filter($entities, fn (array $entity): bool => $entity['entity_type'] === 'outcome'),
        ));
        usort($outcomes, fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
        $outcomeDocument = $documents['framework/outcome-alignments.json'] ?? [];
        $outcomeDocument['module_count'] = count(array_unique(array_column($outcomes, 'module')));
        $outcomeDocument['outcome_count'] = count($outcomes);
        $outcomeDocument['outcomes'] = $outcomes;
        $documents['framework/outcome-alignments.json'] = $outcomeDocument;

        foreach ($documents as $path => $payload) {
            if ($path === 'package.json') {
                continue;
            }
            $this->writeJson($this->withinRoot($root, $path), $payload);
        }

        $schemaPath = $this->withinRoot($root, 'schemas/content-block.schema.json');
        $schema = json_decode((string) file_get_contents($schemaPath), true, flags: JSON_THROW_ON_ERROR);
        $schema['properties']['provenance_kind']['enum'] = ['source_verbatim', 'derived_navigation', 'admin_authored'];
        $schema['properties']['source_locator']['properties']['artifact'] = ['type' => 'string', 'minLength' => 1];
        $schema['properties']['source_locator']['properties']['chapter'] = ['type' => 'integer', 'minimum' => 0, 'maximum' => 999];
        $this->writeJson($schemaPath, $schema);

        $package = $documents['package.json'] ?? [];
        $package['package'] = $draft->package_name;
        $package['content_version'] = $draft->content_version;
        $package['schema_version'] = $draft->schema_version;
        $package['namespace'] = $draft->namespace_uuid;
        $package['status'] = 'published';
        foreach (array_keys($package['checksums'] ?? []) as $path) {
            $checksum = hash_file('sha256', $this->withinRoot($root, $path));
            if (! is_string($checksum)) {
                throw new RuntimeException("Unable to checksum declared canonical file: {$path}");
            }
            $package['checksums'][$path] = $checksum;
        }
        $this->writeJson($this->withinRoot($root, 'package.json'), $package);
    }

    private function materializeAssets(CurriculumDraft $draft, string $root): void
    {
        $disk = Storage::disk((string) config('curriculum.import.disk'));
        $materialized = [];
        $blocks = $draft->blocks()->whereNull('archived_at')->whereNotNull('curriculum_asset_id')
            ->with('asset.blob')->get();
        foreach ($blocks as $block) {
            $asset = $block->asset;
            if ($asset === null || $asset->curriculum_draft_id !== $draft->id || $asset->archived_at !== null) {
                throw new RuntimeException('A content block refers to an unavailable draft asset.');
            }
            $this->materializeAsset($asset, $disk, $root, $materialized);
        }

        $activities = $draft->entities()->where('entity_type', 'activity')->whereNull('archived_at')->get(['payload']);
        foreach ($activities as $activity) {
            $reference = $activity->payload['audio_asset'] ?? null;
            if (! is_array($reference) || ! is_string($reference['id'] ?? null)) {
                continue;
            }
            $asset = $draft->assets()->where('public_id', $reference['id'])->where('kind', 'audio')
                ->whereNull('archived_at')->with('blob')->first();
            if ($asset === null
                || ! hash_equals((string) ($reference['sha256'] ?? ''), $asset->blob->sha256)
                || ($reference['path'] ?? null) !== 'assets/'.$asset->blob->sha256.'.'.$asset->blob->extension) {
                throw new RuntimeException('An exercise refers to an unavailable or changed draft audio asset.');
            }
            $this->materializeAsset($asset, $disk, $root, $materialized);
        }
    }

    /** @param array<string, true> $materialized */
    private function materializeAsset($asset, $disk, string $root, array &$materialized): void
    {
        $blob = $asset->blob;
        if (isset($materialized[$blob->sha256])) {
            return;
        }
        if (! $disk->exists($blob->storage_path)) {
            throw new RuntimeException('A referenced private asset blob is unavailable.');
        }
        $target = $this->withinRoot($root, 'assets/'.$blob->sha256.'.'.$blob->extension);
        $this->files->ensureDirectoryExists(dirname($target));
        if (! is_file($target) && ! $this->files->copy($disk->path($blob->storage_path), $target)) {
            throw new RuntimeException('A referenced asset could not be copied into the canonical package.');
        }
        $hash = hash_file('sha256', $target);
        if (! is_string($hash) || ! hash_equals($blob->sha256, $hash)) {
            throw new RuntimeException('A materialized asset failed digest verification.');
        }
        $materialized[$blob->sha256] = true;
    }

    /** @return array<string, mixed> */
    private function evidence(CurriculumDraft $draft, string $root, string $evidencePath): array
    {
        $manifest = $this->manifest($root);
        $baseEvidence = $this->baseEvidence($draft);
        $projectionMeta = $baseEvidence['projection_meta'] ?? [];
        $projectionMeta['checkpoint'] = 'ADM-2';
        $projectionMeta['content_version'] = $draft->content_version;
        $projectionMeta['authoring_draft_id'] = $draft->public_id;
        $projectionMeta['evidence_path'] = $evidencePath;
        $projectionMeta['status'] = 'canonical_admin_publication';
        $projectionMeta['notice'] = 'This version was administratively published after strict canonical validation. Administrative publication does not certify inherited human content, ESP/CEFR, hospitality-practitioner, or accessibility review gates.';

        return [
            'evidence_version' => '3.0.0',
            'source_authority' => $baseEvidence['source_authority'] ?? null,
            'package' => [
                'path' => $root,
                'file_count' => count($manifest),
                'byte_count' => array_sum(array_column($manifest, 'bytes')),
                'json_file_count' => count(array_filter($manifest, fn (array $file): bool => str_ends_with($file['path'], '.json'))),
                'markdown_file_count' => count(array_filter($manifest, fn (array $file): bool => str_ends_with($file['path'], '.md'))),
                'tree_sha256' => $this->treeSha256($manifest),
                'tree_digest_definition' => 'SHA-256 over UTF-8 rows sorted by ordinal relative path: path + NUL + lowercase raw-file SHA-256 + NUL + byte length + LF',
            ],
            'standalone' => ['path' => config('curriculum.standalone_output'), 'byte_count' => 0, 'sha256' => str_repeat('0', 64)],
            'expected_counts' => $this->projection->counts($draft),
            'coverage_counts' => $this->projection->coverage($draft),
            'source_fidelity_reconciliation' => [
                'status' => 'specialist_revalidation_required_for_non_structural_metrics',
                'base_evidence_counts' => $baseEvidence['coverage_counts'] ?? null,
                'note' => 'ADM-2 recomputes only the structural counts enforced by the canonical reader. It does not infer source-semantic counts such as warm-up classification, accepted-answer strings, or guidance strings.',
            ],
            'projection_meta' => $projectionMeta,
            'workflow_gate' => [
                'id' => 'ADM-2',
                'status' => 'administrative_publication_completed',
                'does_not_certify_inherited_human_reviews' => true,
            ],
            'human_gates' => $baseEvidence['human_gates'] ?? ['status' => 'not_recorded'],
            'release_decision' => [
                'package_version' => $draft->content_version,
                'lifecycle_status' => 'published',
                'decision' => 'administratively_published_after_canonical_validation; inherited human-gate evidence remains authoritative',
            ],
        ];
    }

    /** @return list<array{path: string, sha256: string, bytes: int}> */
    private function manifest(string $root): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $manifest = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $manifest[] = ['path' => $path, 'sha256' => (string) hash_file('sha256', $file->getPathname()), 'bytes' => $file->getSize()];
        }
        usort($manifest, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $manifest;
    }

    /** @param list<array{path: string, sha256: string, bytes: int}> $manifest */
    private function treeSha256(array $manifest): string
    {
        $descriptor = '';
        foreach ($manifest as $file) {
            $descriptor .= $file['path']."\0".$file['sha256']."\0".$file['bytes']."\n";
        }

        return hash('sha256', $descriptor);
    }

    /** @return array<string, mixed> */
    private function baseEvidence(CurriculumDraft $draft): array
    {
        $path = $draft->basePackage?->projection_meta['evidence_path'] ?? config('curriculum.evidence_path');
        if (! is_string($path) || ! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1 ? $path : base_path(str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    private function withinRoot(string $root, string $relative): string
    {
        if (str_contains($relative, '..') || preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $relative) === 1) {
            throw new RuntimeException('Canonical source path must be relative and cannot traverse directories.');
        }

        return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create draft package directory: {$directory}");
        }
        $contents = CanonicalJson::encode($data, pretty: true)."\n";
        $written = file_put_contents($path, $contents, LOCK_EX);
        if ($written !== strlen($contents) || ! hash_equals(hash('sha256', $contents), (string) hash_file('sha256', $path))) {
            throw new RuntimeException("Draft package file failed write verification: {$path}");
        }
    }
}
