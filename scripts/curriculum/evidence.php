<?php

declare(strict_types=1);

use Hospitrainity\Curriculum\SourceCompiler;

require __DIR__.'/SourceCompiler.php';
require __DIR__.'/AssessmentNormalizer.php';

$arguments = [];
for ($index = 1; $index < count($argv); $index += 2) {
    $name = $argv[$index] ?? '';
    $value = $argv[$index + 1] ?? '';
    if (! str_starts_with($name, '--') || $value === '') {
        fwrite(STDERR, "Usage: php evidence.php --package <dir> --baseline-evidence <json> --standalone <html> --output <json>\n");
        exit(1);
    }
    $arguments[substr($name, 2)] = $value;
}

try {
    foreach (['package', 'baseline-evidence', 'standalone', 'output'] as $required) {
        if (! isset($arguments[$required])) {
            throw new RuntimeException("Missing --{$required}");
        }
    }
    $compiler = new SourceCompiler(__FILE__, __DIR__, sys_get_temp_dir(), hash_file('sha256', __FILE__));
    $files = $compiler->treeManifest($arguments['package']);
    $jsonFiles = array_filter($files, static fn (array $file): bool => str_ends_with($file['path'], '.json'));
    $markdownFiles = array_filter($files, static fn (array $file): bool => str_ends_with($file['path'], '.md'));
    $baseline = json_decode((string) file_get_contents($arguments['baseline-evidence']), true, flags: JSON_THROW_ON_ERROR);
    $projection = $baseline['projection_meta'];
    $projection['checkpoint'] = 'CF-7';
    $projection['content_version'] = SourceCompiler::VERSION;
    $projection['status'] = 'development';
    $projection['generated_from'] = 'Hospitrainity.docx via the deterministic scripts/curriculum source compiler; framework metadata migrated from 0.3.0-draft.';
    $projection['disclaimer'] = 'This development projection exposes all seven compiled chapters for local verification; chapter lifecycle status does not constitute release approval of 0.4.0-draft. CF-7 release review remains open. Earlier CP-14 evidence records a Project Owner verdict consolidated across the ESP/CEFR, hospitality-practitioner, and accessibility roles rather than three independently conducted specialist reviews; it must not be represented as independent specialist certification. Provisional CEFR bands remain working hypotheses and must not be presented as certified CEFR levels.';
    $projection['notice'] = 'Source-fidelity development package generated from the authoritative DOCX. CF-7 technical evidence does not authorize publication; final publication remains gated by the recorded human decisions and reviews.';
    $projection['title'] = 'Hospitrainity — Hospitality English';
    $standaloneBytes = filesize($arguments['standalone']);
    $standaloneHash = hash_file('sha256', $arguments['standalone']);
    if (! is_int($standaloneBytes) || ! is_string($standaloneHash)) {
        throw new RuntimeException('Unable to inspect standalone artifact.');
    }
    $qualityReports = [];
    foreach ([
        'brand-report' => 'active_brand',
        'link-report' => 'external_links',
        'wcag-report' => 'wcag_2_2_aa',
        'visual-report' => 'source_visual_comparison',
    ] as $argument => $gate) {
        if (! isset($arguments[$argument])) {
            $qualityReports[$gate] = ['status' => 'pending'];

            continue;
        }
        $reportPath = $arguments[$argument];
        $reportContents = file_get_contents($reportPath);
        if ($reportContents === false) {
            throw new RuntimeException("Unable to read --{$argument}: {$reportPath}");
        }
        $report = json_decode($reportContents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($report) || ! isset($report['status']) || ! is_string($report['status'])) {
            throw new RuntimeException("--{$argument} must be a JSON object with a string status.");
        }
        $qualityReports[$gate] = [
            'status' => $report['status'],
            'artifact' => str_replace('\\', '/', $reportPath),
            'sha256' => hash('sha256', $reportContents),
            'bytes' => strlen($reportContents),
        ];
    }

    $evidence = [
        'evidence_version' => '2.1.0',
        'source_authority' => [
            'artifact' => 'Hospitrainity.docx',
            'sha256' => SourceCompiler::AUTHORITY_SHA256,
        ],
        'package' => [
            'path' => str_replace('\\', '/', $arguments['package']),
            'file_count' => count($files),
            'byte_count' => array_sum(array_column($files, 'bytes')),
            'json_file_count' => count($jsonFiles),
            'markdown_file_count' => count($markdownFiles),
            'tree_sha256' => $compiler->treeSha256($files),
            'tree_digest_definition' => 'SHA-256 over UTF-8 rows sorted by ordinal relative path: path + NUL + lowercase raw-file SHA-256 + NUL + byte length + LF',
        ],
        'standalone' => ['path' => str_replace('\\', '/', $arguments['standalone']), 'byte_count' => $standaloneBytes, 'sha256' => $standaloneHash],
        'expected_counts' => [
            'chapters' => 7,
            'sections' => 85,
            'activities' => 25,
            'prompts' => 124,
            'answer_models' => 96,
            'feedback_models' => 96,
            'rubrics' => 6,
            'outcomes' => 21,
            'competencies' => 7,
            'cefr_references' => 23,
            'source_provenance' => 7,
            'migration_edges' => 8,
        ],
        'coverage_counts' => [
            'content_blocks' => 774,
            'source_tables' => 21,
            'warm_up_questions' => 21,
            'exercise_prompts' => 96,
            'rating_items' => 28,
            'external_hyperlink_relationships' => 18,
            'non_empty_accepted_strings' => 90,
            'feedback_and_guidance_strings' => 138,
        ],
        'projection_meta' => $projection,
        'workflow_gate' => ['id' => 'CF-7', 'status' => 'open'],
        'quality_gates' => $qualityReports,
        'human_gates' => [
            'status' => 'open',
            'required' => [
                'learner_response_retention_or_account_lifetime_approval',
                'qualified_esp_cefr_review',
                'hospitality_practitioner_review',
                'accessibility_review',
                'external_link_replacement_or_removal_decisions',
                'content_owner_release_approval',
            ],
        ],
        'release_decision' => [
            'package_version' => SourceCompiler::VERSION,
            'lifecycle_status' => 'draft',
            'decision' => 'retain_draft_until_every_human_and_technical_gate_passes',
        ],
    ];
    $directory = dirname($arguments['output']);
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Unable to create evidence directory.');
    }
    file_put_contents($arguments['output'], json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n", LOCK_EX);
    fwrite(STDOUT, json_encode($evidence['package'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, '[curriculum-evidence] '.$exception->getMessage()."\n");
    exit(1);
}
