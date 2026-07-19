<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumImportStatus;
use App\Models\CurriculumDraft;
use App\Models\CurriculumImport;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

final class CurriculumImportCompiler
{
    public function __construct(
        private readonly CanonicalPackageReader $reader,
        private readonly CurriculumDraftProjection $projection,
        private readonly Filesystem $files,
        private readonly CurriculumDraftLifecycle $lifecycle,
    ) {}

    public function compile(CurriculumImport $import): void
    {
        $import = DB::transaction(function () use ($import): CurriculumImport {
            $locked = CurriculumImport::query()->lockForUpdate()->with(['draft', 'basePackage'])->findOrFail($import->id);
            if ($locked->status !== CurriculumImportStatus::Queued) {
                throw new RuntimeException('Only a queued curriculum import can be compiled.');
            }
            $locked->forceFill(['status' => CurriculumImportStatus::Processing, 'revision' => $locked->revision + 1])->save();

            return $locked;
        });

        try {
            $disk = Storage::disk((string) config('curriculum.import.disk'));
            if (! is_string($import->source_storage_path) || ! $disk->exists($import->source_storage_path)) {
                throw new RuntimeException('The private quarantined source is unavailable.');
            }
            $source = $disk->path($import->source_storage_path);
            $output = $disk->path((string) $import->compiled_package_path);
            $evidencePath = $disk->path((string) $import->evidence_path);
            $baseline = $this->absolutePath((string) $import->basePackage?->source_path);
            if (! is_dir($baseline)) {
                throw new RuntimeException('The draft base package is unavailable.');
            }
            $this->files->ensureDirectoryExists(dirname($output));
            if (is_dir($output)) {
                $this->files->deleteDirectory($output);
            }

            $result = Process::path(base_path())
                ->timeout((int) config('curriculum.import.compiler_timeout_seconds'))
                ->idleTimeout((int) config('curriculum.import.compiler_idle_timeout_seconds'))
                ->run([
                    PHP_BINARY,
                    base_path('scripts/curriculum/compile.php'),
                    '--source', $source,
                    '--baseline', $baseline,
                    '--output', $output,
                    '--expected-sha256', $import->source_sha256,
                    '--source-artifact', $import->source_original_name,
                ]);
            if (! $result->successful()) {
                throw new RuntimeException('The canonical compiler rejected the DOCX: '.$this->safeMessage($result->errorOutput()));
            }
            $compiler = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($compiler) || ($compiler['status'] ?? null) !== 'compiled') {
                throw new RuntimeException('The canonical compiler returned an invalid completion report.');
            }
            $inventory = $this->readJson($output.DIRECTORY_SEPARATOR.'provenance'.DIRECTORY_SEPARATOR.'source-inventory.json');
            $evidence = $this->evidence($import, $output, $compiler, $inventory);
            $this->writeJson($evidencePath, $evidence);
            $package = $this->reader->read($output, $evidencePath);
            $diff = $this->diff($import->draft, $package, $inventory);

            DB::transaction(function () use ($import, $compiler, $inventory, $diff, $package): void {
                $locked = CurriculumImport::query()->lockForUpdate()->findOrFail($import->id);
                if ($locked->status !== CurriculumImportStatus::Processing) {
                    throw new RuntimeException('The import state changed while it was compiling.');
                }
                $locked->forceFill([
                    'status' => CurriculumImportStatus::Ready,
                    'revision' => $locked->revision + 1,
                    'compiler_report' => $compiler,
                    'inventory_report' => $inventory,
                    'diff_report' => $diff,
                    'error_report' => null,
                    'processed_at' => now(),
                ])->save();

                $draft = CurriculumDraft::query()->find($locked->curriculum_draft_id);
                if ($draft !== null) {
                    $this->lifecycle->record(
                        $draft,
                        User::query()->find($locked->created_by),
                        'docx_import_ready',
                        metadata: [
                            'import_id' => $locked->public_id,
                            'source_sha256' => $locked->source_sha256,
                            'compiled_tree_sha256' => $package->treeSha256,
                            'diff_summary' => $diff['summary'] ?? null,
                        ],
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->fail($import, $exception);
        }
    }

    private function fail(CurriculumImport $import, Throwable $exception): void
    {
        $disk = Storage::disk((string) config('curriculum.import.disk'));
        if (is_string($import->compiled_package_path)) {
            $path = $disk->path($import->compiled_package_path);
            if (is_dir($path)) {
                $this->files->deleteDirectory($path);
            }
        }
        if (is_string($import->source_storage_path)) {
            $disk->delete($import->source_storage_path);
        }
        DB::transaction(function () use ($import, $exception): void {
            $locked = CurriculumImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->status === CurriculumImportStatus::Accepted) {
                return;
            }
            $locked->forceFill([
                'status' => CurriculumImportStatus::Failed,
                'revision' => $locked->revision + 1,
                'source_storage_path' => null,
                'error_report' => [
                    'code' => 'canonical_compilation_failed',
                    'message' => $this->safeMessage($exception->getMessage()),
                    'source_deleted' => true,
                ],
                'processed_at' => now(),
            ])->save();

            $draft = CurriculumDraft::query()->find($locked->curriculum_draft_id);
            if ($draft !== null) {
                $this->lifecycle->record(
                    $draft,
                    User::query()->find($locked->created_by),
                    'docx_import_failed',
                    metadata: [
                        'import_id' => $locked->public_id,
                        'source_sha256' => $locked->source_sha256,
                        'error_code' => 'canonical_compilation_failed',
                        'source_deleted' => true,
                    ],
                );
            }
        });
    }

    /** @param array<string, mixed> $compiler @param array<string, mixed> $inventory @return array<string, mixed> */
    private function evidence(CurriculumImport $import, string $root, array $compiler, array $inventory): array
    {
        $manifest = $this->manifest($root);
        $counts = $compiler['counts'] ?? [];
        $framework = [
            'outcomes' => count($this->readJson($root.'/framework/outcome-alignments.json')['outcomes'] ?? []),
            'competencies' => count($this->readJson($root.'/framework/competencies.json')['competencies'] ?? []),
            'cefr_references' => count($this->readJson($root.'/framework/cefr-references.json')['references'] ?? []),
        ];
        $expected = [
            'chapters' => (int) ($counts['chapter'] ?? -1),
            'sections' => (int) ($counts['lesson-section'] ?? -1),
            'activities' => (int) ($counts['activity'] ?? -1),
            'prompts' => (int) ($counts['prompt-item'] ?? -1),
            'answer_models' => (int) ($counts['answer-model'] ?? -1),
            'feedback_models' => (int) ($counts['feedback-model'] ?? -1),
            'rubrics' => (int) ($counts['rubric'] ?? -1),
            'outcomes' => $framework['outcomes'],
            'competencies' => $framework['competencies'],
            'cefr_references' => $framework['cefr_references'],
            'source_provenance' => (int) ($counts['source-provenance'] ?? -1),
            'migration_edges' => (int) ($counts['migration-edge'] ?? -1),
        ];
        $canonical = $inventory['canonical'] ?? [];

        return [
            'evidence_version' => '3.1.0-import-dry-run',
            'source_authority' => ['artifact' => $import->source_original_name, 'sha256' => $import->source_sha256],
            'package' => [
                'path' => $root,
                'file_count' => count($manifest),
                'byte_count' => array_sum(array_column($manifest, 'bytes')),
                'json_file_count' => count(array_filter($manifest, fn (array $file): bool => str_ends_with($file['path'], '.json'))),
                'markdown_file_count' => count(array_filter($manifest, fn (array $file): bool => str_ends_with($file['path'], '.md'))),
                'tree_sha256' => $this->treeSha256($manifest),
            ],
            'standalone' => ['path' => null, 'byte_count' => 0, 'sha256' => str_repeat('0', 64)],
            'expected_counts' => $expected,
            'coverage_counts' => [
                'content_blocks' => (int) ($compiler['content_blocks'] ?? -1),
                'source_tables' => (int) ($canonical['source_tables'] ?? -1),
                'external_hyperlink_relationships' => (int) ($canonical['external_hyperlink_relationships'] ?? -1),
            ],
            'projection_meta' => [
                'checkpoint' => 'ADM-3',
                'status' => 'private_dry_run_only',
                'content_version' => $compiler['content_version'] ?? null,
                'notice' => 'Compilation does not activate or publish this package. Acceptance replaces only the selected draft workspace.',
            ],
            'workflow_gate' => ['id' => 'ADM-3', 'status' => 'dry_run'],
        ];
    }

    /** @return array<string, mixed> */
    private function diff(CurriculumDraft $draft, CanonicalPackage $package, array $inventory): array
    {
        $current = [];
        foreach ($this->projection->entities($draft) as $entity) {
            $current[$entity['source_path']] = $entity;
        }
        $compiled = [];
        foreach ($package->entities as $entity) {
            $compiled[$entity['source_path']] = $entity;
        }
        $items = ['created' => [], 'changed' => [], 'removed' => [], 'unchanged' => []];
        foreach ($compiled as $path => $entity) {
            $summary = ['source_path' => $path, 'entity_type' => $entity['entity_type'], 'code' => $entity['code']];
            if (! isset($current[$path])) {
                $items['created'][] = $summary;
            } elseif (! hash_equals($this->payloadHash($current[$path]['payload']), $this->payloadHash($entity['payload']))) {
                $items['changed'][] = $summary;
            } else {
                $items['unchanged'][] = $summary;
            }
        }
        foreach (array_diff_key($current, $compiled) as $path => $entity) {
            $items['removed'][] = ['source_path' => $path, 'entity_type' => $entity['entity_type'], 'code' => $entity['code']];
        }
        $unclassified = (int) ($inventory['coverage']['unclassified_source_blocks'] ?? -1);

        return [
            'summary' => [
                'created' => count($items['created']),
                'changed' => count($items['changed']),
                'removed' => count($items['removed']),
                'unchanged' => count($items['unchanged']),
                'rejected' => 0,
                'unclassified' => $unclassified,
            ],
            'items' => $items,
            'acceptance_effect' => 'replace_selected_draft_workspace_only',
        ];
    }

    private function safeMessage(string $message): string
    {
        $message = preg_replace('/[A-Za-z]:[\\\\\/][^\r\n]*/', '[private path]', $message) ?? 'Import failed.';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? 'Import failed.');

        return mb_substr($message !== '' ? $message : 'Import failed.', 0, 800);
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('A required compiler output file is unavailable.');
        }
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('A required compiler output file is malformed.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $contents = CanonicalJson::encode($data, pretty: true)."\n";
        if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('The private import evidence could not be written.');
        }
    }

    /** @return list<array{path:string,sha256:string,bytes:int}> */
    private function manifest(string $root): array
    {
        $manifest = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $manifest[] = [
                    'path' => str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)),
                    'sha256' => (string) hash_file('sha256', $file->getPathname()),
                    'bytes' => $file->getSize(),
                ];
            }
        }
        usort($manifest, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $manifest;
    }

    /** @param list<array{path:string,sha256:string,bytes:int}> $manifest */
    private function treeSha256(array $manifest): string
    {
        $descriptor = '';
        foreach ($manifest as $file) {
            $descriptor .= $file['path']."\0".$file['sha256']."\0".$file['bytes']."\n";
        }

        return hash('sha256', $descriptor);
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', CanonicalJson::encode($payload));
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1
            ? $path
            : base_path(str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}
