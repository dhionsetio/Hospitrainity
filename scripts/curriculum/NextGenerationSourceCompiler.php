<?php

declare(strict_types=1);

namespace Hospitrainity\Curriculum;

use DOMDocument;
use DOMElement;
use DOMXPath;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Compiles the web-native v0.8 learning DOCX into a deterministic candidate
 * bundle. The bundle is intentionally separate from the active v0.4 package.
 * Promotion remains a later, explicit release operation.
 */
final class NextGenerationSourceCompiler
{
    public const AUTHORITY_SHA256 = '5de098dcdcc6405093004f6253a1def2f5dc4d85bc1ccacf1416376f3a77569b';

    public const PREVIOUS_AUTHORITY_SHA256 = '7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4';

    public const VERSION = '0.8.0-draft';

    public const SCHEMA_VERSION = '3.0.0-candidate';

    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const NAMESPACE_UUID = '7eb6742b-1af8-57b2-9db6-6dcfd35951a6';

    /** @var array<string, string> */
    private const CHAPTER_TITLES = [
        'HSP-C01' => 'Welcome and Introduction to Customer Care',
        'HSP-C02' => 'Front Desk and Check-In',
        'HSP-C03' => 'Dealing with Customers on the Phone',
        'HSP-C04' => 'The Guest-Services Hotline',
        'HSP-C05' => 'Customer Care Through Writing',
        'HSP-C06' => 'Online and Social Media Customer Service',
        'HSP-C07' => 'Dealing with Problems and Complaints',
    ];

    /** @var array<string, string> */
    private const CEFR_LABELS = [
        'HSP-C01' => 'A2',
        'HSP-C02' => 'A2 to B1',
        'HSP-C03' => 'A2 to B1',
        'HSP-C04' => 'B1',
        'HSP-C05' => 'B1',
        'HSP-C06' => 'B1',
        'HSP-C07' => 'B1 to B2',
    ];

    /** @var list<array<string, mixed>> */
    private array $elements = [];

    /** @var array<string, array<string, string>> */
    private array $relationships = [];

    /** @var list<array<string, mixed>> */
    private array $activities = [];

    /** @var list<array<string, mixed>> */
    private array $outcomes = [];

    /** @var list<array<string, mixed>> */
    private array $contentNodes = [];

    /** @var list<array<string, mixed>> */
    private array $externalResources = [];

    /** @var array<string, int> */
    private array $definedCodes = [];

    public function __construct(
        private readonly string $sourcePath,
        private readonly string $outputPath,
        private readonly string $expectedSha256 = self::AUTHORITY_SHA256,
        private readonly string $sourceArtifact = 'Hospitrainity.docx',
    ) {}

    /** @return array<string, mixed> */
    public function compile(bool $force = false): array
    {
        $this->assertInputs();
        $this->parseDocument();
        $analysis = $this->analyzeSource();

        $this->prepareOutput($force);
        $this->writeAuthoringGuidance($analysis['front_matter_end']);
        $chapterSummary = $this->writeChapters($analysis['chapters']);
        $this->writeEntityInventories();

        $summary = [
            'status' => 'candidate_compiled',
            'activation_status' => 'not_active',
            'source_sha256' => strtolower((string) hash_file('sha256', $this->sourcePath)),
            'previous_source_sha256' => self::PREVIOUS_AUTHORITY_SHA256,
            'content_version' => self::VERSION,
            'schema_version' => self::SCHEMA_VERSION,
            'counts' => [
                'chapters' => $chapterSummary['chapters'],
                'sections' => $chapterSummary['sections'],
                'learner_visible_sections' => $chapterSummary['learner_visible_sections'],
                'outcomes' => count($this->outcomes),
                'activities_and_response_items' => count($this->activities),
                'content_nodes' => count($this->contentNodes),
                'source_paragraphs' => $analysis['paragraphs'],
                'source_tables' => $analysis['tables'],
                'anchored_external_hyperlinks' => $analysis['external_hyperlinks'],
                'external_relationship_records' => $analysis['external_relationships'],
            ],
            'review_state' => [
                'owner_authorized_unreviewed_research_and_cefr_claims' => true,
                'independent_academic_review' => false,
                'qualified_cefr_review' => false,
                'tester_release_only' => true,
            ],
        ];

        $this->writeJson($this->outputPath.'/candidate-package.json', $summary + [
            'package' => 'hospitrainity',
            'source_artifact' => $this->sourceArtifact,
            'supersedes_content_version' => '0.4.0-draft',
            'promotion_rule' => 'Promote only after active-package transformation, application regression tests, and an explicit release decision.',
        ]);
        $this->writeCoverage($summary);
        $manifest = $this->treeManifest($this->outputPath, ['MANIFEST.json']);
        $this->writeJson($this->outputPath.'/MANIFEST.json', [
            'definition' => 'SHA-256 over UTF-8 rows sorted by ordinal relative path: path + NUL + lowercase raw-file SHA-256 + NUL + byte length + LF',
            'file_count' => count($manifest),
            'files' => $manifest,
            'tree_sha256' => $this->treeSha256($manifest),
        ]);

        return $summary + [
            'file_count_before_manifest' => count($manifest),
            'tree_sha256_before_manifest' => $this->treeSha256($manifest),
        ];
    }

    private function assertInputs(): void
    {
        if (! is_file($this->sourcePath)) {
            throw new RuntimeException('Candidate authority DOCX is unavailable.');
        }
        if (strtolower(pathinfo($this->sourcePath, PATHINFO_EXTENSION)) !== 'docx') {
            throw new RuntimeException('Candidate authority must be a DOCX file.');
        }
        $actual = hash_file('sha256', $this->sourcePath);
        if (! is_string($actual) || ! hash_equals(strtolower($this->expectedSha256), strtolower($actual))) {
            throw new RuntimeException('Candidate authority hash mismatch. Expected '.$this->expectedSha256.'; found '.($actual ?: 'unreadable').'.');
        }
    }

    private function parseDocument(): void
    {
        $zip = new ZipArchive;
        if ($zip->open($this->sourcePath) !== true) {
            throw new RuntimeException('Candidate authority is not a readable DOCX package.');
        }
        try {
            $documentXml = $zip->getFromName('word/document.xml');
            $relationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');
            $mediaEntries = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if (str_starts_with($name, 'word/media/')) {
                    $mediaEntries++;
                }
            }
        } finally {
            $zip->close();
        }
        if (! is_string($documentXml) || ! is_string($relationshipsXml)) {
            throw new RuntimeException('Candidate authority is missing its main document or relationship part.');
        }
        if ($mediaEntries !== 0) {
            throw new RuntimeException('Candidate authority unexpectedly contains embedded media.');
        }

        $relationships = $this->loadXml($relationshipsXml, 'relationships');
        foreach ($relationships->documentElement?->childNodes ?? [] as $relationship) {
            if (! $relationship instanceof DOMElement || $relationship->localName !== 'Relationship') {
                continue;
            }
            $this->relationships[$relationship->getAttribute('Id')] = [
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
            throw new RuntimeException('Candidate authority has no WordprocessingML body.');
        }

        foreach ($body->childNodes as $bodyIndex => $node) {
            if (! $node instanceof DOMElement || $node->localName === 'sectPr') {
                continue;
            }
            if (! in_array($node->localName, ['p', 'tbl'], true)) {
                throw new RuntimeException("Unsupported source body block {$node->localName} at index {$bodyIndex}.");
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
            $runs[] = ['text' => $runText];
            $text .= $runText;
        }

        $styleNode = $xpath->query('./w:pPr/w:pStyle', $paragraph)->item(0);
        $style = $styleNode instanceof DOMElement ? $styleNode->getAttributeNS(self::W, 'val') : '';
        $links = [];
        foreach ($xpath->query('.//w:hyperlink', $paragraph) as $hyperlink) {
            if (! $hyperlink instanceof DOMElement) {
                continue;
            }
            $id = $hyperlink->getAttributeNS(self::R, 'id');
            if ($id === '') {
                continue;
            }
            $relationship = $this->relationships[$id] ?? null;
            if ($relationship === null) {
                throw new RuntimeException("Hyperlink relationship {$id} is missing.");
            }
            if ($relationship['target_mode'] === 'External') {
                if (! filter_var($relationship['target'], FILTER_VALIDATE_URL)) {
                    throw new RuntimeException("External hyperlink {$id} has a malformed target.");
                }
                $links[] = ['text' => trim($hyperlink->textContent), 'target' => $relationship['target']];
            }
        }

        return [
            'kind' => 'paragraph',
            'body_index' => $bodyIndex,
            'style' => $style,
            'text' => $this->normalize($text),
            'runs' => $runs,
            'links' => $links,
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
                    $value = $this->normalize($paragraph->textContent);
                    if ($value !== '') {
                        $parts[] = $value;
                    }
                }
                $cells[] = implode("\n", $parts);
            }
            $rows[] = $cells;
        }
        if ($rows === [] || count($rows[0]) === 0) {
            throw new RuntimeException("Malformed empty source table at body index {$bodyIndex}.");
        }
        $width = count($rows[0]);
        foreach ($rows as $rowIndex => $row) {
            if (count($row) !== $width) {
                throw new RuntimeException('Malformed source table row width at body index '.$bodyIndex.', row '.($rowIndex + 1).'.');
            }
        }

        return ['kind' => 'table', 'body_index' => $bodyIndex, 'rows' => $rows];
    }

    /** @return array{front_matter_end:int,chapters:list<array<string,mixed>>,paragraphs:int,tables:int,external_hyperlinks:int,external_relationships:int} */
    private function analyzeSource(): array
    {
        $paragraphs = array_values(array_filter($this->elements, static fn (array $element): bool => $element['kind'] === 'paragraph'));
        $tables = array_values(array_filter($this->elements, static fn (array $element): bool => $element['kind'] === 'table'));
        $links = [];
        foreach ($paragraphs as $paragraph) {
            array_push($links, ...$paragraph['links']);
        }
        $externalRelationships = array_filter(
            $this->relationships,
            static fn (array $relationship): bool => $relationship['target_mode'] === 'External',
        );
        if (count($paragraphs) !== 1439 || count($tables) !== 5 || count($externalRelationships) !== 18) {
            throw new RuntimeException('Candidate source inventory mismatch. Expected 1,439 paragraphs, 5 tables, and 18 external relationship records; found '.count($paragraphs).', '.count($tables).', and '.count($externalRelationships).'.');
        }
        foreach ($externalRelationships as $relationshipId => $relationship) {
            if (! filter_var($relationship['target'], FILTER_VALIDATE_URL)) {
                throw new RuntimeException("External relationship {$relationshipId} has a malformed target.");
            }
            $this->externalResources[] = [
                'relationship_id' => $relationshipId,
                'target' => $relationship['target'],
                'usage_status' => 'unmapped_relationship_not_anchored_in_document_body',
            ];
        }

        $versionMatches = array_values(array_filter(
            $paragraphs,
            static fn (array $paragraph): bool => $paragraph['text'] === 'Version: v0.8.0-draft',
        ));
        if (count($versionMatches) !== 1) {
            throw new RuntimeException('Candidate source version marker is missing or duplicated.');
        }

        $chapterMarkers = [];
        foreach ($this->elements as $index => $element) {
            if ($element['kind'] !== 'paragraph' || $element['style'] !== 'Heading1') {
                continue;
            }
            if (preg_match('/^Chapter (HSP-C\d{2}): (.+)$/u', (string) $element['text'], $match) !== 1) {
                continue;
            }
            $chapterMarkers[] = ['index' => $index, 'code' => $match[1], 'title' => $match[2], 'element' => $element];
        }
        if (count($chapterMarkers) !== 7) {
            throw new RuntimeException('Candidate must contain exactly seven coded chapter headings.');
        }
        foreach ($chapterMarkers as $position => $marker) {
            $expectedCode = sprintf('HSP-C%02d', $position + 1);
            if ($marker['code'] !== $expectedCode || (self::CHAPTER_TITLES[$expectedCode] ?? null) !== $marker['title']) {
                throw new RuntimeException("Candidate chapter {$expectedCode} is missing, renamed, or out of order.");
            }
        }

        $chapters = [];
        foreach ($chapterMarkers as $position => $marker) {
            $end = $chapterMarkers[$position + 1]['index'] ?? count($this->elements);
            $sections = [];
            for ($index = $marker['index'] + 1; $index < $end; $index++) {
                $element = $this->elements[$index];
                if ($element['kind'] === 'paragraph' && $element['style'] === 'Heading2') {
                    $sections[] = ['index' => $index, 'heading' => $element];
                }
            }
            $expectedSectionCount = $marker['code'] === 'HSP-C01' ? 10 : 13;
            if (count($sections) !== $expectedSectionCount) {
                throw new RuntimeException("Candidate {$marker['code']} must contain {$expectedSectionCount} Heading 2 sections; found ".count($sections).'.');
            }
            foreach ($sections as $sectionIndex => &$section) {
                $section['end'] = $sections[$sectionIndex + 1]['index'] ?? $end;
            }
            unset($section);
            $chapters[] = $marker + ['end' => $end, 'sections' => $sections];
        }

        return [
            'front_matter_end' => $chapterMarkers[0]['index'],
            'chapters' => $chapters,
            'paragraphs' => count($paragraphs),
            'tables' => count($tables),
            'external_hyperlinks' => count($links),
            'external_relationships' => count($externalRelationships),
        ];
    }

    private function writeAuthoringGuidance(int $end): void
    {
        $blocks = [];
        for ($index = 0; $index < $end; $index++) {
            $element = $this->elements[$index];
            if ($element['kind'] === 'paragraph' && $element['text'] === '') {
                continue;
            }
            $blocks[] = $this->sourceBlock($element, 0, ['Authoring guidance'], count($blocks) + 1);
        }
        $this->assertNoEmDash($blocks, 'authoring guidance');
        $this->writeJson($this->outputPath.'/front-matter/authoring-guidance.json', [
            'audience' => 'content_author_and_build_pipeline',
            'blocks' => $blocks,
            'compiled_for_learner_display' => false,
            'content_version' => self::VERSION,
            'owner_acceptance' => 'Implement supplied research and CEFR claims for the tester release without representing them as independently reviewed.',
            'review_status' => 'owner_authorized_unreviewed_source_claims',
        ]);
    }

    /** @param list<array<string,mixed>> $chapters @return array{chapters:int,sections:int,learner_visible_sections:int} */
    private function writeChapters(array $chapters): array
    {
        $sectionCount = 0;
        $visibleCount = 0;
        foreach ($chapters as $chapterPosition => $chapter) {
            $chapterCode = (string) $chapter['code'];
            $chapterTitle = (string) $chapter['title'];
            $chapterNumber = $chapterPosition + 1;
            $preambleEnd = $chapter['sections'][0]['index'];
            $preamble = $this->elementsBetween($chapter['index'] + 1, $preambleEnd);
            $cefrClaim = $this->findTextPrefix($preamble, 'CEFR level: ');
            $expectedCefr = self::CEFR_LABELS[$chapterCode];
            if (! str_starts_with($cefrClaim, 'CEFR level: '.$expectedCefr.' ')) {
                throw new RuntimeException("Candidate {$chapterCode} CEFR claim does not match the supplied chapter header.");
            }

            $chapterOutcomes = [];
            foreach ($this->elementsBetween($chapter['index'] + 1, $chapter['end']) as $element) {
                if ($element['kind'] !== 'paragraph') {
                    continue;
                }
                if (preg_match('/^[\x{2022}]?\s*(HSP-C\d{2}-LO-\d{3}):\s*(.+)$/u', (string) $element['text'], $match) === 1) {
                    $this->defineCode($match[1], (int) $element['body_index']);
                    $chapterOutcomes[] = $match[1];
                    $this->outcomes[] = [
                        'chapter_code' => $chapterCode,
                        'code' => $match[1],
                        'id' => $this->stableUuid($match[1]),
                        'review_status' => 'owner_authorized_unreviewed_source_claim',
                        'source_locator' => $this->locator($element, $chapterNumber, [$chapterTitle, 'Learning outcomes']),
                        'statement' => $match[2],
                    ];
                }
            }
            if (count($chapterOutcomes) !== 4) {
                throw new RuntimeException("Candidate {$chapterCode} must define exactly four learning outcomes.");
            }

            $sectionCodes = [];
            foreach ($chapter['sections'] as $order => $section) {
                $headingText = (string) $section['heading']['text'];
                $sectionCode = $this->sectionCode($chapterCode, $headingText);
                if (isset($sectionCodes[$sectionCode])) {
                    throw new RuntimeException("Duplicate section code {$sectionCode}.");
                }
                $sectionCodes[$sectionCode] = true;
                $visibility = $this->sectionVisibility($headingText);
                $title = preg_replace('/^Lesson HSP-C\d{2}-L\d{2}:\s*/u', '', $headingText) ?? $headingText;
                $sourceElements = $this->elementsBetween($section['index'] + 1, $section['end']);
                [$learnerBlocks, $sourceMetadata] = $this->compileSectionElements(
                    $sourceElements,
                    $chapterCode,
                    $sectionCode,
                    $chapterNumber,
                    $chapterTitle,
                    $title,
                );
                if ($visibility === 'learner' && $learnerBlocks === []) {
                    throw new RuntimeException("Learner-visible section {$sectionCode} has no learner blocks.");
                }
                $this->assertNoEmDash($learnerBlocks, $sectionCode);
                $featuresText = implode("\n", array_map(
                    static fn (array $element): string => $element['kind'] === 'paragraph' ? (string) $element['text'] : '',
                    $sourceElements,
                ));
                $payload = [
                    'blocks' => $visibility === 'learner' ? $learnerBlocks : [],
                    'chapter_code' => $chapterCode,
                    'code' => $sectionCode,
                    'content_version' => self::VERSION,
                    'entity_type' => 'lesson-section',
                    'features' => [
                        'listen_or_tts_model_aid' => str_contains($featuresText, 'listen button') || str_contains($featuresText, 'Text-to-speech'),
                        'saved_response' => str_contains($featuresText, 'saved') || str_contains($featuresText, 'stored'),
                        'spaced_review' => str_contains($featuresText, 'spaced-review') || str_contains($featuresText, 'spaced resurfacing'),
                    ],
                    'id' => $this->stableUuid($sectionCode),
                    'language' => 'en',
                    'order' => $order + 1,
                    'source_heading' => $headingText,
                    'source_locator' => $this->locator($section['heading'], $chapterNumber, [$chapterTitle, $title]),
                    'source_metadata' => $sourceMetadata,
                    'status' => 'draft',
                    'title' => $title,
                    'visibility' => $visibility,
                ];
                $this->writeJson($this->outputPath.'/chapters/'.$chapterCode.'/sections/'.$sectionCode.'.json', $payload);
                $sectionCount++;
                $visibleCount += $visibility === 'learner' ? 1 : 0;
            }

            $expectedLessons = $chapterCode === 'HSP-C01'
                ? array_map(static fn (int $number): string => sprintf('%s-L%02d', $chapterCode, $number), range(1, 6))
                : array_map(static fn (int $number): string => sprintf('%s-L%02d', $chapterCode, $number), range(1, 8));
            foreach ($expectedLessons as $lessonCode) {
                if (! isset($sectionCodes[$lessonCode])) {
                    throw new RuntimeException("Candidate lesson {$lessonCode} is missing.");
                }
                $this->defineCode($lessonCode, (int) $chapter['index']);
            }

            $this->writeJson($this->outputPath.'/chapters/'.$chapterCode.'/chapter.json', [
                'cefr_claim' => [
                    'label' => $expectedCefr,
                    'source_text' => $cefrClaim,
                    'review_status' => 'owner_authorized_unreviewed_source_claim',
                ],
                'code' => $chapterCode,
                'content_version' => self::VERSION,
                'entity_type' => 'chapter',
                'id' => $this->stableUuid($chapterCode),
                'module' => $chapterNumber,
                'outcome_codes' => $chapterOutcomes,
                'section_codes' => array_keys($sectionCodes),
                'source_locator' => $this->locator($chapter['element'], $chapterNumber, [$chapterTitle]),
                'status' => 'draft',
                'title' => $chapterTitle,
            ]);
            $this->defineCode($chapterCode, (int) $chapter['element']['body_index']);
        }

        $expected = [
            'outcomes' => 28,
            'warm_up' => 21,
            'confidence' => 28,
            'micro_practice' => 30,
            'role_play' => 24,
            'quiz' => 48,
            'vocabulary' => 7,
            'phrase_bank' => 6,
        ];
        $actual = ['outcomes' => count($this->outcomes), 'warm_up' => 0, 'confidence' => 0, 'micro_practice' => 0, 'role_play' => 0, 'quiz' => 0, 'vocabulary' => 0, 'phrase_bank' => 0];
        foreach ($this->activities as $activity) {
            $type = (string) $activity['activity_family'];
            if (isset($actual[$type])) {
                $actual[$type]++;
            }
        }
        foreach ($this->contentNodes as $node) {
            $type = (string) $node['node_type'];
            if (isset($actual[$type])) {
                $actual[$type]++;
            }
        }
        foreach ($expected as $name => $count) {
            if (($actual[$name] ?? null) !== $count) {
                throw new RuntimeException("Candidate {$name} count mismatch. Expected {$count}; found ".($actual[$name] ?? 'missing').'.');
            }
        }

        return ['chapters' => count($chapters), 'sections' => $sectionCount, 'learner_visible_sections' => $visibleCount];
    }

    /** @param list<array<string,mixed>> $elements @return array{list<array<string,mixed>>,array<string,mixed>} */
    private function compileSectionElements(array $elements, string $chapterCode, string $sectionCode, int $chapterNumber, string $chapterTitle, string $sectionTitle): array
    {
        $blocks = [];
        $metadata = [];
        $order = 1;
        for ($index = 0; $index < count($elements); $index++) {
            $element = $elements[$index];
            if ($element['kind'] === 'paragraph') {
                $text = (string) $element['text'];
                if ($text === '') {
                    continue;
                }
                if (preg_match('/^Activity (HSP-C\d{2}-(?:MP|RP)-\d{3})$/u', $text, $match) === 1) {
                    [$activity, $consumed] = $this->activityFromSequence(array_slice($elements, $index), $match[1], $chapterCode, $sectionCode, $chapterNumber, $chapterTitle, $sectionTitle);
                    $this->activities[] = $activity;
                    $this->defineCode($match[1], (int) $element['body_index']);
                    $blocks[] = $this->embedBlock($match[1], $order++, $element, $chapterNumber, [$chapterTitle, $sectionTitle]);
                    $index += $consumed - 1;

                    continue;
                }
                if (preg_match('/^(HSP-C\d{2}-QZ-\d{3})\.\s+Stem:\s*(.+)$/u', $text, $match) === 1) {
                    $activity = $this->quizFromParagraph($element, $match[1], $match[2], $chapterCode, $sectionCode, $chapterNumber, $chapterTitle, $sectionTitle);
                    $this->activities[] = $activity;
                    $this->defineCode($match[1], (int) $element['body_index']);
                    $blocks[] = $this->embedBlock($match[1], $order++, $element, $chapterNumber, [$chapterTitle, $sectionTitle]);

                    continue;
                }
                if (preg_match('/^[\x{2022}]?\s*(HSP-C\d{2}-(WU|CC)-\d{3}):\s*(.+)$/u', $text, $match) === 1) {
                    $family = $match[2] === 'WU' ? 'warm_up' : 'confidence';
                    $activity = [
                        'activity_family' => $family,
                        'chapter_code' => $chapterCode,
                        'code' => $match[1],
                        'content_version' => self::VERSION,
                        'id' => $this->stableUuid($match[1]),
                        'lesson_code' => $sectionCode,
                        'prompt' => $match[3],
                        'response_form' => $family === 'warm_up' ? 'free_text' : 'rating_1_to_5',
                        'response_persistence' => $family === 'warm_up' ? 'private_learner_journal_raw_text' : 'confidence_rating_separate_from_progress',
                        'scoring_mode' => 'unscored',
                        'source_locator' => $this->locator($element, $chapterNumber, [$chapterTitle, $sectionTitle]),
                        'status' => 'draft',
                    ];
                    $this->activities[] = $activity;
                    $this->defineCode($match[1], (int) $element['body_index']);
                    $blocks[] = $this->embedBlock($match[1], $order++, $element, $chapterNumber, [$chapterTitle, $sectionTitle]);

                    continue;
                }
                if (preg_match('/^[\x{2022}]?\s*(HSP-C\d{2}-LO-\d{3}):\s*(.+)$/u', $text, $match) === 1) {
                    $copy = $element;
                    $copy['text'] = $match[2];
                    $blocks[] = $this->learnerBlock($copy, $order++, $chapterNumber, [$chapterTitle, $sectionTitle]);

                    continue;
                }
                if (preg_match('/^(?:Vocabulary (?:deck|set)|Phrase bank) (HSP-C\d{2}-(VOC|PHR)-\d{3})\b/u', $text, $match) === 1) {
                    $nodeType = $match[2] === 'VOC' ? 'vocabulary' : 'phrase_bank';
                    $this->contentNodes[] = [
                        'chapter_code' => $chapterCode,
                        'code' => $match[1],
                        'content_version' => self::VERSION,
                        'id' => $this->stableUuid($match[1]),
                        'lesson_code' => $sectionCode,
                        'node_type' => $nodeType,
                        'source_locator' => $this->locator($element, $chapterNumber, [$chapterTitle, $sectionTitle]),
                    ];
                    $this->defineCode($match[1], (int) $element['body_index']);
                    $metadata['content_node_codes'][] = $match[1];

                    continue;
                }
                if ($this->isTechnicalMetadata($text)) {
                    [$key, $value] = $this->splitMetadata($text);
                    $metadata[$key][] = $value;

                    continue;
                }
                if (preg_match('/^Model (dialogue|email|chat|letter(?: of apology)?) HSP-C\d{2}-MOD-\d{3}(\.\s*)?(.*)$/u', $text, $match) === 1) {
                    $copy = $element;
                    $copy['text'] = 'Model '.$match[1].'.'.($match[3] !== '' ? ' '.$match[3] : '');
                    $blocks[] = $this->learnerBlock($copy, $order++, $chapterNumber, [$chapterTitle, $sectionTitle]);

                    continue;
                }
                $blocks[] = $this->learnerBlock($element, $order++, $chapterNumber, [$chapterTitle, $sectionTitle]);

                continue;
            }

            $confidenceCodes = [];
            foreach ($element['rows'] as $row) {
                if (preg_match('/^(HSP-C\d{2}-CC-\d{3}):\s*(.+)$/u', (string) ($row[0] ?? ''), $match) !== 1) {
                    continue;
                }
                $activity = [
                    'activity_family' => 'confidence',
                    'chapter_code' => $chapterCode,
                    'code' => $match[1],
                    'content_version' => self::VERSION,
                    'id' => $this->stableUuid($match[1]),
                    'lesson_code' => $sectionCode,
                    'prompt' => $match[2],
                    'response_form' => 'rating_1_to_5',
                    'response_persistence' => 'confidence_rating_separate_from_progress',
                    'scoring_mode' => 'unscored',
                    'source_locator' => $this->locator($element, $chapterNumber, [$chapterTitle, $sectionTitle]),
                    'status' => 'draft',
                ];
                $this->activities[] = $activity;
                $this->defineCode($match[1], (int) $element['body_index']);
                $confidenceCodes[] = $match[1];
            }
            if ($confidenceCodes !== []) {
                $blocks[] = [
                    'id' => $this->stableUuid($sectionCode.'|confidence-group|'.$element['body_index']),
                    'item_codes' => $confidenceCodes,
                    'order' => $order++,
                    'source_locator' => $this->locator($element, $chapterNumber, [$chapterTitle, $sectionTitle]),
                    'type' => 'response_group',
                ];
            } else {
                $blocks[] = $this->learnerBlock($element, $order++, $chapterNumber, [$chapterTitle, $sectionTitle]);
            }
        }

        return [$blocks, $metadata];
    }

    /** @param list<array<string,mixed>> $elements @return array{array<string,mixed>,int} */
    private function activityFromSequence(array $elements, string $code, string $chapterCode, string $sectionCode, int $chapterNumber, string $chapterTitle, string $sectionTitle): array
    {
        $start = $elements[0];
        $fields = [];
        $consumed = 1;
        for ($index = 1; $index < count($elements); $index++) {
            $element = $elements[$index];
            if ($element['kind'] !== 'paragraph') {
                break;
            }
            $text = (string) $element['text'];
            if (preg_match('/^(?:Activity HSP-C\d{2}-(?:MP|RP)-\d{3}|HSP-C\d{2}-QZ-\d{3}\.)/u', $text) === 1) {
                break;
            }
            if (! $this->isActivityField($text)) {
                break;
            }
            [$key, $value] = $this->splitMetadata($text);
            $fields[$key] = $value;
            $consumed++;
        }
        $interaction = (string) ($fields['interaction'] ?? '');
        $family = str_contains($code, '-MP-') ? 'micro_practice' : 'role_play';
        if ($interaction === '') {
            throw new RuntimeException("Activity {$code} is missing its interaction field.");
        }
        $responseForm = match (true) {
            str_contains($interaction, 'single_select') => 'single_select',
            str_contains($interaction, 'sequence') => 'sequence',
            str_contains($interaction, 'text_input_exact') => 'text_input_exact',
            str_contains($interaction, 'free_text') => 'free_text',
            default => throw new RuntimeException("Activity {$code} has an unsupported interaction: {$interaction}"),
        };
        $prompt = (string) ($fields['stem'] ?? '');
        if ($prompt === '') {
            foreach ($fields as $fieldName => $fieldValue) {
                if (str_starts_with((string) $fieldName, 'stem_')) {
                    $prompt = (string) $fieldValue;
                    break;
                }
            }
        }
        if ($prompt === '') {
            throw new RuntimeException("Activity {$code} is missing its prompt.");
        }
        if ($responseForm === 'single_select' && ! isset($fields['options'], $fields['correct'])) {
            throw new RuntimeException("Selection activity {$code} is missing options or a correct answer.");
        }
        if ($responseForm === 'free_text' && ! isset($fields['expected_answer_model'])) {
            throw new RuntimeException("Free-text activity {$code} is missing its model answer.");
        }

        return [[
            'activity_family' => $family,
            'chapter_code' => $chapterCode,
            'code' => $code,
            'content_version' => self::VERSION,
            'fields' => $fields,
            'id' => $this->stableUuid($code),
            'lesson_code' => $sectionCode,
            'prompt' => $prompt,
            'response_form' => $responseForm,
            'response_persistence' => 'saved_assessment_response',
            'scoring_mode' => $responseForm === 'free_text' ? 'model_self_check' : 'objective',
            'source_locator' => $this->locator($start, $chapterNumber, [$chapterTitle, $sectionTitle]),
            'status' => 'draft',
        ], $consumed];
    }

    /** @return array<string,mixed> */
    private function quizFromParagraph(array $element, string $code, string $remainder, string $chapterCode, string $sectionCode, int $chapterNumber, string $chapterTitle, string $sectionTitle): array
    {
        if (preg_match('/^(.*?)\s+Options:\s+(.*?)\s+Correct:\s*([ABC])\.\s+Feedback if correct:\s*(.*?)\s+Feedback if incorrect:\s*(.*)$/u', $remainder, $match) !== 1) {
            throw new RuntimeException("Quiz item {$code} does not match the required source grammar.");
        }
        $options = $this->parseOptions($match[2]);
        if (count($options) < 2 || ! isset($options[$match[3]])) {
            throw new RuntimeException("Quiz item {$code} has malformed options or a missing correct option.");
        }

        return [
            'activity_family' => 'quiz',
            'chapter_code' => $chapterCode,
            'code' => $code,
            'content_version' => self::VERSION,
            'correct_option' => $match[3],
            'feedback' => ['correct' => $match[4], 'incorrect' => $match[5]],
            'id' => $this->stableUuid($code),
            'lesson_code' => $sectionCode,
            'options' => $options,
            'prompt' => $match[1],
            'response_form' => 'single_select',
            'response_persistence' => 'saved_assessment_response',
            'scoring_mode' => 'objective',
            'source_locator' => $this->locator($element, $chapterNumber, [$chapterTitle, $sectionTitle]),
            'status' => 'draft',
        ];
    }

    /** @return array<string,string> */
    private function parseOptions(string $text): array
    {
        preg_match_all('/(?:^|\s)([ABC])\)\s*/u', $text, $matches, PREG_OFFSET_CAPTURE);
        $options = [];
        for ($index = 0; $index < count($matches[0]); $index++) {
            $label = $matches[1][$index][0];
            $start = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $end = $matches[0][$index + 1][1] ?? strlen($text);
            $value = trim(substr($text, $start, $end - $start));
            if ($value !== '') {
                $options[$label] = $value;
            }
        }

        return $options;
    }

    private function sectionCode(string $chapterCode, string $heading): string
    {
        if (preg_match('/^Lesson (HSP-C\d{2}-L\d{2}):/u', $heading, $match) === 1) {
            if (! str_starts_with($match[1], $chapterCode.'-')) {
                throw new RuntimeException("Lesson {$match[1]} is under the wrong chapter.");
            }

            return $match[1];
        }

        return match ($heading) {
            'What you will be able to do (learning outcomes)' => $chapterCode.'-META-OUTCOMES',
            'Two kinds of need (ESP framing)' => $chapterCode.'-META-NEEDS',
            'Course roadmap (what is inside)' => $chapterCode.'-ROADMAP',
            'Take it further' => $chapterCode.'-RESOURCES',
            'Sources' => $chapterCode.'-SOURCES',
            'Engagement and accessibility pass' => $chapterCode.'-ACCESSIBILITY',
            default => throw new RuntimeException("Unsupported chapter section heading: {$heading}"),
        };
    }

    private function sectionVisibility(string $heading): string
    {
        return match ($heading) {
            'Sources' => 'provenance_only',
            'Engagement and accessibility pass' => 'build_contract_only',
            default => 'learner',
        };
    }

    private function isTechnicalMetadata(string $text): bool
    {
        return preg_match('/^(?:Type|Objective|Estimated time|Outcome|Web behaviour|Stable ID|Interaction|Channel|Participation|CEFR|Accessibility|Source locator|Status|Quiz metadata|Confidence items)\s*:/u', $text) === 1
            || preg_match('/^(?:Activity|Reference table|Confidence items) HSP-C/u', $text) === 1;
    }

    private function isActivityField(string $text): bool
    {
        return preg_match('/^(?:Interaction|Stem(?: \([^)]+\))?|Options|Correct|Correct order|Accepted answer|Expected answer \(model\)|Distractor rationale|Correct criteria|Feedback if correct|Feedback if incorrect|Feedback per option|Rubric)\s*:/u', $text) === 1;
    }

    /** @return array{string,string} */
    private function splitMetadata(string $text): array
    {
        $position = mb_strpos($text, ':');
        if ($position === false) {
            return ['note', $text];
        }
        $key = mb_substr($text, 0, $position);
        $value = trim(mb_substr($text, $position + 1));
        $key = mb_strtolower($key);
        $key = str_replace([' ', '(', ')', '-'], ['_', '', '', '_'], $key);
        $key = preg_replace('/_+/u', '_', $key) ?? $key;

        return [trim($key, '_'), $value];
    }

    /** @return array<string,mixed> */
    private function learnerBlock(array $element, int $order, int $chapter, array $headingPath): array
    {
        $block = $this->sourceBlock($element, $chapter, $headingPath, $order);
        if (($block['type'] ?? null) === 'paragraph' && preg_match('/^[\x{2022}]\s*(.+)$/u', (string) ($block['text'] ?? ''), $match) === 1) {
            $block['text'] = $match[1];
            $block['type'] = 'list_item';
        }
        if (($block['type'] ?? null) === 'paragraph' && preg_match('/^HSP-C\d{2}-[A-Z]+-\d{3}\.\s*(.+)$/u', (string) ($block['text'] ?? ''), $match) === 1) {
            $block['text'] = $match[1];
        }

        return $block;
    }

    /** @return array<string,mixed> */
    private function sourceBlock(array $element, int $chapter, array $headingPath, int $order): array
    {
        $type = $element['kind'] === 'table' ? 'source_table' : match ((string) ($element['style'] ?? '')) {
            'Heading1' => 'heading_1',
            'Heading2' => 'heading_2',
            'Heading3' => 'heading_3',
            default => ($element['links'] ?? []) !== [] ? 'external_link' : (($element['numbered'] ?? false) ? 'list_item' : 'paragraph'),
        };
        $block = [
            'id' => $this->stableUuid('block|'.$element['body_index'].'|'.$type),
            'order' => $order,
            'source_locator' => $this->locator($element, $chapter, $headingPath),
            'type' => $type,
        ];
        if ($element['kind'] === 'table') {
            $block['header'] = $element['rows'][0];
            $block['rows'] = array_slice($element['rows'], 1);
        } else {
            $block['text'] = $element['text'];
            if ($element['links'] !== []) {
                $block['links'] = $element['links'];
            }
        }

        return $block;
    }

    /** @return array<string,mixed> */
    private function embedBlock(string $activityCode, int $order, array $element, int $chapter, array $headingPath): array
    {
        return [
            'activity_code' => $activityCode,
            'id' => $this->stableUuid('embed|'.$activityCode),
            'order' => $order,
            'source_locator' => $this->locator($element, $chapter, $headingPath),
            'type' => 'activity_embed',
        ];
    }

    /** @return array<string,mixed> */
    private function locator(array $element, int $chapter, array $headingPath): array
    {
        $text = $element['kind'] === 'table'
            ? implode("\n", array_map(static fn (array $row): string => implode("\t", $row), $element['rows']))
            : (string) $element['text'];

        return [
            'artifact' => $this->sourceArtifact,
            'body_index' => (int) $element['body_index'],
            'chapter' => $chapter,
            'heading_path' => array_values($headingPath),
            'normalized_text_sha256' => hash('sha256', $this->normalize($text)),
        ];
    }

    private function defineCode(string $code, int $bodyIndex): void
    {
        if (isset($this->definedCodes[$code])) {
            throw new RuntimeException("Stable code {$code} is defined more than once at source body indexes {$this->definedCodes[$code]} and {$bodyIndex}.");
        }
        $this->definedCodes[$code] = $bodyIndex;
    }

    /** @param list<array<string,mixed>> $blocks */
    private function assertNoEmDash(array $blocks, string $label): void
    {
        $encoded = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (str_contains($encoded, "\u{2014}")) {
            throw new RuntimeException("Learner-facing em dash found in {$label}.");
        }
    }

    private function writeEntityInventories(): void
    {
        usort($this->activities, static fn (array $left, array $right): int => strcmp((string) $left['code'], (string) $right['code']));
        usort($this->outcomes, static fn (array $left, array $right): int => strcmp((string) $left['code'], (string) $right['code']));
        usort($this->contentNodes, static fn (array $left, array $right): int => strcmp((string) $left['code'], (string) $right['code']));
        $this->assertNoEmDash($this->activities, 'activities and response items');
        $this->assertNoEmDash($this->outcomes, 'learning outcomes');
        $this->writeJson($this->outputPath.'/entities/activities-and-responses.json', ['entities' => $this->activities]);
        $this->writeJson($this->outputPath.'/entities/content-nodes.json', ['entities' => $this->contentNodes]);
        $this->writeJson($this->outputPath.'/entities/external-resources.json', [
            'entities' => $this->externalResources,
            'mapping_status' => 'unresolved',
            'note' => 'The DOCX retains external relationship records but contains no hyperlink anchors in its document body. The candidate preserves targets without assigning them to learner-facing source labels.',
        ]);
        $this->writeJson($this->outputPath.'/entities/outcomes.json', ['entities' => $this->outcomes]);
    }

    /** @param array<string,mixed> $summary */
    private function writeCoverage(array $summary): void
    {
        $counts = $summary['counts'];
        $lines = [
            '# Hospitrainity v0.8 candidate coverage',
            '',
            '- Source SHA-256: `'.$summary['source_sha256'].'`',
            '- Previous source SHA-256 retained as history: `'.self::PREVIOUS_AUTHORITY_SHA256.'`',
            '- Candidate version: `'.self::VERSION.'`',
            '- Chapters: '.$counts['chapters'].' / 7',
            '- Source sections: '.$counts['sections'].' / 88',
            '- Learner-visible sections: '.$counts['learner_visible_sections'],
            '- Learning outcomes: '.$counts['outcomes'].' / 28',
            '- Activities and response items: '.$counts['activities_and_response_items'].' / 151',
            '- Vocabulary and phrase content nodes: '.$counts['content_nodes'].' / 13',
            '- Source paragraphs: '.$counts['source_paragraphs'].' / 1439',
            '- Source tables: '.$counts['source_tables'].' / 5',
            '- Anchored external hyperlinks: '.$counts['anchored_external_hyperlinks'].' / 0',
            '- Unmapped external relationship records preserved: '.$counts['external_relationship_records'].' / 18',
            '',
            'This is a deterministic, non-active candidate bundle. The active v0.4 package remains unchanged until a separate transformation and release gate passes.',
            'The owner authorized the supplied research and CEFR claims for the tester release. The bundle records that authorization without claiming independent academic or CEFR review.',
            '',
        ];
        if (file_put_contents($this->outputPath.'/coverage.md', implode("\n", $lines)) === false) {
            throw new RuntimeException('Unable to write candidate coverage report.');
        }
    }

    private function findTextPrefix(array $elements, string $prefix): string
    {
        foreach ($elements as $element) {
            if ($element['kind'] === 'paragraph' && str_starts_with((string) $element['text'], $prefix)) {
                return (string) $element['text'];
            }
        }
        throw new RuntimeException("Required source text prefix is missing: {$prefix}");
    }

    /** @return list<array<string,mixed>> */
    private function elementsBetween(int $start, int $end): array
    {
        return array_slice($this->elements, $start, $end - $start);
    }

    private function prepareOutput(bool $force): void
    {
        if (file_exists($this->outputPath)) {
            if (! $force) {
                throw new RuntimeException('Candidate output already exists. Pass --force to replace it.');
            }
            $this->removeTree($this->outputPath);
        }
        if (! mkdir($this->outputPath, 0775, true) && ! is_dir($this->outputPath)) {
            throw new RuntimeException('Unable to create candidate output directory.');
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
        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

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

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim($text);
    }

    private function stableUuid(string $name): string
    {
        $namespaceBytes = hex2bin(str_replace('-', '', self::NAMESPACE_UUID));
        if (! is_string($namespaceBytes)) {
            throw new RuntimeException('Invalid compiler UUID namespace.');
        }
        $hash = sha1($namespaceBytes.$name);
        $timeHi = (hexdec(substr($hash, 12, 4)) & 0x0FFF) | 0x5000;
        $clock = (hexdec(substr($hash, 16, 4)) & 0x3FFF) | 0x8000;

        return sprintf('%s-%s-%04x-%04x-%s', substr($hash, 0, 8), substr($hash, 8, 4), $timeHi, $clock, substr($hash, 20, 12));
    }

    /** @param array<string,mixed> $payload */
    private function writeJson(string $path, array $payload): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create candidate package directory.');
        }
        $json = json_encode($this->sortKeys($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write candidate package JSON.');
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

    private function loadXml(string $xml, string $label): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                $error = libxml_get_last_error();
                throw new RuntimeException("Malformed candidate {$label} XML: ".($error?->message ?? 'unknown XML error'));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function removeTree(string $path): void
    {
        $resolved = realpath($path);
        if ($resolved === false || $resolved === dirname($resolved) || $resolved === realpath($this->sourcePath)) {
            throw new RuntimeException('Refusing unsafe candidate output deletion.');
        }
        $driveRoot = preg_match('/^[A-Za-z]:\\\\?$/', $resolved) === 1;
        if ($driveRoot || count(array_filter(preg_split('/[\\\\\/]+/', $resolved) ?: [])) < 3) {
            throw new RuntimeException('Refusing broad candidate output deletion.');
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (! $ok) {
                throw new RuntimeException('Unable to replace previous candidate output.');
            }
        }
        if (! rmdir($resolved)) {
            throw new RuntimeException('Unable to remove previous candidate output directory.');
        }
    }
}
