<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$workflowPaths = [
    '.github/workflows/tests.yml',
    '.github/workflows/authority-release.yml',
];

$workflows = [];
foreach ($workflowPaths as $path) {
    if (! is_file($path)) {
        throw new RuntimeException("Required workflow is absent: {$path}");
    }

    $workflow = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    if (! is_array($workflow) || ! isset($workflow['name'], $workflow['on'], $workflow['permissions'], $workflow['jobs'])) {
        throw new RuntimeException("Workflow is missing a required top-level contract: {$path}");
    }
    if (($workflow['permissions']['contents'] ?? null) !== 'read') {
        throw new RuntimeException("Workflow must default to contents: read: {$path}");
    }

    $workflows[$path] = $workflow;
}

$authorityPath = '.github/workflows/authority-release.yml';
$authority = $workflows[$authorityPath];
$triggers = is_array($authority['on']) ? array_keys($authority['on']) : [$authority['on']];
if ($triggers !== ['workflow_dispatch']) {
    throw new RuntimeException('Authority verification must be manual-only workflow_dispatch.');
}

$authoritySource = file_get_contents($authorityPath);
if (! is_string($authoritySource)) {
    throw new RuntimeException('Unable to inspect authority workflow source.');
}
if (preg_match('/(?:upload-artifact|artifact\s+upload|actions\/cache)/i', $authoritySource) === 1) {
    throw new RuntimeException('Authority verification must not upload or cache protected artifacts.');
}

$authorityJob = $authority['jobs']['verify-authorities'] ?? null;
if (! is_array($authorityJob)) {
    throw new RuntimeException('Authority verification job is absent.');
}
$runnerLabels = $authorityJob['runs-on'] ?? [];
foreach (['self-hosted', 'windows', 'hospitrainity-authority'] as $requiredLabel) {
    if (! is_array($runnerLabels) || ! in_array($requiredLabel, $runnerLabels, true)) {
        throw new RuntimeException("Authority runner is missing required label: {$requiredLabel}");
    }
}

foreach ($workflowPaths as $path) {
    $source = file_get_contents($path);
    preg_match_all('/^\s*uses:\s*([^\s#]+)\s*$/m', (string) $source, $matches);
    foreach ($matches[1] as $actionReference) {
        if (preg_match('/@[a-f0-9]{40}$/', $actionReference) !== 1) {
            throw new RuntimeException("Workflow action is not pinned to a full commit: {$path} {$actionReference}");
        }
    }
}

echo 'WORKFLOW_POLICY_OK files=', count($workflowPaths), ' authority_trigger=workflow_dispatch uploads=false', PHP_EOL;
