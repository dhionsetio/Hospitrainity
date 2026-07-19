import assert from 'node:assert/strict';
import test from 'node:test';
import { scanText } from '../../scripts/security/scan-tracked-secrets.mjs';

test('secret scanner fingerprints private material without returning its value', () => {
    const privateMarker = '-----BEGIN PRIVATE KEY-----';
    const findings = scanText('config/example.txt', privateMarker);

    assert.equal(findings.length, 1);
    assert.equal(findings[0].rule, 'private-key-material');
    assert.equal(findings[0].line, 1);
    assert.match(findings[0].fingerprint, /^[a-f0-9]{64}$/);
    assert.equal(JSON.stringify(findings).includes(privateMarker), false);
});

test('secret scanner detects a literal Laravel password hash at its source line', () => {
    const findings = scanText(
        'database/seeders/ExampleSeeder.php',
        "<?php\nHash::make('predictable-test-value');\n",
    );

    assert.equal(findings.length, 1);
    assert.equal(findings[0].rule, 'laravel-literal-password-hash');
    assert.equal(findings[0].line, 2);
});

test('secret scanner does not flag ordinary placeholder-free source text', () => {
    assert.deepEqual(scanText('README.md', 'Use environment-managed credentials.'), []);
});
