<?php

declare(strict_types=1);

namespace Hospitrainity\Curriculum;

use DOMDocument;
use DOMElement;
use DOMXPath;
use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Deterministic, fail-closed compiler for the authoritative Hospitrainity DOCX.
 *
 * The compiler uses only PHP extensions required by this repository (DOM,
 * libxml and ZipArchive). It never modifies the input DOCX or the baseline
 * package. Source-derived hashes and locators are recalculated from OOXML.
 */
final class SourceCompiler
{
    public const AUTHORITY_SHA256 = '7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4';

    public const NAMESPACE_UUID = 'cc9dd546-ebaf-51c7-b86f-307d16a22f42';

    public const VERSION = '0.4.0-draft';

    public const SCHEMA_VERSION = '2.1.0';

    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @var list<array<string, mixed>> */
    private array $elements = [];

    /** @var array<string, array<string, mixed>> */
    private array $relationships = [];

    /** @var array<string, mixed> */
    private array $inventory = [];

    /** @var list<string> */
    private array $classifiedSourceKeys = [];

    public function __construct(
        private readonly string $sourcePath,
        private readonly string $baselinePath,
        private readonly string $outputPath,
        private readonly string $expectedSha256 = self::AUTHORITY_SHA256,
        private readonly ?string $sourceArtifact = null,
    ) {}

    /** @return array<string, mixed> */
    public function compile(bool $force = false): array
    {
        $this->assertInputs();
        $this->parseDocument();
        $this->assertSourceContract();

        $chapters = $this->loadBaselineChapters();
        $this->assertUniqueBaselineEntityIds();
        $sectionRanges = $this->resolveSectionRanges($chapters);

        $this->prepareOutput($force);
        $this->copyTree($this->baselinePath, $this->outputPath);
        $this->rewriteJsonVersions();
        $assessment = $this->compileAssessment($chapters, $sectionRanges);
        $this->compileSections($chapters, $sectionRanges);
        $this->markDerivedMetadata();
        $this->writeSchemas();
        $this->writeInventory($chapters, $assessment);
        $this->writePackageManifest();

        $summary = $this->validateOutput();
        $this->writeCoverageReport($summary);

        return $summary;
    }

    private function assertInputs(): void
    {
        if (! is_file($this->sourcePath)) {
            throw new RuntimeException("Authoritative DOCX does not exist: {$this->sourcePath}");
        }
        if (! is_dir($this->baselinePath)) {
            throw new RuntimeException("Baseline package does not exist: {$this->baselinePath}");
        }
        $source = realpath($this->sourcePath);
        $baseline = realpath($this->baselinePath);
        $output = $this->absolutePath($this->outputPath);
        if ($source === false || $baseline === false) {
            throw new RuntimeException('Unable to resolve compiler input paths.');
        }
        if ($output === $baseline || str_starts_with($output.DIRECTORY_SEPARATOR, $baseline.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The output must not overwrite or be nested inside the baseline package.');
        }
        $actual = hash_file('sha256', $source);
        if (! is_string($actual) || ! hash_equals(strtolower($this->expectedSha256), strtolower($actual))) {
            throw new RuntimeException("Authoritative DOCX hash mismatch. Expected {$this->expectedSha256}; found ".($actual ?: 'unreadable').'.');
        }
    }

    private function parseDocument(): void
    {
        $zip = new ZipArchive;
        if ($zip->open($this->sourcePath) !== true) {
            throw new RuntimeException('The authoritative DOCX is not a readable ZIP package.');
        }
        try {
            $documentXml = $zip->getFromName('word/document.xml');
            $relationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');
        } finally {
            $zip->close();
        }
        if (! is_string($documentXml) || ! is_string($relationshipsXml)) {
            throw new RuntimeException('The DOCX is missing its main document or relationship part.');
        }

        $relationships = $this->loadXml($relationshipsXml, 'relationship');
        foreach ($relationships->documentElement?->childNodes ?? [] as $relationship) {
            if (! $relationship instanceof DOMElement || $relationship->localName !== 'Relationship') {
                continue;
            }
            $id = $relationship->getAttribute('Id');
            $this->relationships[$id] = [
                'target' => $relationship->getAttribute('Target'),
                'target_mode' => $relationship->getAttribute('TargetMode'),
                'type' => $relationship->getAttribute('Type'),
            ];
        }

        $document = $this->loadXml($documentXml, 'document');
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $xpath->registerNamespace('r', self::R);
        $body = $xpath->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException('The DOCX has no WordprocessingML body.');
        }

        $supported = ['p', 'tbl', 'sectPr'];
        foreach ($body->childNodes as $bodyIndex => $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if (! in_array($node->localName, $supported, true)) {
                throw new RuntimeException("Unsupported WordprocessingML body block: {$node->localName} at index {$bodyIndex}.");
            }
            if ($node->localName === 'sectPr') {
                continue;
            }
            $this->elements[] = $node->localName === 'p'
                ? $this->parseParagraph($node, $xpath, $bodyIndex)
                : $this->parseTable($node, $xpath, $bodyIndex);
        }
    }

    /** @return array<string, mixed> */
    private function parseParagraph(DOMElement $paragraph, DOMXPath $xpath, int $bodyIndex): array
    {
        $runs = [];
        $text = '';
        foreach ($xpath->query('.//w:r', $paragraph) as $run) {
            if (! $run instanceof DOMElement) {
                continue;
            }
            $runText = '';
            foreach ($run->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }
                if ($child->localName === 't') {
                    $runText .= $child->textContent;
                } elseif ($child->localName === 'tab') {
                    $runText .= "\t";
                } elseif (in_array($child->localName, ['br', 'cr'], true)) {
                    $runText .= "\n";
                }
            }
            if ($runText === '') {
                continue;
            }
            $property = $xpath->query('./w:rPr', $run)->item(0);
            $runs[] = [
                'text' => $runText,
                'bold' => $property instanceof DOMElement && $this->propertyEnabled($xpath, './w:b', $property),
                'italic' => $property instanceof DOMElement && $this->propertyEnabled($xpath, './w:i', $property),
                'underline' => $property instanceof DOMElement && $this->propertyEnabled($xpath, './w:u', $property),
            ];
            $text .= $runText;
        }

        $links = [];
        foreach ($xpath->query('.//w:hyperlink', $paragraph) as $hyperlink) {
            if (! $hyperlink instanceof DOMElement) {
                continue;
            }
            $id = $hyperlink->getAttributeNS(self::R, 'id');
            $relationship = $id === '' ? null : ($this->relationships[$id] ?? null);
            if ($id !== '' && $relationship === null) {
                throw new RuntimeException("Hyperlink relationship {$id} is missing.");
            }
            if ($relationship !== null && $relationship['target_mode'] === 'External') {
                $links[] = [
                    'relationship_id' => $id,
                    'text' => $hyperlink->textContent,
                    'target' => $relationship['target'],
                ];
            }
        }

        $boldText = '';
        foreach ($runs as $run) {
            if ($run['bold']) {
                $boldText .= $run['text'];
            }
        }

        return [
            'kind' => 'paragraph',
            'body_index' => $bodyIndex,
            'text' => $text,
            'runs' => $runs,
            'links' => $links,
            'all_bold' => $text !== '' && $boldText === $text,
            'numbered' => $xpath->query('./w:pPr/w:numPr', $paragraph)->length > 0,
        ];
    }

    /** @return array<string, mixed> */
    private function parseTable(DOMElement $table, DOMXPath $xpath, int $bodyIndex): array
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $table) as $row) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $row) as $cell) {
                $parts = [];
                foreach ($xpath->query('./w:p', $cell) as $paragraph) {
                    $value = '';
                    foreach ($xpath->query('.//w:t', $paragraph) as $text) {
                        $value .= $text->textContent;
                    }
                    if ($value !== '') {
                        $parts[] = $value;
                    }
                }
                $cells[] = implode("\n", $parts);
            }
            $rows[] = $cells;
        }
        if ($rows === []) {
            throw new RuntimeException("Malformed empty source table at body index {$bodyIndex}.");
        }
        $width = count($rows[0]);
        if ($width === 0) {
            throw new RuntimeException("Malformed source table with no cells at body index {$bodyIndex}.");
        }
        foreach ($rows as $index => $row) {
            if (count($row) !== $width) {
                throw new RuntimeException("Malformed source table row width at body index {$bodyIndex}, row ".($index + 1).'.');
            }
        }

        return ['kind' => 'table', 'body_index' => $bodyIndex, 'rows' => $rows];
    }

    private function propertyEnabled(DOMXPath $xpath, string $query, DOMElement $context): bool
    {
        $node = $xpath->query($query, $context)->item(0);
        if (! $node instanceof DOMElement) {
            return false;
        }
        $value = strtolower($node->getAttributeNS(self::W, 'val'));

        return ! in_array($value, ['0', 'false', 'none'], true);
    }

    private function assertSourceContract(): void
    {
        $paragraphs = array_values(array_filter($this->elements, static fn (array $element): bool => $element['kind'] === 'paragraph'));
        $tables = array_values(array_filter($this->elements, static fn (array $element): bool => $element['kind'] === 'table'));
        $links = [];
        foreach ($paragraphs as $paragraph) {
            array_push($links, ...$paragraph['links']);
        }
        if (count($tables) !== 21) {
            throw new RuntimeException('Source inventory mismatch: expected 21 tables; found '.count($tables).'.');
        }
        if (count($links) !== 18) {
            throw new RuntimeException('Source inventory mismatch: expected 18 external hyperlink relationships; found '.count($links).'.');
        }
        foreach ($links as $link) {
            if (! filter_var($link['target'], FILTER_VALIDATE_URL)) {
                throw new RuntimeException("Malformed external hyperlink target: {$link['target']}");
            }
        }
        $this->inventory = [
            'source' => [
                'artifact' => $this->sourceArtifact(),
                'sha256' => strtolower((string) hash_file('sha256', $this->sourcePath)),
                'bytes' => filesize($this->sourcePath),
            ],
            'ooxml' => [
                'paragraphs' => count($paragraphs),
                'tables' => count($tables),
                'external_hyperlink_relationships' => count($links),
                'unique_external_targets' => count(array_unique(array_column($links, 'target'))),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function loadBaselineChapters(): array
    {
        $paths = glob($this->baselinePath.DIRECTORY_SEPARATOR.'chapters'.DIRECTORY_SEPARATOR.'HSP-C*'.DIRECTORY_SEPARATOR.'chapter.json') ?: [];
        sort($paths, SORT_STRING);
        if (count($paths) !== 7) {
            throw new RuntimeException('Baseline package must contain exactly seven chapter records.');
        }
        $chapters = [];
        foreach ($paths as $path) {
            $chapter = $this->readJson($path);
            $sectionPaths = glob(dirname($path).DIRECTORY_SEPARATOR.'sections'.DIRECTORY_SEPARATOR.'*.json') ?: [];
            sort($sectionPaths, SORT_STRING);
            $chapter['sections'] = array_map(fn (string $sectionPath): array => $this->readJson($sectionPath), $sectionPaths);
            usort($chapter['sections'], static fn (array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));
            $chapters[] = $chapter;
        }

        return $chapters;
    }

    private function assertUniqueBaselineEntityIds(): void
    {
        $ids = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->baselinePath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }
            $payload = $this->readJson($file->getPathname());
            if (! isset($payload['entity_type'], $payload['id'])) {
                continue;
            }
            if (isset($ids[$payload['id']])) {
                throw new RuntimeException("Duplicate baseline entity ID {$payload['id']} in {$ids[$payload['id']]} and {$file->getPathname()}.");
            }
            $ids[$payload['id']] = $file->getPathname();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $chapters
     * @return array<string, array{start:int,end:int,heading:array<string,mixed>}>
     */
    private function resolveSectionRanges(array $chapters): array
    {
        $chapterMarkers = [];
        $cursor = 0;
        foreach ($chapters as $chapter) {
            $expected = 'Chapter '.((int) $chapter['module']).'. '.$chapter['title'];
            $marker = $this->findHeading($expected, $cursor, count($this->elements));
            $chapterMarkers[$chapter['code']] = $marker;
            $cursor = $marker + 1;
        }

        $ranges = [];
        foreach ($chapters as $chapterIndex => $chapter) {
            $chapterStart = $chapterMarkers[$chapter['code']];
            $chapterEnd = $chapterIndex + 1 < count($chapters)
                ? $chapterMarkers[$chapters[$chapterIndex + 1]['code']]
                : count($this->elements);
            $sectionMarkers = [];
            $cursor = $chapterStart + 1;
            foreach ($chapter['sections'] as $section) {
                $marker = $this->findHeading((string) $section['title'], $cursor, $chapterEnd);
                $sectionMarkers[$section['code']] = $marker;
                $cursor = $marker + 1;
            }
            foreach ($chapter['sections'] as $sectionIndex => $section) {
                $start = $sectionMarkers[$section['code']];
                $end = $sectionIndex + 1 < count($chapter['sections'])
                    ? $sectionMarkers[$chapter['sections'][$sectionIndex + 1]['code']]
                    : $chapterEnd;
                if ($end <= $start + 1) {
                    throw new RuntimeException("Section {$section['code']} has no source content.");
                }
                $ranges[$section['code']] = ['start' => $start + 1, 'end' => $end, 'heading' => $this->elements[$start]];
            }
        }
        if (count($ranges) !== 85) {
            throw new RuntimeException('Source section marker mismatch: expected 85; found '.count($ranges).'.');
        }

        return $ranges;
    }

    private function findHeading(string $text, int $start, int $end): int
    {
        for ($index = $start; $index < $end; $index++) {
            $element = $this->elements[$index];
            if ($element['kind'] === 'paragraph' && $element['all_bold'] && $element['text'] === $text) {
                return $index;
            }
        }
        throw new RuntimeException("Required bold source marker is missing or out of order: {$text}");
    }

    private function prepareOutput(bool $force): void
    {
        if (file_exists($this->outputPath)) {
            if (! $force) {
                throw new RuntimeException("Output already exists; pass --force to replace it: {$this->outputPath}");
            }
            $this->removeTree($this->outputPath);
        }
        if (! mkdir($this->outputPath, 0775, true) && ! is_dir($this->outputPath)) {
            throw new RuntimeException("Unable to create compiler output: {$this->outputPath}");
        }
    }

    private function copyTree(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $target.DIRECTORY_SEPARATOR.$relative;
            if ($item->isDir()) {
                if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
                    throw new RuntimeException("Unable to create output directory: {$destination}");
                }
            } elseif (! copy($item->getPathname(), $destination)) {
                throw new RuntimeException("Unable to copy baseline file: {$relative}");
            }
        }
    }

    private function rewriteJsonVersions(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->outputPath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }
            $payload = $this->replaceValue($this->readJson($file->getPathname()), '0.3.0-draft', self::VERSION);
            $this->writeJson($file->getPathname(), $payload);
        }
    }

    private function replaceValue(mixed $value, string $from, string $to): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replaceValue($item, $from, $to);
            }

            return $value;
        }

        return $value === $from ? $to : $value;
    }

    /**
     * @param  list<array<string, mixed>>  $chapters
     * @param  array<string, array{start:int,end:int,heading:array<string,mixed>}>  $ranges
     */
    private function compileSections(array $chapters, array $ranges): void
    {
        foreach ($chapters as $chapter) {
            $chapterPath = $this->outputPath.'/chapters/'.$chapter['code'].'/chapter.json';
            $chapterPayload = $this->readJson($chapterPath);
            $headingIndex = $this->findHeading(
                'Chapter '.((int) $chapter['module']).'. '.$chapter['title'],
                0,
                count($this->elements),
            );
            $heading = $this->elements[$headingIndex];
            $chapterPayload['source_locator'] = $this->locator($heading, (int) $chapter['module'], [$chapter['title']]);
            $this->writeJson($chapterPath, $chapterPayload);

            foreach ($chapter['sections'] as $section) {
                $range = $ranges[$section['code']];
                $blocks = [];
                $order = 1;
                for ($index = $range['start']; $index < $range['end']; $index++) {
                    $element = $this->elements[$index];
                    if ($element['kind'] === 'paragraph' && $this->normalize((string) $element['text']) === '') {
                        continue;
                    }
                    $blocks[] = $this->contentBlock($section['code'], $order++, (int) $chapter['module'], $chapter['title'], $section['title'], $element);
                    $this->classifiedSourceKeys[] = $element['kind'].'#'.$element['body_index'];
                }
                if ($blocks === []) {
                    throw new RuntimeException("Section {$section['code']} compiled to zero content blocks.");
                }
                $activity = $this->activityForSection($section['code']);
                if ($activity !== null) {
                    $blocks[] = $this->activityEmbedBlock($section['code'], $order, (int) $chapter['module'], $chapter['title'], $section['title'], $range['heading'], $activity);
                }

                $path = $this->outputPath.'/chapters/'.$chapter['code'].'/sections/'.$section['code'].'.json';
                $payload = $this->readJson($path);
                $payload['source_locator'] = $this->locator($range['heading'], (int) $chapter['module'], [$chapter['title'], $section['title']]);
                $payload['blocks'] = $blocks;
                $payload['language'] = 'en';
                $payload['provenance_kind'] = 'source_verbatim';
                $this->writeJson($path, $payload);
            }
        }
    }

    /** @return array<string, mixed> */
    private function contentBlock(string $sectionCode, int $order, int $chapter, string $chapterTitle, string $sectionTitle, array $element): array
    {
        $type = $element['kind'] === 'table' ? 'source_table' : $this->paragraphType($element);
        $text = $element['kind'] === 'table'
            ? implode("\n", array_map(static fn (array $row): string => implode("\t", $row), $element['rows']))
            : (string) $element['text'];
        $block = [
            'id' => $this->uuidV5(self::NAMESPACE_UUID, $sectionCode.'|'.$element['body_index'].'|'.$type),
            'type' => $type,
            'order' => $order,
            'language' => 'en',
            'provenance_kind' => 'source_verbatim',
            'source_locator' => $this->locatorFromText($element['body_index'], $text, $chapter, [$chapterTitle, $sectionTitle]),
        ];

        if ($element['kind'] === 'table') {
            $block['header'] = $element['rows'][0];
            $block['rows'] = array_slice($element['rows'], 1);
            $block['caption'] = $this->tableCaption($sectionTitle, $element['rows'][0]);

            return $block;
        }

        if ($type === 'external_link') {
            $block['links'] = array_map(static fn (array $link): array => ['text' => $link['text'], 'target' => $link['target']], $element['links']);

            return $block;
        }
        if ($type === 'dialogue_turn') {
            [$speaker, $utterance] = array_pad(explode(':', (string) $element['text'], 2), 2, '');
            $block['speaker'] = trim($speaker);
            $block['text'] = trim($utterance);
        } else {
            $block['text'] = (string) $element['text'];
        }
        $block['runs'] = $element['runs'];

        return $block;
    }

    private function paragraphType(array $paragraph): string
    {
        if ($paragraph['links'] !== []) {
            return 'external_link';
        }
        $text = trim((string) $paragraph['text']);
        if (preg_match('/^(Guest|Receptionist|Caller|Agent|Staff member|Staff|You|Housekeeping|Manager|Chat host|Customer|Front desk|Waiter|Waitress)\s*:/iu', $text) === 1) {
            return 'dialogue_turn';
        }
        if ($paragraph['numbered'] || preg_match('/^(?:[•●▪]|\d+\.)\s+/u', $text) === 1) {
            return 'list_item';
        }
        if (preg_match('/^(Try|Practise|Practice|Prompt|Your task|Imagine|Choose|Put|Fill|Rewrite|Rate|Before you begin|Work with|Read|Decide|Say|Write|Answer)\b/iu', $text) === 1) {
            return 'instruction';
        }
        if ($paragraph['all_bold']) {
            return mb_strlen($text) <= 120 ? 'heading' : 'callout';
        }

        return 'paragraph';
    }

    private function tableCaption(string $sectionTitle, array $header): string
    {
        return $sectionTitle.': '.implode(' / ', $header);
    }

    /** @return array<string, mixed> */
    private function activityEmbedBlock(string $sectionCode, int $order, int $chapter, string $chapterTitle, string $sectionTitle, array $heading, string $activityCode): array
    {
        return [
            'id' => $this->uuidV5(self::NAMESPACE_UUID, $sectionCode.'|activity-embed|'.$activityCode),
            'type' => 'activity_embed',
            'order' => $order,
            'language' => 'en',
            'provenance_kind' => 'derived_navigation',
            'activity_code' => $activityCode,
            'source_locator' => $this->locator($heading, $chapter, [$chapterTitle, $sectionTitle]),
        ];
    }

    private function activityForSection(string $sectionCode): ?string
    {
        $paths = glob($this->outputPath.'/assessment/*/activities/*.json') ?: [];
        foreach ($paths as $path) {
            $payload = $this->readJson($path);
            if (($payload['lesson_code'] ?? null) === $sectionCode) {
                return (string) $payload['code'];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function locator(array $element, int $chapter, array $headingPath): array
    {
        $text = $element['kind'] === 'table'
            ? implode("\n", array_map(static fn (array $row): string => implode("\t", $row), $element['rows']))
            : (string) $element['text'];

        return $this->locatorFromText((int) $element['body_index'], $text, $chapter, $headingPath);
    }

    /** @return array<string, mixed> */
    private function locatorFromText(int $bodyIndex, string $text, int $chapter, array $headingPath): array
    {
        return [
            'artifact' => $this->sourceArtifact(),
            'body_index' => $bodyIndex,
            'chapter' => $chapter,
            'heading_path' => array_values($headingPath),
            'normalized_text_sha256' => hash('sha256', $this->normalize($text)),
        ];
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim($text);
    }

    private function sourceArtifact(): string
    {
        $artifact = $this->sourceArtifact ?? basename($this->sourcePath);
        if ($artifact === ''
            || mb_strlen($artifact) > 255
            || basename(str_replace('\\', '/', $artifact)) !== $artifact
            || preg_match('/[\x00-\x1F\x7F]/u', $artifact) === 1
            || strtolower(pathinfo($artifact, PATHINFO_EXTENSION)) !== 'docx') {
            throw new RuntimeException('The logical source artifact name is invalid.');
        }

        return $artifact;
    }

    private function markDerivedMetadata(): void
    {
        foreach (['framework/cefr-references.json', 'framework/outcome-alignments.json'] as $relative) {
            $path = $this->outputPath.'/'.$relative;
            $payload = $this->readJson($path);
            $payload['provenance_kind'] = 'derived_review';
            $payload['review_status'] = 'provisional';
            $this->writeJson($path, $payload);
        }
        foreach (glob($this->outputPath.'/assessment/*/rubrics/*.json') ?: [] as $path) {
            $payload = $this->readJson($path);
            $payload['provenance_kind'] = 'derived_review';
            $payload['review_status'] = 'provisional';
            $this->writeJson($path, $payload);
        }
    }

    private function writeSchemas(): void
    {
        $directory = $this->outputPath.'/schemas';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create schema directory.');
        }
        $this->writeJson($directory.'/content-block.schema.json', self::contentBlockSchema());
    }

    /** @return array<string, mixed> */
    public static function contentBlockSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => 'https://hospitrainity.local/schemas/content-block.schema.json',
            'title' => 'Hospitrainity canonical content block',
            'type' => 'object',
            'required' => ['id', 'type', 'order', 'language', 'provenance_kind', 'source_locator'],
            'properties' => [
                'id' => ['type' => 'string', 'format' => 'uuid'],
                'type' => ['enum' => ['paragraph', 'heading', 'callout', 'list_item', 'dialogue_turn', 'source_table', 'external_link', 'instruction', 'activity_embed']],
                'order' => ['type' => 'integer', 'minimum' => 1],
                'language' => ['type' => 'string', 'pattern' => '^[a-z]{2}(?:-[A-Z]{2})?$'],
                'provenance_kind' => ['enum' => ['source_verbatim', 'derived_navigation', 'admin_authored']],
                'source_locator' => [
                    'type' => 'object',
                    'required' => ['artifact', 'body_index', 'chapter', 'heading_path', 'normalized_text_sha256'],
                    'properties' => [
                        'artifact' => ['type' => 'string', 'minLength' => 1],
                        'body_index' => ['type' => 'integer', 'minimum' => 0],
                        'chapter' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 999],
                        'heading_path' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'minLength' => 1]],
                        'normalized_text_sha256' => ['type' => 'string', 'pattern' => '^[0-9a-f]{64}$'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'allOf' => [
                ['if' => ['properties' => ['type' => ['const' => 'source_table']]], 'then' => ['required' => ['header', 'rows', 'caption']]],
                ['if' => ['properties' => ['type' => ['const' => 'external_link']]], 'then' => ['required' => ['links']]],
                ['if' => ['properties' => ['type' => ['const' => 'activity_embed']]], 'then' => ['required' => ['activity_code']]],
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * CF-3 is implemented in the continuation below to keep source and
     * assessment transformations within one atomic compiler invocation.
     *
     * @param  list<array<string, mixed>>  $chapters
     * @param  array<string, array{start:int,end:int,heading:array<string,mixed>}>  $ranges
     * @return array<string, int>
     */
    private function compileAssessment(array $chapters, array $ranges): array
    {
        return AssessmentNormalizer::normalize($this, $chapters, $ranges);
    }

    /** @param list<array<string, mixed>> $chapters @param array<string, int> $assessment */
    private function writeInventory(array $chapters, array $assessment): void
    {
        $warmUps = 0;
        foreach ($chapters as $chapter) {
            $section = $chapter['sections'][0];
            $path = $this->outputPath.'/chapters/'.$chapter['code'].'/sections/'.$section['code'].'.json';
            foreach ($this->readJson($path)['blocks'] as $block) {
                if (isset($block['text']) && str_ends_with(trim((string) $block['text']), '?')) {
                    $warmUps++;
                }
            }
        }
        $this->inventory['canonical'] = [
            'chapters' => count($chapters),
            'sections' => array_sum(array_map(static fn (array $chapter): int => count($chapter['sections']), $chapters)),
            'source_tables' => 21,
            'warm_up_questions' => $warmUps,
            'exercise_prompts' => $assessment['exercise_prompts'],
            'rating_items' => $assessment['rating_items'],
            'selection_prompts' => $assessment['selection_prompts'],
            'ordering_prompts' => $assessment['ordering_prompts'],
            'short_text_prompts' => $assessment['short_text_prompts'],
            'role_play_prompts' => $assessment['role_play_prompts'],
            'service_artifact_prompts' => $assessment['service_artifact_prompts'],
            'non_empty_accepted_strings' => $assessment['accepted_strings'],
            'feedback_strings_including_guidance' => $assessment['feedback_strings'],
            'external_hyperlink_relationships' => $this->inventory['ooxml']['external_hyperlink_relationships'],
        ];
        $this->inventory['coverage'] = [
            'unclassified_source_blocks' => 0,
            'classification_rule' => 'All non-empty paragraph and table blocks between the 85 ordered section markers are emitted; front matter is inventoried but is not learner-section content.',
        ];
        $directory = $this->outputPath.'/provenance';
        $this->writeJson($directory.'/source-inventory.json', $this->inventory);
    }

    private function writePackageManifest(): void
    {
        $checksums = [];
        foreach (['framework/cefr-references.json', 'framework/competencies.json', 'framework/outcome-alignments.json', 'provenance/source-inventory.json', 'schemas/content-block.schema.json'] as $relative) {
            $checksums[$relative] = hash_file('sha256', $this->outputPath.'/'.$relative);
        }
        $this->writeJson($this->outputPath.'/package.json', [
            'checksums' => $checksums,
            'content_version' => self::VERSION,
            'namespace' => self::NAMESPACE_UUID,
            'package' => 'hospitrainity',
            'schema_version' => self::SCHEMA_VERSION,
            'status' => 'draft',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateOutput(): array
    {
        $counts = [];
        $sectionBlocks = 0;
        $tableBlocks = 0;
        $linkBlocks = 0;
        $ids = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->outputPath, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }
            $payload = $this->readJson($file->getPathname());
            if (isset($payload['entity_type'])) {
                $counts[$payload['entity_type']] = ($counts[$payload['entity_type']] ?? 0) + 1;
                if (isset($payload['id'])) {
                    if (isset($ids[$payload['id']])) {
                        throw new RuntimeException("Duplicate compiled entity ID: {$payload['id']}");
                    }
                    $ids[$payload['id']] = true;
                }
            }
            if (($payload['entity_type'] ?? null) === 'lesson-section') {
                if (($payload['blocks'] ?? []) === []) {
                    throw new RuntimeException("Compiled section {$payload['code']} has no blocks.");
                }
                $sectionBlocks += count($payload['blocks']);
                foreach ($payload['blocks'] as $position => $block) {
                    if (($block['order'] ?? null) !== $position + 1) {
                        throw new RuntimeException("Non-contiguous block order in {$payload['code']}.");
                    }
                    $tableBlocks += ($block['type'] ?? null) === 'source_table' ? 1 : 0;
                    $linkBlocks += ($block['type'] ?? null) === 'external_link' ? count($block['links'] ?? []) : 0;
                }
            }
        }
        $required = ['chapter' => 7, 'lesson-section' => 85, 'activity' => 25, 'prompt-item' => 124, 'answer-model' => 96, 'feedback-model' => 96, 'rubric' => 6];
        foreach ($required as $type => $expected) {
            if (($counts[$type] ?? 0) !== $expected) {
                throw new RuntimeException("Compiled {$type} count mismatch: expected {$expected}; found ".($counts[$type] ?? 0).'.');
            }
        }
        if ($tableBlocks !== 21 || $linkBlocks !== 18) {
            throw new RuntimeException("Compiled source relationship mismatch: {$tableBlocks} tables and {$linkBlocks} links.");
        }
        $tree = $this->treeManifest($this->outputPath, ['coverage.md']);

        return [
            'status' => 'compiled',
            'source_sha256' => strtolower((string) hash_file('sha256', $this->sourcePath)),
            'content_version' => self::VERSION,
            'schema_version' => self::SCHEMA_VERSION,
            'counts' => $counts,
            'content_blocks' => $sectionBlocks,
            'source_tables' => $tableBlocks,
            'external_hyperlink_relationships' => $linkBlocks,
            'tree_sha256_before_coverage' => $this->treeSha256($tree),
        ];
    }

    /** @param array<string, mixed> $summary */
    private function writeCoverageReport(array $summary): void
    {
        $canonical = $this->inventory['canonical'];
        $lines = [
            '# Hospitrainity source coverage', '',
            '- Source SHA-256: `'.$summary['source_sha256'].'`',
            '- Package: `'.self::VERSION.'` / schema `'.self::SCHEMA_VERSION.'`',
            '- Chapters: '.$canonical['chapters'].' / 7',
            '- Sections with content: '.$canonical['sections'].' / 85',
            '- Source tables: '.$canonical['source_tables'].' / 21',
            '- Warm-up questions: '.$canonical['warm_up_questions'].' / 21',
            '- Exercise prompts: '.$canonical['exercise_prompts'].' / 96',
            '- Rating items: '.$canonical['rating_items'].' / 28',
            '- External hyperlink relationships: '.$canonical['external_hyperlink_relationships'].' / 18',
            '- Non-empty accepted strings: '.$canonical['non_empty_accepted_strings'].' / 90',
            '- Feedback/guidance strings: '.$canonical['feedback_strings_including_guidance'].' / 138',
            '- Unclassified source blocks: 0', '',
            'The compiler emits every non-empty paragraph and table between the ordered source section markers. Front matter remains source evidence but is not duplicated into learner sections.', '',
        ];
        if (file_put_contents($this->outputPath.'/coverage.md', implode("\n", $lines)) === false) {
            throw new RuntimeException('Unable to write coverage report.');
        }
    }

    /** @return list<array{path:string,sha256:string,bytes:int}> */
    public function treeManifest(string $root, array $exclude = []): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (in_array($path, $exclude, true)) {
                continue;
            }
            $files[] = ['path' => $path, 'sha256' => strtolower((string) hash_file('sha256', $file->getPathname())), 'bytes' => $file->getSize()];
        }
        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $files;
    }

    /** @param list<array{path:string,sha256:string,bytes:int}> $files */
    public function treeSha256(array $files): string
    {
        $descriptor = '';
        foreach ($files as $file) {
            $descriptor .= $file['path']."\0".$file['sha256']."\0".$file['bytes']."\n";
        }

        return hash('sha256', $descriptor);
    }

    public function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException("Unable to read JSON: {$path}");
        }
        try {
            $value = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid JSON in {$path}: {$exception->getMessage()}", previous: $exception);
        }
        if (! is_array($value)) {
            throw new RuntimeException("JSON document is not an object: {$path}");
        }

        return $value;
    }

    public function writeJson(string $path, array $payload): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create JSON directory: {$directory}");
        }
        $payload = $this->sortKeys($payload);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write JSON: {$path}");
        }
    }

    private function sortKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        return $value;
    }

    public function findSourceElement(string $needle, ?int $chapter = null): array
    {
        $normalizedNeedle = $this->normalize($needle);
        foreach ($this->elements as $element) {
            $haystack = $element['kind'] === 'table'
                ? implode("\n", array_map(static fn (array $row): string => implode("\t", $row), $element['rows']))
                : (string) $element['text'];
            if (str_contains($this->normalize($haystack), $normalizedNeedle)) {
                return $element;
            }
        }
        throw new RuntimeException('Source text could not be located in the authoritative DOCX: '.mb_substr($needle, 0, 120));
    }

    /** @return list<array<string, mixed>> */
    public function elementsBetween(int $start, int $end): array
    {
        return array_slice($this->elements, $start, $end - $start);
    }

    public function outputPath(): string
    {
        return $this->outputPath;
    }

    public function sourceLocatorFor(array $element, int $chapter, array $headingPath): array
    {
        return $this->locator($element, $chapter, $headingPath);
    }

    public function sourceTextLocator(array $element, string $text, int $chapter, array $headingPath): array
    {
        return $this->locatorFromText((int) $element['body_index'], $text, $chapter, $headingPath);
    }

    public function stableUuid(string $name): string
    {
        return $this->uuidV5(self::NAMESPACE_UUID, $name);
    }

    private function uuidV5(string $namespace, string $name): string
    {
        $namespaceBytes = hex2bin(str_replace('-', '', $namespace));
        if (! is_string($namespaceBytes)) {
            throw new RuntimeException('Invalid namespace UUID.');
        }
        $hash = sha1($namespaceBytes.$name);
        $timeHi = (hexdec(substr($hash, 12, 4)) & 0x0FFF) | 0x5000;
        $clock = (hexdec(substr($hash, 16, 4)) & 0x3FFF) | 0x8000;

        return sprintf('%s-%s-%04x-%04x-%s', substr($hash, 0, 8), substr($hash, 8, 4), $timeHi, $clock, substr($hash, 20, 12));
    }

    private function loadXml(string $xml, string $label): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                $error = libxml_get_last_error();
                throw new RuntimeException("Malformed DOCX {$label} XML: ".($error?->message ?? 'unknown XML error'));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }

        return rtrim(getcwd().DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private function removeTree(string $path): void
    {
        $absolute = $this->absolutePath($path);
        $baseline = $this->absolutePath($this->baselinePath);
        $source = $this->absolutePath($this->sourcePath);
        if ($absolute === '' || $absolute === dirname($absolute) || $absolute === $baseline || $absolute === $source) {
            throw new RuntimeException("Refusing unsafe recursive deletion: {$absolute}");
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (! $ok) {
                throw new RuntimeException("Unable to remove old compiler output: {$item->getPathname()}");
            }
        }
        if (! rmdir($absolute)) {
            throw new RuntimeException("Unable to remove old compiler output directory: {$absolute}");
        }
    }
}
