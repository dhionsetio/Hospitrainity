<?php

namespace App\Services\Curriculum;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CanonicalPackageReader
{
    private const PUBLISHED_ENTITY_TYPES = [
        'chapter',
        'lesson-section',
        'activity',
        'prompt-item',
        'answer-model',
        'feedback-model',
        'rubric',
    ];

    /**
     * @throws JsonException
     */
    public function read(?string $root = null, ?string $evidencePath = null): CanonicalPackage
    {
        $root = realpath($root ?? config('curriculum.package_path'));
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('Canonical curriculum package directory does not exist.');
        }

        $evidence = $this->readJsonFile($evidencePath ?? config('curriculum.evidence_path'));
        $sourceFiles = $this->sourceManifest($root);
        $treeSha256 = $this->treeSha256($sourceFiles);
        $byteCount = array_sum(array_column($sourceFiles, 'bytes'));

        $this->assertSame((int) $evidence['package']['file_count'], count($sourceFiles), 'source file count');
        $this->assertSame((int) $evidence['package']['byte_count'], $byteCount, 'source byte count');
        $this->assertHash($evidence['package']['tree_sha256'], $treeSha256, 'canonical package tree');

        $jsonFiles = array_values(array_filter(
            $sourceFiles,
            static fn (array $file): bool => str_ends_with($file['path'], '.json'),
        ));
        $this->assertSame((int) $evidence['package']['json_file_count'], count($jsonFiles), 'JSON file count');

        $documents = [];
        foreach ($jsonFiles as $file) {
            $documents[$file['path']] = $this->readJsonFile($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file['path']));
        }

        $metadata = $documents['package.json'] ?? throw new RuntimeException('package.json is missing from the canonical package.');
        $this->validatePackageMetadata($metadata);
        $this->validateDeclaredChecksums($root, $metadata);

        [$entities, $links] = $this->buildEntitiesAndLinks($documents, $sourceFiles, $metadata);
        $counts = $this->validateSemantics($entities, $links, $evidence, $metadata);

        return new CanonicalPackage(
            root: $root,
            metadata: $metadata,
            sourceFiles: $sourceFiles,
            entities: $entities,
            links: $links,
            counts: $counts,
            evidence: $evidence,
            treeSha256: $treeSha256,
            byteCount: $byteCount,
        );
    }

    /** @return list<array{path: string, sha256: string, bytes: int}> */
    private function sourceManifest(string $root): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $sha256 = hash_file('sha256', $file->getPathname());
            if ($sha256 === false) {
                throw new RuntimeException("Unable to hash canonical source file: {$path}");
            }

            $files[] = [
                'path' => $path,
                'sha256' => $sha256,
                'bytes' => $file->getSize(),
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $files;
    }

    /** @param list<array{path: string, sha256: string, bytes: int}> $files */
    private function treeSha256(array $files): string
    {
        $descriptor = '';
        foreach ($files as $file) {
            $descriptor .= $file['path']."\0".$file['sha256']."\0".$file['bytes']."\n";
        }

        return hash('sha256', $descriptor);
    }

    /**
     * @param  array<string, array<string, mixed>>  $documents
     * @param  list<array{path: string, sha256: string, bytes: int}>  $sourceFiles
     * @param  array<string, mixed>  $metadata
     * @return array{list<array<string, mixed>>, list<array<string, mixed>>}
     */
    private function buildEntitiesAndLinks(array $documents, array $sourceFiles, array $metadata): array
    {
        $hashes = [];
        foreach ($sourceFiles as $file) {
            $hashes[$file['path']] = $file['sha256'];
        }

        $entities = [];
        foreach ($documents as $path => $payload) {
            if (isset($payload['entity_type'])) {
                $entities[] = $this->sourceEntity($path, $hashes[$path], $payload);

                continue;
            }

            $documentType = str_starts_with($path, 'framework/')
                ? 'framework-document'
                : (str_starts_with($path, 'provenance/') ? 'provenance-document' : 'package-manifest');

            $entities[] = [
                'entity_uuid' => null,
                'code' => 'DOC-'.strtoupper(str_replace(['/', '.', '_'], '-', preg_replace('/\.json$/', '', $path))),
                'entity_type' => $documentType,
                'parent_code' => null,
                'position' => null,
                'lifecycle_status' => $payload['status'] ?? null,
                'content_version' => $payload['content_version'] ?? $payload['version'] ?? null,
                'source_path' => $path,
                'source_sha256' => $hashes[$path],
                'payload' => $payload,
            ];
        }

        $this->appendFrameworkEntities(
            entities: $entities,
            path: 'framework/competencies.json',
            type: 'competency',
            collection: 'competencies',
            payload: $documents['framework/competencies.json'],
        );
        $this->appendFrameworkEntities(
            entities: $entities,
            path: 'framework/cefr-references.json',
            type: 'cefr-reference',
            collection: 'references',
            payload: $documents['framework/cefr-references.json'],
        );
        $this->appendFrameworkEntities(
            entities: $entities,
            path: 'framework/outcome-alignments.json',
            type: 'outcome',
            collection: 'outcomes',
            payload: $documents['framework/outcome-alignments.json'],
        );

        usort($entities, static fn (array $left, array $right): int => strcmp($left['source_path'], $right['source_path']));

        $links = $this->buildLinks($entities);
        usort($links, static function (array $left, array $right): int {
            return [$left['source_code'], $left['relationship'], $left['position'], $left['target_code']]
                <=> [$right['source_code'], $right['relationship'], $right['position'], $right['target_code']];
        });

        return [$entities, $links];
    }

    /** @param array<string, mixed> $payload */
    private function sourceEntity(string $path, string $sha256, array $payload): array
    {
        $type = $payload['entity_type'];
        $code = $payload['code'] ?? null;
        if ($type === 'migration-edge') {
            $code = pathinfo($path, PATHINFO_FILENAME);
        }

        $parentCode = match ($type) {
            'lesson-section' => $payload['chapter_code'],
            'activity' => $payload['lesson_code'],
            'prompt-item' => $payload['activity_code'],
            'answer-model', 'feedback-model' => $payload['prompt_code'],
            'rubric' => $payload['activity_code'],
            'source-provenance' => $payload['target_code'],
            default => null,
        };

        return [
            'entity_uuid' => $payload['id'] ?? null,
            'code' => $code,
            'entity_type' => $type,
            'parent_code' => $parentCode,
            'position' => isset($payload['order']) ? (int) $payload['order'] : null,
            'lifecycle_status' => $payload['status'] ?? null,
            'content_version' => $payload['content_version'] ?? null,
            'source_path' => $path,
            'source_sha256' => $sha256,
            'payload' => $payload,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     * @param  array<string, mixed>  $payload
     */
    private function appendFrameworkEntities(array &$entities, string $path, string $type, string $collection, array $payload): void
    {
        foreach ($payload[$collection] ?? [] as $index => $item) {
            $code = $item['id'] ?? throw new RuntimeException("Framework item in {$path} has no id.");
            $canonical = CanonicalJson::encode($item);
            $entities[] = [
                'entity_uuid' => null,
                'code' => $code,
                'entity_type' => $type,
                'parent_code' => null,
                'position' => $index + 1,
                'lifecycle_status' => $item['status'] ?? $item['verification_status'] ?? null,
                'content_version' => $payload['version'] ?? $payload['registry_version'] ?? null,
                'source_path' => $path.'#/'.$collection.'/'.$code,
                'source_sha256' => hash('sha256', $canonical),
                'payload' => $item,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     * @return list<array<string, mixed>>
     */
    private function buildLinks(array $entities): array
    {
        $links = [];
        foreach ($entities as $entity) {
            $payload = $entity['payload'];
            $source = $entity['code'];
            if ($source === null) {
                continue;
            }

            if ($entity['parent_code'] !== null) {
                $relationship = match ($entity['entity_type']) {
                    'lesson-section' => 'belongs-to-chapter',
                    'activity' => 'belongs-to-section',
                    'prompt-item' => 'belongs-to-activity',
                    'answer-model', 'feedback-model' => 'describes-prompt',
                    'rubric' => 'assesses-activity',
                    'source-provenance' => 'documents-target',
                    default => 'belongs-to',
                };
                $links[] = $this->link($source, $relationship, $entity['parent_code'], $entity['position'] ?? 0);
            }

            foreach ($payload['outcome_codes'] ?? [] as $position => $target) {
                $links[] = $this->link($source, 'aligns-to-outcome', $target, $position + 1);
            }
            foreach ($payload['competency_ids'] ?? [] as $position => $target) {
                $links[] = $this->link($source, 'aligns-to-competency', $target, $position + 1);
            }
            foreach ($payload['cefr_reference_ids'] ?? [] as $position => $target) {
                $links[] = $this->link($source, 'references-cefr', $target, $position + 1);
            }
            foreach (['replaces', 'replaced_by'] as $relationship) {
                if (! empty($payload[$relationship])) {
                    $links[] = $this->link($source, str_replace('_', '-', $relationship), $payload[$relationship], 0);
                }
            }
            if ($entity['entity_type'] === 'migration-edge' && ! empty($payload['canonical_code'])) {
                $links[] = $this->link($source, 'migrates-to', $payload['canonical_code'], 0, [
                    'disposition' => $payload['disposition'] ?? null,
                    'legacy_ref' => $payload['legacy_ref'] ?? null,
                ]);
            }
        }

        return $links;
    }

    /** @return array<string, mixed> */
    private function link(string $source, string $relationship, string $target, int $position, ?array $metadata = null): array
    {
        return [
            'source_code' => $source,
            'relationship' => $relationship,
            'target_code' => $target,
            'position' => $position,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     * @param  list<array<string, mixed>>  $links
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $metadata
     * @return array<string, int>
     */
    private function validateSemantics(array $entities, array $links, array $evidence, array $metadata): array
    {
        $expectedMap = [
            'chapters' => 'chapter',
            'sections' => 'lesson-section',
            'activities' => 'activity',
            'prompts' => 'prompt-item',
            'answer_models' => 'answer-model',
            'feedback_models' => 'feedback-model',
            'rubrics' => 'rubric',
            'outcomes' => 'outcome',
            'competencies' => 'competency',
            'cefr_references' => 'cefr-reference',
            'source_provenance' => 'source-provenance',
            'migration_edges' => 'migration-edge',
        ];

        $counts = [];
        foreach ($expectedMap as $name => $type) {
            $counts[$name] = count(array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === $type));
            $this->assertSame((int) $evidence['expected_counts'][$name], $counts[$name], $name);
        }

        $codes = [];
        $uuids = [];
        foreach ($entities as $entity) {
            $code = $entity['code'];
            if ($code !== null) {
                if (isset($codes[$code])) {
                    throw new RuntimeException("Duplicate curriculum code: {$code}");
                }
                $codes[$code] = $entity;
            }

            $uuid = $entity['entity_uuid'];
            if ($uuid !== null) {
                if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
                    throw new RuntimeException("Invalid entity UUID for {$code}: {$uuid}");
                }
                if (isset($uuids[$uuid])) {
                    throw new RuntimeException("Duplicate curriculum entity UUID: {$uuid}");
                }
                $uuids[$uuid] = true;
            }

            if (isset($entity['payload']['content_version'])) {
                $this->assertSame($metadata['content_version'], $entity['payload']['content_version'], "content version for {$code}");
            }

            if (in_array($entity['entity_type'], self::PUBLISHED_ENTITY_TYPES, true)) {
                $this->assertSame('published', $entity['lifecycle_status'], "lifecycle status for {$code}");
            }

            $locator = $entity['payload']['source_locator'] ?? null;
            if ($locator !== null && ! preg_match('/^[0-9a-f]{64}$/', (string) ($locator['normalized_text_sha256'] ?? ''))) {
                throw new RuntimeException("Invalid source locator checksum for {$code}.");
            }
        }

        foreach ($links as $link) {
            if (! isset($codes[$link['source_code']])) {
                throw new RuntimeException("Link source does not exist: {$link['source_code']}");
            }
            if (! isset($codes[$link['target_code']])) {
                throw new RuntimeException("Link target does not exist: {$link['target_code']}");
            }
        }

        $modules = array_map(
            static fn (array $entity): int => (int) $entity['payload']['module'],
            array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'chapter'),
        );
        sort($modules, SORT_NUMERIC);
        $this->assertSame(range(1, 7), $modules, 'chapter module sequence');

        foreach (array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'chapter') as $chapter) {
            $orders = array_map(
                static fn (array $entity): int => (int) $entity['position'],
                array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'lesson-section' && $entity['parent_code'] === $chapter['code']),
            );
            sort($orders, SORT_NUMERIC);
            $this->assertSame(range(1, count($orders)), $orders, "section order for {$chapter['code']}");
        }

        foreach (array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'prompt-item') as $prompt) {
            $answers = count(array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'answer-model' && $entity['parent_code'] === $prompt['code']));
            $feedback = count(array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'feedback-model' && $entity['parent_code'] === $prompt['code']));
            if (($prompt['payload']['response_form'] ?? null) === 'rating') {
                $this->assertSame(0, $answers, "answer model count for rating {$prompt['code']}");
                $this->assertSame(0, $feedback, "feedback model count for rating {$prompt['code']}");
            } else {
                $this->assertSame(1, $answers, "answer model count for {$prompt['code']}");
                $this->assertSame(1, $feedback, "feedback model count for {$prompt['code']}");
            }
        }

        $this->validateStructuredContent($entities, $evidence);
        $this->validateResponseSemantics($entities);
        $this->validateExerciseTemplateSemantics($entities);

        return $counts;
    }

    /** @param list<array<string, mixed>> $entities @param array<string, mixed> $evidence */
    private function validateStructuredContent(array $entities, array $evidence): void
    {
        $allowedTypes = ['paragraph', 'heading', 'callout', 'list_item', 'dialogue_turn', 'source_table', 'external_link', 'instruction', 'activity_embed'];
        $blockIds = [];
        $blockCount = 0;
        $tableCount = 0;
        $linkCount = 0;
        foreach (array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'lesson-section') as $section) {
            $blocks = $section['payload']['blocks'] ?? null;
            if (! is_array($blocks) || $blocks === []) {
                throw new RuntimeException("Lesson section {$section['code']} has no ordered content blocks.");
            }
            foreach (array_values($blocks) as $index => $block) {
                if (! is_array($block)) {
                    throw new RuntimeException("Lesson section {$section['code']} contains a malformed content block.");
                }
                $this->assertSame($index + 1, $block['order'] ?? null, "block order in {$section['code']}");
                $type = $block['type'] ?? null;
                if (! is_string($type) || ! in_array($type, $allowedTypes, true)) {
                    throw new RuntimeException("Unsupported content block type in {$section['code']}.");
                }
                $id = $block['id'] ?? null;
                if (! is_string($id) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id) !== 1) {
                    throw new RuntimeException("Invalid content block ID in {$section['code']}.");
                }
                if (isset($blockIds[$id])) {
                    throw new RuntimeException("Duplicate content block ID: {$id}");
                }
                $blockIds[$id] = true;
                if (($block['language'] ?? null) !== 'en' || ! in_array($block['provenance_kind'] ?? null, ['source_verbatim', 'derived_navigation', 'admin_authored'], true)) {
                    throw new RuntimeException("Invalid language or provenance for content block {$id}.");
                }
                $locatorHash = $block['source_locator']['normalized_text_sha256'] ?? null;
                if (! is_string($locatorHash) || preg_match('/^[0-9a-f]{64}$/', $locatorHash) !== 1) {
                    throw new RuntimeException("Invalid source locator for content block {$id}.");
                }
                if ($type === 'source_table') {
                    if (! is_array($block['header'] ?? null) || ! is_array($block['rows'] ?? null) || $block['header'] === []) {
                        throw new RuntimeException("Malformed source table content block {$id}.");
                    }
                    $width = count($block['header']);
                    foreach ($block['rows'] as $row) {
                        if (! is_array($row) || count($row) !== $width) {
                            throw new RuntimeException("Malformed source table row in content block {$id}.");
                        }
                    }
                    $tableCount++;
                }
                if ($type === 'external_link') {
                    foreach ($block['links'] ?? [] as $link) {
                        if (! is_array($link) || ! filter_var($link['target'] ?? null, FILTER_VALIDATE_URL) || trim((string) ($link['text'] ?? '')) === '') {
                            throw new RuntimeException("Malformed external link in content block {$id}.");
                        }
                        $linkCount++;
                    }
                }
                $blockCount++;
            }
        }
        $coverage = $evidence['coverage_counts'] ?? [];
        $this->assertSame((int) ($coverage['content_blocks'] ?? -1), $blockCount, 'content block count');
        $this->assertSame((int) ($coverage['source_tables'] ?? -1), $tableCount, 'source table block count');
        $this->assertSame((int) ($coverage['external_hyperlink_relationships'] ?? -1), $linkCount, 'external hyperlink relationship count');
    }

    /** @param list<array<string, mixed>> $entities */
    private function validateResponseSemantics(array $entities): void
    {
        $answers = [];
        foreach ($entities as $entity) {
            if ($entity['entity_type'] === 'answer-model') {
                $answers[$entity['parent_code']] = $entity['payload'];
            }
            if ($entity['entity_type'] === 'activity') {
                $rule = $entity['payload']['completion_rule'] ?? null;
                if (! in_array($rule, [
                    'all_required_items_attempted_and_checked',
                    'all_required_responses_and_self_checks_completed',
                    'all_rated',
                    'all_rated_or_explicitly_skipped',
                ], true) || ($entity['payload']['completion_rule_provenance_kind'] ?? null) !== 'derived_workflow') {
                    throw new RuntimeException("Activity {$entity['code']} lacks a supported derived completion rule.");
                }
            }
        }
        foreach (array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'prompt-item') as $prompt) {
            $payload = $prompt['payload'];
            $form = $payload['response_form'] ?? null;
            $answer = $answers[$prompt['code']] ?? null;
            if ($form === 'selection') {
                $choices = $payload['choices'] ?? [];
                if (! is_array($choices) || count($choices) < 2) {
                    throw new RuntimeException("Selection prompt {$prompt['code']} has no structured choices.");
                }
                $ids = array_column($choices, 'id');
                if (count($ids) !== count(array_unique($ids)) || array_filter($choices, static fn (array $choice): bool => array_key_exists('correct', $choice)) !== []) {
                    throw new RuntimeException("Selection prompt {$prompt['code']} has invalid or answer-leaking choice identity.");
                }
                $correctIds = $answer['correct_choice_ids'] ?? [];
                if (count($correctIds) !== 1 || ! in_array($correctIds[0], $ids, true)) {
                    throw new RuntimeException("Selection answer model for {$prompt['code']} does not reference an allowed choice.");
                }
            } elseif ($form === 'ordering') {
                $tokens = $payload['tokens'] ?? [];
                $tokenIds = is_array($tokens) ? array_column($tokens, 'id') : [];
                $correctOrder = $answer['correct_order'] ?? [];
                $sortedTokens = $tokenIds;
                $sortedCorrect = is_array($correctOrder) ? $correctOrder : [];
                sort($sortedTokens, SORT_STRING);
                sort($sortedCorrect, SORT_STRING);
                if (count($tokenIds) < 2 || count($tokenIds) !== count(array_unique($tokenIds)) || $sortedTokens !== $sortedCorrect) {
                    throw new RuntimeException("Ordering prompt {$prompt['code']} has an invalid token permutation.");
                }
            } elseif ($form === 'rating') {
                $scale = $payload['rating_scale'] ?? [];
                if (($scale['min'] ?? null) !== 1 || ($scale['max'] ?? null) !== 5 || ($payload['scoring_mode'] ?? null) !== 'unscored_self_report') {
                    throw new RuntimeException("Rating prompt {$prompt['code']} is not a structured unscored 1-5 item.");
                }
            } elseif (in_array($form, ['role_play', 'service_artifact'], true) || ($form === 'short_text' && ($payload['scoring_mode'] ?? null) === 'model_self_check')) {
                if (($payload['self_check_required'] ?? false) !== true || ! is_array($payload['response_constraints'] ?? null)) {
                    throw new RuntimeException("Open response prompt {$prompt['code']} lacks response/self-check semantics.");
                }
            } elseif ($form === 'short_text' && ($payload['scoring_mode'] ?? null) === 'objective_normalized_closed') {
                $accepted = $answer['accepted_normalized'] ?? null;
                if (! is_array($accepted) || $accepted === [] || ($answer['accepted_normalized_provenance_kind'] ?? null) !== 'derived_scoring') {
                    throw new RuntimeException("Closed response prompt {$prompt['code']} lacks reviewed normalized answers.");
                }
                foreach ($accepted as $value) {
                    if (! is_string($value) || $value === '' || $value !== $this->normalizeClosedResponse($value)) {
                        throw new RuntimeException("Closed response prompt {$prompt['code']} contains a non-normalized accepted answer.");
                    }
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $entities */
    private function validateExerciseTemplateSemantics(array $entities): void
    {
        $registry = new CanonicalExerciseTemplateRegistry;
        foreach (array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'activity' && isset($entity['payload']['template_type'])) as $activity) {
            $payload = $activity['payload'];
            $type = $payload['template_type'];
            if (! is_string($type) || ! $registry->has($type)) {
                throw new RuntimeException("Activity {$activity['code']} uses an unavailable exercise template.");
            }
            $definition = $registry->get($type);
            if ($definition['enabled'] !== true) {
                throw new RuntimeException("Activity {$activity['code']} uses an exercise template that is mapped but not approved for delivery.");
            }
            if (($payload['template_registry_version'] ?? null) !== CanonicalExerciseTemplateRegistry::VERSION) {
                throw new RuntimeException("Activity {$activity['code']} uses an unsupported exercise-template registry version.");
            }
            if (($payload['response_form'] ?? null) !== $definition['response_form'] || ($payload['scoring_mode'] ?? null) !== $definition['scoring_mode']) {
                throw new RuntimeException("Activity {$activity['code']} diverges from its exercise-template response/scoring contract.");
            }
            $prompts = array_values(array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'prompt-item' && $entity['parent_code'] === $activity['code']));
            $count = count($prompts);
            if ($count < $definition['cardinality']['minimum'] || $count > $definition['cardinality']['maximum']) {
                throw new RuntimeException("Activity {$activity['code']} violates its exercise-template item cardinality.");
            }
            foreach ($prompts as $prompt) {
                if (($prompt['payload']['response_form'] ?? null) !== $definition['response_form'] || ($prompt['payload']['scoring_mode'] ?? null) !== $definition['scoring_mode']) {
                    throw new RuntimeException("Prompt {$prompt['code']} diverges from activity {$activity['code']} template semantics.");
                }
            }
            if ($definition['audio_required']) {
                $asset = $payload['audio_asset'] ?? null;
                if (! is_array($asset)
                    || ! is_string($asset['accessibility_text'] ?? null)
                    || trim($asset['accessibility_text']) === ''
                    || ! is_string($asset['path'] ?? null)
                    || preg_match('#^assets/[0-9a-f]{64}\.(mp3|wav)$#', $asset['path']) !== 1) {
                    throw new RuntimeException("Activity {$activity['code']} lacks its required reviewed audio asset/accessibility description.");
                }
            }
            if ($definition['rubric_required']) {
                $rubrics = array_filter($entities, static fn (array $entity): bool => $entity['entity_type'] === 'rubric' && $entity['parent_code'] === $activity['code']);
                if (count($rubrics) !== 1) {
                    throw new RuntimeException("Activity {$activity['code']} requires exactly one self-assessment rubric.");
                }
            }
        }
    }

    private function normalizeClosedResponse(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** @param array<string, mixed> $metadata */
    private function validatePackageMetadata(array $metadata): void
    {
        foreach (['package', 'content_version', 'schema_version', 'namespace', 'status', 'checksums'] as $key) {
            if (! array_key_exists($key, $metadata)) {
                throw new RuntimeException("Canonical package metadata is missing {$key}.");
            }
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $metadata['namespace'])) {
            throw new RuntimeException('Canonical package namespace is not a UUID.');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function validateDeclaredChecksums(string $root, array $metadata): void
    {
        foreach ($metadata['checksums'] as $relative => $expected) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_file($path)) {
                throw new RuntimeException("Checksum-declared file does not exist: {$relative}");
            }
            $actual = hash_file('sha256', $path);
            if ($actual === false) {
                throw new RuntimeException("Unable to hash checksum-declared file: {$relative}");
            }
            $this->assertHash($expected, $actual, $relative);
        }
    }

    /** @return array<string, mixed> */
    private function readJsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read JSON file: {$path}");
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("JSON document must contain an object: {$path}");
        }

        return $decoded;
    }

    private function assertHash(mixed $expected, mixed $actual, string $label): void
    {
        if (! is_string($expected) || ! is_string($actual) || ! hash_equals(strtolower($expected), strtolower($actual))) {
            throw new RuntimeException("Checksum mismatch for {$label}. Expected {$expected}; found {$actual}.");
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                'Unexpected %s. Expected %s; found %s.',
                $label,
                json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));
        }
    }
}
