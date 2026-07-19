<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = $root.'/standalone/Hospitrainity-Standalone.html';
$target = $root.'/resources/standalone/hospitrainity-template.html';
$expected = '471aedc0725981ea4e9b8592e2226626c716eb996f2e28255c8c091d0cb115bb';

if (! is_file($source)) {
    throw new RuntimeException("Approved standalone artifact not found: {$source}");
}

$actual = hash_file('sha256', $source);
if (! hash_equals($expected, $actual)) {
    throw new RuntimeException("Refusing to extract a template from an unapproved artifact: {$actual}");
}

$html = file_get_contents($source);
if ($html === false) {
    throw new RuntimeException("Unable to read approved standalone artifact: {$source}");
}

$pattern = '/(<script id="hsp-data" type="application\/json">).*?(<\/script>\R<script>)/s';
$template = preg_replace($pattern, '$1{{HOSPITRAINITY_DATA}}$2', $html, 1, $replacements);

if ($template === null || $replacements !== 1) {
    throw new RuntimeException('Expected exactly one embedded curriculum data block.');
}

$directory = dirname($target);
if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    throw new RuntimeException("Unable to create template directory: {$directory}");
}

if (file_put_contents($target, $template, LOCK_EX) !== strlen($template)) {
    throw new RuntimeException("Unable to write standalone template: {$target}");
}

fwrite(STDOUT, "Extracted verified phase-14 template to {$target}\n");
