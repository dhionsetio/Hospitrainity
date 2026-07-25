<?php

declare(strict_types=1);

use App\Services\AgentContextRepository;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! extension_loaded('zip')) {
    fwrite(STDERR, 'The PHP zip extension is required to export the portable agent context.'.PHP_EOL);
    exit(1);
}

/** @var AgentContextRepository $contexts */
$contexts = $app->make(AgentContextRepository::class);
$defaultDocument = $contexts->document($contexts->resolveIncludes(null));
$completeDocument = $contexts->document(AgentContextRepository::SECTIONS);
$curriculum = $completeDocument['sections']['curriculum'];

$curriculumVersion = is_array($curriculum)
    ? (string) ($curriculum['package']['content_version'] ?? 'unavailable')
    : 'unavailable';
$curriculumStatus = is_array($curriculum)
    ? (string) ($curriculum['status'] ?? 'unknown')
    : 'unknown';

$files = [
    'README.md' => portableReadme($curriculumVersion, $curriculumStatus),
    'AGENT_INSTRUCTIONS.md' => agentInstructions(),
    'MATERIALS_CHANGE_GUIDE.md' => materialsChangeGuide(),
    'CHANGE_REQUEST_TEMPLATE.md' => changeRequestTemplate(),
    'api/v1/agent-context/index.json' => prettyJson($defaultDocument),
    'api/v1/agent-context/all.json' => prettyJson($completeDocument),
    'api/v1/agent-context/openapi.json' => prettyJson($contexts->openApi()),
];

foreach (AgentContextRepository::SECTIONS as $section) {
    $files["api/v1/agent-context/{$section}.json"] = prettyJson($contexts->document([$section]));
}

ksort($files);
$manifestFiles = [];
foreach ($files as $path => $contents) {
    $manifestFiles[] = [
        'path' => $path,
        'sha256' => hash('sha256', $contents),
        'bytes' => strlen($contents),
    ];
}

$generatedAt = gmdate(DATE_ATOM);
$manifest = [
    'archive_format' => 'hospitrainity-portable-agent-context',
    'archive_format_version' => '1.0.0',
    'context_api_version' => AgentContextRepository::VERSION,
    'generated_at' => $generatedAt,
    'curriculum_snapshot' => [
        'status' => $curriculumStatus,
        'content_version' => $curriculumVersion,
    ],
    'handling' => [
        'classification' => 'private project context',
        'contains_application_source' => false,
        'contains_credentials_or_secrets' => false,
        'contains_personal_or_learner_data' => false,
        'contains_prompt_bodies_or_answer_keys' => false,
        'share_only_with' => 'An agent or collaborator authorized to understand or revise this project.',
    ],
    'files' => $manifestFiles,
];
$files['MANIFEST.json'] = prettyJson($manifest);
ksort($files);

$contextDirectory = dirname(__DIR__, 2).'/context';
foreach ($files as $relativePath => $contents) {
    $targetFile = $contextDirectory.'/'.$relativePath;
    $targetDir = dirname($targetFile);
    if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
        throw new RuntimeException("Unable to create context directory: {$targetDir}");
    }
    file_put_contents($targetFile, $contents);
}

$outputDirectory = storage_path('app/private/agent-context-exports');
if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0700, true) && ! is_dir($outputDirectory)) {
    throw new RuntimeException("Unable to create agent context export directory: {$outputDirectory}");
}

$timestamp = gmdate('Ymd\THis\Z');
$outputPath = $outputDirectory.DIRECTORY_SEPARATOR.
    'Hospitrainity-Agent-Context-v'.AgentContextRepository::VERSION."-{$timestamp}.zip";

$zip = new ZipArchive;
$openResult = $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::EXCL);
if ($openResult !== true) {
    throw new RuntimeException("Unable to create agent context archive (ZipArchive code {$openResult}).");
}

foreach ($files as $path => $contents) {
    if (! $zip->addFromString($path, $contents)) {
        $zip->close();
        throw new RuntimeException("Unable to add {$path} to the agent context archive.");
    }
    $zip->setCompressionName($path, ZipArchive::CM_DEFLATE, 9);
}

if (! $zip->close()) {
    throw new RuntimeException('Unable to finalize the agent context archive.');
}

$verification = new ZipArchive;
if ($verification->open($outputPath) !== true) {
    throw new RuntimeException('The generated agent context archive could not be reopened.');
}

foreach ($files as $path => $expectedContents) {
    $actualContents = $verification->getFromName($path);
    if (! is_string($actualContents)
        || ! hash_equals(hash('sha256', $expectedContents), hash('sha256', $actualContents))) {
        $verification->close();
        throw new RuntimeException("Archive verification failed for {$path}.");
    }
}
$verification->close();

$result = [
    'status' => 'created_and_verified',
    'path' => $outputPath,
    'sha256' => hash_file('sha256', $outputPath),
    'bytes' => filesize($outputPath),
    'file_count' => count($files),
    'context_api_version' => AgentContextRepository::VERSION,
    'curriculum_status' => $curriculumStatus,
    'curriculum_content_version' => $curriculumVersion,
    'generated_at' => $generatedAt,
];

fwrite(STDOUT, prettyJson($result));

function prettyJson(mixed $value): string
{
    return json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ).PHP_EOL;
}

function portableReadme(string $curriculumVersion, string $curriculumStatus): string
{
    $template = <<<'MARKDOWN'
# Hospitrainity Portable Agent Context

This archive is a context-only snapshot for an AI agent or authorized collaborator. It explains what Hospitrainity is, what the website contains, its goals, roles, features, workflows, architecture boundaries, current curriculum outline, and important terminology without providing the application source.

Context API version: `1.0.0`  
Curriculum snapshot: `{{CURRICULUM_VERSION}}` (`{{CURRICULUM_STATUS}}`)

## Start here

1. Read `AGENT_INSTRUCTIONS.md`.
2. Read `api/v1/agent-context/all.json` for the complete context bundle.
3. For a material adjustment, also read `MATERIALS_CHANGE_GUIDE.md` and use `CHANGE_REQUEST_TEMPLATE.md`.
4. Consult individual files under `api/v1/agent-context/` when a smaller context window is preferable.
5. Use `MANIFEST.json` to verify every packaged file.

## Portable API layout

The JSON files mirror the live read-only context API:

- `index.json`: compact default context—product, domain, and capabilities.
- `all.json`: all context sections in one document.
- `product.json`: purpose, audiences, delivery modes, and non-goals.
- `domain.json`: roles, scopes, entities, lifecycles, and invariants.
- `capabilities.json`: actor features and exercise contracts.
- `workflows.json`: registration, enrollment, learning, authoring, release, and privacy flows.
- `architecture.json`: runtime layers, sources of truth, storage, and security boundaries.
- `curriculum.json`: safe navigation-level outline of the currently deliverable package.
- `routes.json`: named web/API surfaces and their access requirements.
- `glossary.json`: canonical project terminology.
- `openapi.json`: machine-readable contract for the equivalent live API.

These are static files, so they require no server or bearer token after extraction.

## What is deliberately absent

This archive contains no PHP, Blade, JavaScript, CSS, SQL, configuration secrets, credentials, user records, institution records, learner attempts, progress, free-form responses, private exports, raw draft payloads, prompt bodies, model answers, answer keys, checksums from curriculum source files, or release evidence.

When an agent must revise a specific lesson, section, prompt, or answer, provide that target material separately. The archive supplies the surrounding product and governance context; it does not silently substitute an incomplete copy for the authoritative material.

## Handling

Treat this as private project context. It is safer than sharing the repository, but the route inventory and product architecture are still non-public project information. Share it only with an agent or collaborator authorized for the requested work.

The context is a snapshot. Regenerate the ZIP after material, feature, role, workflow, route, or curriculum-release changes.
MARKDOWN;

    return str_replace(
        ['{{CURRICULUM_VERSION}}', '{{CURRICULUM_STATUS}}'],
        [$curriculumVersion, $curriculumStatus],
        $template,
    ).PHP_EOL;
}

function agentInstructions(): string
{
    return <<<'MARKDOWN'
# Instructions for the Receiving Agent

You are receiving a bounded context snapshot of Hospitrainity, a hospitality-English learning and curriculum-delivery platform. Use it to understand the product before proposing changes. Do not claim that this archive is the website source or a complete curriculum export.

## Required reasoning behavior

- Treat facts in the JSON files as confirmed only for the recorded snapshot version.
- Clearly distinguish confirmed context, inference, and information missing from the archive.
- Preserve the separation between work context, institution context, and learning context.
- Never propose revealing personal progress to an institution or copying personal progress into an institution scope.
- Do not treat completion, participation, passing, or self-checking as proof of mastery.
- Do not present draft preview content or machine checks as production approval or qualified human review.
- Do not invent routes, roles, schemas, translations, answer keys, media, institutional policies, or compliance claims.
- Ask for the exact target material when a requested revision depends on text not included here.

## How to use the context

- Product direction or feature fit: read `product.json`, `capabilities.json`, and `workflows.json`.
- Authorization or audience questions: read `domain.json` and `routes.json`.
- Material changes: read `curriculum.json`, `MATERIALS_CHANGE_GUIDE.md`, and the separately supplied target content.
- Technical integration: read `architecture.json`, `routes.json`, and `openapi.json`.
- Terminology: read `glossary.json` before choosing labels.

## Expected change proposal

For a material change, identify the stable chapter, section, activity, or prompt code when available. Return:

1. confirmed current context;
2. the requested outcome and intended learner audience;
3. proposed before/after material or a precise patch;
4. learning, scoring, accessibility, translation, and privacy impacts;
5. assumptions and unresolved review needs;
6. a concrete validation checklist.

Do not modify unrelated material merely to make the proposal look comprehensive.
MARKDOWN;
}

function materialsChangeGuide(): string
{
    return <<<'MARKDOWN'
# Materials Change Guide

## Product goal

Hospitrainity helps learners practise workplace English for hospitality while keeping personal learning evidence separate from institution-attributed evidence. Learning content is canonical, versioned, reviewable, and projected into both the Laravel learner experience and an offline standalone artifact.

## Content hierarchy

The canonical hierarchy is:

```text
curriculum package
└── chapter
    ├── outcome references
    └── lesson section
        ├── ordered learning blocks
        └── activity
            ├── prompt items
            ├── answer/feedback models
            └── optional rubric
```

The ZIP includes only navigation-level chapter, section, and activity metadata. Request or attach the exact authoritative target content before rewriting its wording.

## Constraints a revision must preserve

- Keep stable identifiers unless the owner explicitly authorizes a migration.
- Preserve chapter/section/activity hierarchy and intentional ordering.
- Keep source English faithful; add Indonesian interface translation only when it is approved and semantically equivalent.
- Use the registered response and scoring contract. Objective responses are server-validated; open language is model/rubric self-check, not objective auto-grading.
- Keep choices, tokens, and prompts keyboard operable and understandable without color alone.
- Audio-dependent exercises remain unavailable until an approved equivalent-audio and accommodation policy exists.
- Do not introduce timed dependency, forced recording, or retention of raw open writing/speech responses without an explicit reviewed policy change.
- Keep completion language distinct from mastery or proficiency claims.
- Preserve hospitality workplace relevance, polite register, and clear learner instructions.
- Treat CEFR/accessibility/source-rights claims as requiring their recorded human review; do not infer approval from a machine-readable field.
- Change canonical material through a new reviewed draft/version. Never hand-edit generated standalone output or database projections.

## Recommended workflow

1. Locate the target in `curriculum.json` and record its code and parent context.
2. Obtain the exact current target material from the owner or authoritative source.
3. Confirm the intended learner audience, skill, hospitality situation, and desired difficulty.
4. State whether the change affects only wording or also response form, scoring, media, outcomes, timing, or navigation.
5. Draft the smallest coherent change with before/after text.
6. Check answer/feedback alignment and distractor validity when objective assessment is involved.
7. Check accessibility, privacy, localization, and source/rights consequences.
8. Recommend validation in the isolated curriculum draft preview.
9. Require the normal validation, review, approval, and publication gates before learner delivery.

## What to request when context is missing

Ask only for what the change needs, such as:

- exact chapter/section/activity/prompt code;
- current learner-facing text;
- choices, correct-answer model, feedback, or rubric if affected;
- intended learner level and hospitality role/situation;
- approved source or authority constraints;
- required English/Indonesian handling;
- media and accessibility requirements;
- acceptance criteria.

Never fabricate missing current material.
MARKDOWN;
}

function changeRequestTemplate(): string
{
    return <<<'MARKDOWN'
# Hospitrainity Material Change Request

Copy this template into the prompt you send with the ZIP.

## Target

- Chapter/section/activity/prompt code:
- Current title:
- Current authoritative material attached or pasted: yes / no

## Requested change

- What should change:
- Why it should change:
- Intended learner audience:
- Hospitality situation or job role:
- Desired difficulty or language level, if confirmed:

## Scope

- Wording only:
- Response form or scoring affected:
- Choices, answer model, feedback, or rubric affected:
- Audio/image/media affected:
- English/Indonesian handling:
- Must remain backward-compatible with existing attempts: yes / no / unknown

## Constraints and acceptance criteria

- Source or authority that must be preserved:
- Accessibility requirements:
- Privacy/retention requirements:
- Required reviewer or approval:
- What a successful revision must demonstrate:

## Requested agent output

- [ ] Before/after material
- [ ] Rationale
- [ ] Impact analysis
- [ ] Assumptions and missing evidence
- [ ] Validation checklist
- [ ] Implementation patch, only if the relevant source files are separately provided
MARKDOWN;
}
