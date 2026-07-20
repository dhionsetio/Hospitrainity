<?php

namespace App\Services\Curriculum;

use App\Models\Completion;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumEntity;
use App\Models\CurriculumImportRun;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\SearchIndexBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CanonicalCurriculumImporter
{
    public function __construct(
        private readonly StandaloneGenerator $standalone,
        private readonly CurriculumArtifactStore $artifacts,
        private readonly CurriculumReleaseGuard $releaseGuard,
        private readonly SearchIndexBuilder $searchIndex,
    ) {}

    /** @return array<string, mixed> */
    public function plan(CanonicalPackage $source): array
    {
        $active = CurriculumPackage::active();
        $desiredSha256 = $this->desiredProjectionSha256($source);
        $currentSha256 = $active === null ? null : $this->activeProjectionSha256();

        $status = match (true) {
            $active === null => 'create',
            $active->source_tree_sha256 === $source->treeSha256 && hash_equals($desiredSha256, (string) $currentSha256) => 'no_changes',
            $active->source_tree_sha256 === $source->treeSha256 => 'repair_projection',
            default => 'replace_active_projection',
        };

        return [
            'mode' => 'dry-run',
            'status' => $status,
            'source' => $this->sourceSummary($source, $desiredSha256),
            'current' => $active === null ? null : [
                'package' => $active->package_name,
                'content_version' => $active->content_version,
                'source_tree_sha256' => $active->source_tree_sha256,
                'laravel_projection_sha256' => $currentSha256,
                'counts' => $active->counts,
            ],
            'count_diff' => $this->countDiff($active?->counts ?? [], $source->counts),
            'legacy_disposition' => $this->legacyDisposition($source),
            'writes_planned' => $status === 'no_changes' ? 0 : count($source->sourceFiles) + count($source->entities) + count($source->links) + 1,
        ];
    }

    /** @return array<string, mixed> */
    public function import(CanonicalPackage $source, ?string $reportPath = null): array
    {
        $this->releaseGuard->assertImportMayActivate($source);
        $runId = (string) Str::uuid();
        $plan = $this->plan($source);
        $beforeSnapshot = $this->snapshot();
        $beforeSha256 = $this->databaseSnapshotSha256($beforeSnapshot);
        $desiredSha256 = $this->desiredProjectionSha256($source);
        $standaloneSha256 = hash('sha256', $this->standalone->render($source));

        if ($plan['status'] === 'no_changes') {
            $report = array_merge($plan, [
                'mode' => 'import',
                'run_id' => $runId,
                'status' => 'no_changes',
                'before_database_sha256' => $beforeSha256,
                'after_database_sha256' => $beforeSha256,
                'standalone_sha256' => $standaloneSha256,
                'rollback' => null,
            ]);
            $reportArtifact = $this->writeReport($runId, $report, $reportPath);
            $this->recordRun($runId, 'import', 'no_changes', CurriculumPackage::active()?->id, $source->treeSha256, $beforeSha256, $beforeSha256, $standaloneSha256, null, $reportArtifact, $report);
            $this->searchIndex->rebuild();

            return $report;
        }

        $rollbackPath = rtrim(config('curriculum.rollback_directory'), '\\/').DIRECTORY_SEPARATOR.$runId.'-before.json';
        $rollbackArtifact = $this->artifacts->writeJson($rollbackPath, $beforeSnapshot);
        $mutated = false;

        try {
            $packageId = DB::transaction(function () use ($source, $desiredSha256, $standaloneSha256): int {
                $versionCollision = CurriculumPackage::query()
                    ->where('package_name', $source->metadata['package'])
                    ->where('content_version', $source->metadata['content_version'])
                    ->where('source_tree_sha256', '!=', $source->treeSha256)
                    ->exists();
                if ($versionCollision) {
                    throw new RuntimeException('The declared content version already exists with a different source checksum. Publish a new version instead of mutating an existing one.');
                }

                CurriculumPackage::query()->where('is_active', true)->update(['is_active' => false]);

                $package = CurriculumPackage::query()->where('source_tree_sha256', $source->treeSha256)->first();
                if ($package === null) {
                    $package = CurriculumPackage::create([
                        'package_name' => $source->metadata['package'],
                        'content_version' => $source->metadata['content_version'],
                        'schema_version' => $source->metadata['schema_version'],
                        'namespace_uuid' => $source->metadata['namespace'],
                        'lifecycle_status' => $source->metadata['status'],
                        'source_path' => $source->evidence['package']['path'],
                        'source_tree_sha256' => $source->treeSha256,
                        'source_file_count' => count($source->sourceFiles),
                        'source_byte_count' => $source->byteCount,
                        'counts' => $source->counts,
                        'projection_meta' => $source->evidence['projection_meta'],
                        'laravel_projection_sha256' => $desiredSha256,
                        'standalone_sha256' => $standaloneSha256,
                        'is_active' => true,
                        'imported_at' => now(),
                    ]);

                    $this->insertSourceFiles($package->id, $source);
                    $this->insertEntities($package->id, $source);
                    $this->insertLinks($package->id, $source);
                } else {
                    $retainedEntityIds = DB::table('curriculum_entities')
                        ->where('curriculum_package_id', $package->id)
                        ->pluck('id', 'source_path')
                        ->map(static fn ($id): int => (int) $id)
                        ->all();
                    DB::table('curriculum_links')->where('curriculum_package_id', $package->id)->delete();
                    DB::table('curriculum_source_files')->where('curriculum_package_id', $package->id)->delete();
                    DB::table('curriculum_entities')->where('curriculum_package_id', $package->id)->delete();
                    $package->update([
                        'package_name' => $source->metadata['package'],
                        'content_version' => $source->metadata['content_version'],
                        'schema_version' => $source->metadata['schema_version'],
                        'namespace_uuid' => $source->metadata['namespace'],
                        'lifecycle_status' => $source->metadata['status'],
                        'source_path' => $source->evidence['package']['path'],
                        'source_file_count' => count($source->sourceFiles),
                        'source_byte_count' => $source->byteCount,
                        'counts' => $source->counts,
                        'projection_meta' => $source->evidence['projection_meta'],
                        'laravel_projection_sha256' => $desiredSha256,
                        'standalone_sha256' => $standaloneSha256,
                        'is_active' => true,
                        'imported_at' => now(),
                    ]);
                    $this->insertSourceFiles($package->id, $source);
                    $this->insertEntities($package->id, $source, $retainedEntityIds);
                    $this->insertLinks($package->id, $source);
                }

                $this->releaseGuard->recordImportedPackage($package);

                $this->mapLegacyCompletions();

                return (int) $package->id;
            }, attempts: 3);
            $mutated = true;

            $actualProjectionSha256 = $this->activeProjectionSha256();
            if (! hash_equals($desiredSha256, $actualProjectionSha256)) {
                throw new RuntimeException("Imported Laravel projection checksum mismatch. Expected {$desiredSha256}; found {$actualProjectionSha256}.");
            }

            $standaloneResult = $this->standalone->write($source);
            $afterSnapshot = $this->snapshot();
            $afterSha256 = $this->databaseSnapshotSha256($afterSnapshot);

            $report = array_merge($plan, [
                'mode' => 'import',
                'run_id' => $runId,
                'status' => 'imported',
                'package_id' => $packageId,
                'before_database_sha256' => $beforeSha256,
                'after_database_sha256' => $afterSha256,
                'standalone_sha256' => $standaloneResult['sha256'],
                'standalone_changed' => $standaloneResult['changed'],
                'rollback' => $rollbackArtifact,
                'post_import_counts' => $this->databaseCounts(),
            ]);
            $reportArtifact = $this->writeReport($runId, $report, $reportPath);
            $this->recordRun($runId, 'import', 'imported', $packageId, $source->treeSha256, $beforeSha256, $afterSha256, $standaloneResult['sha256'], $rollbackArtifact, $reportArtifact, $report);
            User::forgetAllProgressCaches();
            $this->searchIndex->rebuild();

            return $report;
        } catch (Throwable $exception) {
            if ($mutated) {
                $this->restoreSnapshot($beforeSnapshot);
            }

            $failed = [
                'mode' => 'import',
                'run_id' => $runId,
                'status' => 'failed_and_restored',
                'source' => $this->sourceSummary($source, $desiredSha256),
                'before_database_sha256' => $beforeSha256,
                'restored_database_sha256' => $this->databaseSnapshotSha256($this->snapshot()),
                'rollback' => $rollbackArtifact,
                'error' => ['type' => $exception::class, 'message' => $exception->getMessage()],
            ];
            $reportArtifact = $this->writeReport($runId, $failed, $reportPath);
            $this->recordRun($runId, 'import', 'failed_and_restored', null, $source->treeSha256, $beforeSha256, $beforeSha256, null, $rollbackArtifact, $reportArtifact, $failed);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function verify(CanonicalPackage $source): array
    {
        $active = CurriculumPackage::active();
        if ($active === null) {
            throw new RuntimeException('No canonical curriculum package is active.');
        }

        $desired = $this->desiredProjectionSha256($source);
        $actual = $this->activeProjectionSha256();
        if (! hash_equals($desired, $actual)) {
            throw new RuntimeException("Active Laravel projection checksum mismatch. Expected {$desired}; found {$actual}.");
        }
        if (! hash_equals($source->treeSha256, $active->source_tree_sha256)) {
            throw new RuntimeException('The active package does not match the approved source tree checksum.');
        }

        $standalone = config('curriculum.standalone_output');
        $standaloneSha256 = is_file($standalone) ? hash_file('sha256', $standalone) : false;
        if (! is_string($standaloneSha256) || ! hash_equals($source->evidence['standalone']['sha256'], $standaloneSha256)) {
            throw new RuntimeException('The standalone output is missing or does not match the approved canonical evidence checksum.');
        }

        $counts = $this->databaseCounts();
        foreach ($source->counts as $name => $expected) {
            if (($counts[$name] ?? null) !== $expected) {
                throw new RuntimeException("Imported {$name} count mismatch. Expected {$expected}; found ".($counts[$name] ?? 'missing').'.');
            }
        }

        return [
            'mode' => 'verify',
            'status' => 'verified',
            'source_tree_sha256' => $source->treeSha256,
            'laravel_projection_sha256' => $actual,
            'standalone_sha256' => $standaloneSha256,
            'counts' => $counts,
        ];
    }

    /** @return array<string, mixed> */
    public function rollback(string $path, ?string $reportPath = null): array
    {
        $realPath = realpath($path);
        $root = realpath(config('curriculum.rollback_directory'));
        if ($realPath === false || $root === false || ! str_starts_with(strtolower($realPath), strtolower($root.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Rollback artifact must be an existing file inside the configured rollback directory.');
        }

        $artifactSha256 = hash_file('sha256', $realPath);
        $sourceRun = is_string($artifactSha256)
            ? CurriculumImportRun::query()->where('rollback_sha256', $artifactSha256)->first()
            : null;
        $recordedPath = $sourceRun?->rollback_path === null ? false : realpath($sourceRun->rollback_path);
        if ($sourceRun === null || $sourceRun->rollback_sha256 === null || $recordedPath !== $realPath) {
            throw new RuntimeException('No recorded import run owns this rollback artifact.');
        }

        $snapshot = $this->artifacts->readVerifiedJson($realPath, $sourceRun->rollback_sha256);
        $this->releaseGuard->assertRollbackSnapshotMayRestore($snapshot);
        $runId = (string) Str::uuid();
        $before = $this->databaseSnapshotSha256($this->snapshot());
        $this->restoreSnapshot($snapshot);
        $after = $this->databaseSnapshotSha256($this->snapshot());

        $report = [
            'mode' => 'rollback',
            'run_id' => $runId,
            'status' => 'rolled_back',
            'source_import_run_id' => $sourceRun->id,
            'rollback_path' => $realPath,
            'rollback_sha256' => $sourceRun->rollback_sha256,
            'before_database_sha256' => $before,
            'after_database_sha256' => $after,
            'active_package' => CurriculumPackage::active()?->only(['package_name', 'content_version', 'source_tree_sha256']),
        ];
        $reportArtifact = $this->writeReport($runId, $report, $reportPath);
        $this->recordRun($runId, 'rollback', 'rolled_back', CurriculumPackage::active()?->id, null, $before, $after, null, null, $reportArtifact, $report);
        User::forgetAllProgressCaches();
        $this->searchIndex->rebuild();

        return $report;
    }

    private function insertSourceFiles(int $packageId, CanonicalPackage $source): void
    {
        foreach (array_chunk($source->sourceFiles, 250) as $chunk) {
            DB::table('curriculum_source_files')->insert(array_map(static fn (array $file): array => [
                'curriculum_package_id' => $packageId,
                'path' => $file['path'],
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
            ], $chunk));
        }
    }

    /** @param array<string, int> $retainedIdsBySourcePath */
    private function insertEntities(int $packageId, CanonicalPackage $source, array $retainedIdsBySourcePath = []): void
    {
        foreach (array_chunk($source->entities, 100) as $chunk) {
            DB::table('curriculum_entities')->insert(array_map(static function (array $entity) use ($packageId, $retainedIdsBySourcePath): array {
                $row = [
                    'curriculum_package_id' => $packageId,
                    'entity_uuid' => $entity['entity_uuid'],
                    'code' => $entity['code'],
                    'entity_type' => $entity['entity_type'],
                    'parent_code' => $entity['parent_code'],
                    'position' => $entity['position'],
                    'lifecycle_status' => $entity['lifecycle_status'],
                    'content_version' => $entity['content_version'],
                    'source_path' => $entity['source_path'],
                    'source_sha256' => $entity['source_sha256'],
                    'payload' => CanonicalJson::encode($entity['payload']),
                ];
                if (isset($retainedIdsBySourcePath[$entity['source_path']])) {
                    $row['id'] = $retainedIdsBySourcePath[$entity['source_path']];
                }

                return $row;
            }, $chunk));
        }
    }

    private function insertLinks(int $packageId, CanonicalPackage $source): void
    {
        foreach (array_chunk($source->links, 250) as $chunk) {
            DB::table('curriculum_links')->insert(array_map(static fn (array $link): array => [
                'curriculum_package_id' => $packageId,
                'source_code' => $link['source_code'],
                'relationship' => $link['relationship'],
                'target_code' => $link['target_code'],
                'position' => $link['position'],
                'metadata' => $link['metadata'] === null ? null : CanonicalJson::encode($link['metadata']),
            ], $chunk));
        }
    }

    /** @return array<string, mixed> */
    private function desiredProjection(CanonicalPackage $source): array
    {
        return [
            'package' => [
                'package_name' => $source->metadata['package'],
                'content_version' => $source->metadata['content_version'],
                'schema_version' => $source->metadata['schema_version'],
                'namespace_uuid' => $source->metadata['namespace'],
                'lifecycle_status' => $source->metadata['status'],
                'source_path' => $source->evidence['package']['path'],
                'source_tree_sha256' => $source->treeSha256,
                'source_file_count' => count($source->sourceFiles),
                'source_byte_count' => $source->byteCount,
                'counts' => $source->counts,
                'projection_meta' => $source->evidence['projection_meta'],
                'standalone_sha256' => $source->evidence['standalone']['sha256'],
            ],
            'source_files' => $source->sourceFiles,
            'entities' => $source->entities,
            'links' => $source->links,
        ];
    }

    private function desiredProjectionSha256(CanonicalPackage $source): string
    {
        return hash('sha256', CanonicalJson::encode($this->desiredProjection($source)));
    }

    private function activeProjectionSha256(): string
    {
        $active = CurriculumPackage::active() ?? throw new RuntimeException('No active canonical package.');
        $projection = [
            'package' => [
                'package_name' => $active->package_name,
                'content_version' => $active->content_version,
                'schema_version' => $active->schema_version,
                'namespace_uuid' => $active->namespace_uuid,
                'lifecycle_status' => $active->lifecycle_status,
                'source_path' => $active->source_path,
                'source_tree_sha256' => $active->source_tree_sha256,
                'source_file_count' => (int) $active->source_file_count,
                'source_byte_count' => (int) $active->source_byte_count,
                'counts' => $active->counts,
                'projection_meta' => $active->projection_meta,
                'standalone_sha256' => $active->standalone_sha256,
            ],
            'source_files' => DB::table('curriculum_source_files')->where('curriculum_package_id', $active->id)->orderBy('path')->get()->map(static fn ($row): array => [
                'path' => $row->path,
                'sha256' => $row->sha256,
                'bytes' => (int) $row->bytes,
            ])->all(),
            'entities' => DB::table('curriculum_entities')->where('curriculum_package_id', $active->id)->orderBy('source_path')->get()->map(static fn ($row): array => [
                'entity_uuid' => $row->entity_uuid,
                'code' => $row->code,
                'entity_type' => $row->entity_type,
                'parent_code' => $row->parent_code,
                'position' => $row->position === null ? null : (int) $row->position,
                'lifecycle_status' => $row->lifecycle_status,
                'content_version' => $row->content_version,
                'source_path' => $row->source_path,
                'source_sha256' => $row->source_sha256,
                'payload' => json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR),
            ])->all(),
            'links' => DB::table('curriculum_links')->where('curriculum_package_id', $active->id)
                ->orderBy('source_code')->orderBy('relationship')->orderBy('position')->orderBy('target_code')->get()
                ->map(static fn ($row): array => [
                    'source_code' => $row->source_code,
                    'relationship' => $row->relationship,
                    'target_code' => $row->target_code,
                    'position' => (int) $row->position,
                    'metadata' => $row->metadata === null ? null : json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR),
                ])->all(),
        ];

        return hash('sha256', CanonicalJson::encode($projection));
    }

    /** @return array<string, mixed> */
    private function sourceSummary(CanonicalPackage $source, string $projectionSha256): array
    {
        return [
            'package' => $source->metadata['package'],
            'content_version' => $source->metadata['content_version'],
            'schema_version' => $source->metadata['schema_version'],
            'source_tree_sha256' => $source->treeSha256,
            'source_file_count' => count($source->sourceFiles),
            'source_byte_count' => $source->byteCount,
            'laravel_projection_sha256' => $projectionSha256,
            'standalone_sha256' => $source->evidence['standalone']['sha256'],
            'counts' => $source->counts,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $tables = [];
        foreach ($this->snapshotTables() as $table) {
            $rows = DB::table($table)->orderBy('id')->get()->map(function ($row) use ($table): array {
                $data = (array) $row;
                foreach ($this->jsonColumns($table) as $column) {
                    if (isset($data[$column]) && is_string($data[$column])) {
                        $data[$column] = json_decode($data[$column], true, flags: JSON_THROW_ON_ERROR);
                    }
                }

                return $data;
            })->all();
            $tables[$table] = $rows;
        }

        $output = config('curriculum.standalone_output');
        $contents = is_file($output) ? file_get_contents($output) : false;

        return [
            'snapshot_version' => '1.0.0',
            'created_at' => now()->toIso8601String(),
            'tables' => $tables,
            'standalone' => $contents === false ? ['exists' => false] : [
                'exists' => true,
                'sha256' => hash('sha256', $contents),
                'base64' => base64_encode($contents),
            ],
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function databaseSnapshotSha256(array $snapshot): string
    {
        return hash('sha256', CanonicalJson::encode($snapshot['tables']));
    }

    /** @param array<string, mixed> $snapshot */
    private function restoreSnapshot(array $snapshot): void
    {
        $tables = $snapshot['tables'] ?? throw new RuntimeException('Rollback artifact has no database tables.');
        $draftReferences = $this->draftReferences();
        $retainedEntityIds = array_map(static fn (array $row): int => (int) $row['id'], $tables['curriculum_entities'] ?? []);
        $orphanIds = DB::table('curriculum_entities')->whereNotIn('id', $retainedEntityIds === [] ? [-1] : $retainedEntityIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        DB::transaction(function () use ($tables, $orphanIds, $draftReferences): void {
            if ($orphanIds !== []) {
                Completion::purgeForCompletableIds([CurriculumEntity::class => $orphanIds]);
            }

            if ($draftReferences !== null) {
                DB::table('curriculum_draft_entities')->whereNotNull('source_entity_id')->update(['source_entity_id' => null]);
                DB::table('curriculum_drafts')->update(['base_package_id' => null, 'published_package_id' => null]);
            }

            DB::table('curriculum_attempt_events')->delete();
            DB::table('curriculum_responses')->delete();
            DB::table('curriculum_attempts')->delete();
            DB::table('curriculum_activity_progress')->delete();
            // Release evidence is delete-restricted at the database boundary.
            // An explicit verified rollback snapshots and restores it in order.
            DB::table('curriculum_release_events')->delete();
            DB::table('curriculum_release_approvals')->delete();
            DB::table('curriculum_releases')->delete();
            DB::table('curriculum_packages')->delete();
            foreach ($this->snapshotTables() as $table) {
                $rows = $tables[$table] ?? [];
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table($table)->insert(array_map(function (array $row) use ($table): array {
                        foreach ($this->jsonColumns($table) as $column) {
                            if (array_key_exists($column, $row) && $row[$column] !== null) {
                                $row[$column] = CanonicalJson::encode($row[$column]);
                            }
                        }

                        return $row;
                    }, $chunk));
                }
            }
            if ($draftReferences !== null) {
                $this->restoreDraftReferences($draftReferences);
            }
        }, attempts: 3);

        $standalone = $snapshot['standalone'] ?? ['exists' => false];
        $output = config('curriculum.standalone_output');
        if ($standalone['exists'] ?? false) {
            $contents = base64_decode((string) ($standalone['base64'] ?? ''), true);
            if (! is_string($contents) || ! hash_equals((string) $standalone['sha256'], hash('sha256', $contents))) {
                throw new RuntimeException('Rollback standalone payload failed checksum verification.');
            }
            $this->artifacts->writeRaw($output, $contents);
        } elseif (is_file($output) && ! unlink($output)) {
            throw new RuntimeException("Unable to remove standalone output while restoring snapshot: {$output}");
        }
    }

    /** @return array{drafts: list<array<string, mixed>>, entities: list<array<string, mixed>>}|null */
    private function draftReferences(): ?array
    {
        if (! Schema::hasTable('curriculum_drafts') || ! Schema::hasTable('curriculum_draft_entities')) {
            return null;
        }

        $packages = CurriculumPackage::query()->get()->keyBy('id');
        $drafts = DB::table('curriculum_drafts')->get(['id', 'base_package_id', 'published_package_id'])->map(function ($row) use ($packages): array {
            $base = $row->base_package_id === null ? null : $packages->get($row->base_package_id);
            $published = $row->published_package_id === null ? null : $packages->get($row->published_package_id);

            return [
                'id' => (int) $row->id,
                'base_package' => $base?->only(['package_name', 'content_version']),
                'published_package' => $published?->only(['package_name', 'content_version']),
            ];
        })->all();
        $entities = DB::table('curriculum_draft_entities as draft_entity')
            ->join('curriculum_entities as source_entity', 'source_entity.id', '=', 'draft_entity.source_entity_id')
            ->join('curriculum_packages as package', 'package.id', '=', 'source_entity.curriculum_package_id')
            ->get([
                'draft_entity.id', 'source_entity.source_path', 'package.package_name', 'package.content_version',
            ])->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'source_path' => $row->source_path,
                'package_name' => $row->package_name,
                'content_version' => $row->content_version,
            ])->all();

        return compact('drafts', 'entities');
    }

    /** @param array{drafts: list<array<string, mixed>>, entities: list<array<string, mixed>>} $references */
    private function restoreDraftReferences(array $references): void
    {
        foreach ($references['drafts'] as $draft) {
            $values = [];
            foreach (['base_package', 'published_package'] as $reference) {
                $package = $draft[$reference];
                if ($package === null) {
                    continue;
                }
                $id = DB::table('curriculum_packages')->where('package_name', $package['package_name'])
                    ->where('content_version', $package['content_version'])->value('id');
                $values[$reference.'_id'] = $id;
            }
            if ($values !== []) {
                DB::table('curriculum_drafts')->where('id', $draft['id'])->update($values);
            }
        }
        foreach ($references['entities'] as $entity) {
            $sourceId = DB::table('curriculum_entities as source_entity')
                ->join('curriculum_packages as package', 'package.id', '=', 'source_entity.curriculum_package_id')
                ->where('package.package_name', $entity['package_name'])
                ->where('package.content_version', $entity['content_version'])
                ->where('source_entity.source_path', $entity['source_path'])
                ->value('source_entity.id');
            if ($sourceId !== null) {
                DB::table('curriculum_draft_entities')->where('id', $entity['id'])->update(['source_entity_id' => $sourceId]);
            }
        }
    }

    /** @return array<string, int> */
    private function databaseCounts(): array
    {
        $active = CurriculumPackage::active();
        if ($active === null) {
            return [];
        }

        $map = [
            'chapters' => 'chapter', 'sections' => 'lesson-section', 'activities' => 'activity',
            'prompts' => 'prompt-item', 'answer_models' => 'answer-model', 'feedback_models' => 'feedback-model',
            'rubrics' => 'rubric', 'outcomes' => 'outcome', 'competencies' => 'competency',
            'cefr_references' => 'cefr-reference', 'source_provenance' => 'source-provenance', 'migration_edges' => 'migration-edge',
        ];
        $counts = [];
        foreach ($map as $name => $type) {
            $counts[$name] = DB::table('curriculum_entities')->where('curriculum_package_id', $active->id)->where('entity_type', $type)->count();
        }

        return $counts;
    }

    /** @param array<string, int> $before @param array<string, int> $after */
    private function countDiff(array $before, array $after): array
    {
        $diff = [];
        foreach ($after as $name => $count) {
            $diff[$name] = ['before' => (int) ($before[$name] ?? 0), 'after' => $count, 'delta' => $count - (int) ($before[$name] ?? 0)];
        }

        return $diff;
    }

    /** @return array<string, mixed>|null */
    private function legacyDisposition(CanonicalPackage $source): ?array
    {
        foreach ($source->entities as $entity) {
            if ($entity['source_path'] === 'provenance/legacy-inventory.json') {
                return [
                    'source' => $entity['payload']['legacy_source'] ?? null,
                    'totals' => $entity['payload']['legacy_totals'] ?? null,
                    'disposition_counts' => $entity['payload']['disposition_counts'] ?? null,
                    'modules' => $entity['payload']['modules'] ?? null,
                ];
            }
        }

        return null;
    }

    /** @return list<string> */
    private function jsonColumns(string $table): array
    {
        return match ($table) {
            'curriculum_packages' => ['counts', 'projection_meta'],
            'curriculum_entities' => ['payload'],
            'curriculum_links' => ['metadata'],
            'curriculum_responses' => ['response'],
            'curriculum_release_events' => ['metadata'],
            default => [],
        };
    }

    /** @return list<string> */
    private function snapshotTables(): array
    {
        return [
            'curriculum_packages',
            'curriculum_source_files',
            'curriculum_entities',
            'curriculum_links',
            'curriculum_activity_progress',
            'curriculum_attempts',
            'curriculum_responses',
            'curriculum_attempt_events',
            'curriculum_releases',
            'curriculum_release_approvals',
            'curriculum_release_events',
        ];
    }

    private function mapLegacyCompletions(): void
    {
        $rows = DB::table('completions')
            ->join('curriculum_entities', function ($join): void {
                $join->on('curriculum_entities.id', '=', 'completions.completable_id')
                    ->where('completions.completable_type', '=', CurriculumEntity::class)
                    ->where('curriculum_entities.entity_type', '=', 'activity');
            })
            ->join('curriculum_packages', 'curriculum_packages.id', '=', 'curriculum_entities.curriculum_package_id')
            ->get([
                'completions.user_id',
                'completions.created_at as completed_created_at',
                'curriculum_packages.package_name',
                'curriculum_packages.content_version',
                'curriculum_entities.code as activity_code',
                'curriculum_entities.parent_code as section_code',
            ]);

        foreach ($rows as $row) {
            CurriculumActivityProgress::query()->updateOrCreate([
                'user_id' => $row->user_id,
                'package_name' => $row->package_name,
                'content_version' => $row->content_version,
                'activity_code' => $row->activity_code,
            ], [
                'section_code' => $row->section_code,
                'legacy_status' => 'legacy_reveal_only',
                'viewed_at' => $row->completed_created_at,
                'started_at' => $row->completed_created_at,
            ]);
        }
    }

    /** @param array<string, mixed> $report */
    private function writeReport(string $runId, array $report, ?string $path): array
    {
        $path ??= rtrim(config('curriculum.report_directory'), '\\/').DIRECTORY_SEPARATOR.$runId.'.json';

        return $this->artifacts->writeJson($path, $report);
    }

    /**
     * @param  array<string, mixed>|null  $rollback
     * @param  array<string, mixed>  $reportArtifact
     * @param  array<string, mixed>  $report
     */
    private function recordRun(
        string $id,
        string $action,
        string $status,
        ?int $packageId,
        ?string $sourceSha256,
        ?string $beforeSha256,
        ?string $afterSha256,
        ?string $standaloneSha256,
        ?array $rollback,
        array $reportArtifact,
        array $report,
    ): void {
        CurriculumImportRun::create([
            'id' => $id,
            'curriculum_package_id' => $packageId,
            'action' => $action,
            'status' => $status,
            'source_tree_sha256' => $sourceSha256,
            'before_database_sha256' => $beforeSha256,
            'after_database_sha256' => $afterSha256,
            'standalone_sha256' => $standaloneSha256,
            'rollback_path' => $rollback['path'] ?? null,
            'rollback_sha256' => $rollback['sha256'] ?? null,
            'report_path' => $reportArtifact['path'],
            'report' => $report,
        ]);
    }
}
