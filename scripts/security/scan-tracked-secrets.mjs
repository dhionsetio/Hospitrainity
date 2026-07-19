import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFile, stat } from 'node:fs/promises';
import { extname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const ALLOWLIST_PATH = 'config/security/secret-scan-allowlist.json';
const MAX_TEXT_FILE_BYTES = 2 * 1024 * 1024;
const TEXT_EXTENSIONS = new Set([
    '', '.blade.php', '.cjs', '.css', '.env', '.example', '.html', '.ini',
    '.js', '.json', '.lock', '.md', '.mjs', '.php', '.ps1', '.scss', '.sql',
    '.svg', '.toml', '.ts', '.txt', '.xml', '.yaml', '.yml',
]);

const secretRules = [
    {
        id: 'private-key-material',
        pattern: /-----BEGIN (?:[A-Z0-9 ]+ )?PRIVATE KEY-----/g,
    },
    {
        id: 'github-token',
        pattern: /\b(?:gh[pousr]_[A-Za-z0-9]{36,255}|github_pat_[A-Za-z0-9_]{50,255})\b/g,
    },
    {
        id: 'aws-access-key-id',
        pattern: /\b(?:AKIA|ASIA)[A-Z0-9]{16}\b/g,
    },
    {
        id: 'google-api-key',
        pattern: /\bAIza[0-9A-Za-z_-]{35}\b/g,
    },
    {
        id: 'slack-token',
        pattern: /\bxox[baprs]-[0-9A-Za-z-]{20,}\b/g,
    },
    {
        id: 'stripe-live-secret',
        pattern: /\b(?:sk|rk)_live_[0-9A-Za-z]{16,}\b/g,
    },
    {
        id: 'credentialed-database-url',
        pattern: /\b(?:mysql|postgres(?:ql)?|mongodb(?:\+srv)?):\/\/[^:\s/]+:[^@\s/]{3,}@/gi,
    },
    {
        id: 'laravel-literal-password-hash',
        pattern: /Hash::make\(\s*(['"])([^'"\r\n]{8,})\1\s*\)/g,
    },
];

const forbiddenArtifactRules = [
    {
        id: 'authority-or-word-document',
        matches: (path) => path.toLowerCase().endsWith('.docx'),
    },
    {
        id: 'database-artifact',
        matches: (path) => /\.(?:db|sqlite|sqlite3)(?:-.+)?$/i.test(path),
    },
    {
        id: 'private-environment-file',
        matches: (path) => {
            const name = path.split('/').at(-1)?.toLowerCase() ?? '';
            return name === '.env'
                || (name.startsWith('.env.') && !name.endsWith('.example'));
        },
    },
    {
        id: 'private-storage-artifact',
        matches: (path) => path.startsWith('storage/app/private/')
            && path !== 'storage/app/private/.gitignore',
    },
    {
        id: 'rendered-audit-artifact',
        matches: (path) => path.startsWith('.codex-audit/'),
    },
];

function normalizePath(path) {
    return path.replaceAll('\\', '/').replace(/^\.\//, '');
}

function fingerprint(rule, path, matchedValue) {
    return createHash('sha256')
        .update(rule)
        .update('\0')
        .update(path)
        .update('\0')
        .update(matchedValue)
        .digest('hex');
}

function lineForOffset(text, offset) {
    let line = 1;
    for (let index = 0; index < offset; index += 1) {
        if (text.charCodeAt(index) === 10) {
            line += 1;
        }
    }

    return line;
}

export function scanText(path, text) {
    const normalizedPath = normalizePath(path);
    const findings = [];

    for (const rule of secretRules) {
        rule.pattern.lastIndex = 0;
        for (const match of text.matchAll(rule.pattern)) {
            findings.push({
                rule: rule.id,
                path: normalizedPath,
                line: lineForOffset(text, match.index ?? 0),
                fingerprint: fingerprint(rule.id, normalizedPath, match[0]),
            });
        }
    }

    return findings;
}

function artifactFindings(paths) {
    const findings = [];

    for (const rawPath of paths) {
        const path = normalizePath(rawPath);
        for (const rule of forbiddenArtifactRules) {
            if (rule.matches(path)) {
                findings.push({
                    rule: rule.id,
                    path,
                    line: null,
                    fingerprint: fingerprint(rule.id, path, path),
                });
            }
        }
    }

    return findings;
}

function trackedPaths() {
    const output = execFileSync('git', ['ls-files', '-z'], {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });

    return output.split('\0').filter(Boolean).map(normalizePath);
}

function isTextCandidate(path) {
    const lower = path.toLowerCase();
    if (lower.endsWith('.blade.php')) {
        return true;
    }

    return TEXT_EXTENSIONS.has(extname(lower));
}

async function loadAllowlist() {
    const parsed = JSON.parse(await readFile(ALLOWLIST_PATH, 'utf8'));
    if (parsed.schema_version !== '1.0.0' || !Array.isArray(parsed.entries)) {
        throw new Error(`${ALLOWLIST_PATH} does not satisfy schema version 1.0.0.`);
    }

    const fingerprints = new Set();
    for (const entry of parsed.entries) {
        if (!entry.fingerprint || !entry.rule || !entry.path || !entry.finding || !entry.review_by_batch || !entry.rationale) {
            throw new Error('Every secret-scan allowlist entry requires fingerprint, rule, path, finding, review_by_batch, and rationale.');
        }
        if (fingerprints.has(entry.fingerprint)) {
            throw new Error(`Duplicate secret-scan allowlist fingerprint: ${entry.fingerprint}`);
        }
        fingerprints.add(entry.fingerprint);
    }

    return parsed.entries;
}

export async function scanRepository() {
    const paths = trackedPaths();
    const findings = artifactFindings(paths);

    for (const path of paths) {
        if (!isTextCandidate(path)) {
            continue;
        }

        const metadata = await stat(path);
        if (metadata.size > MAX_TEXT_FILE_BYTES) {
            continue;
        }

        const content = await readFile(path);
        if (content.subarray(0, 8192).includes(0)) {
            continue;
        }

        findings.push(...scanText(path, content.toString('utf8')));
    }

    const allowlist = await loadAllowlist();
    const allowedFingerprints = new Set(allowlist.map((entry) => entry.fingerprint));
    const observedFingerprints = new Set(findings.map((finding) => finding.fingerprint));
    const unallowlisted = findings.filter((finding) => !allowedFingerprints.has(finding.fingerprint));
    const staleAllowlist = allowlist.filter((entry) => !observedFingerprints.has(entry.fingerprint));

    return {
        trackedFiles: paths.length,
        findings,
        unallowlisted,
        allowlistedCount: findings.length - unallowlisted.length,
        staleAllowlist,
    };
}

async function main() {
    const result = await scanRepository();

    if (result.unallowlisted.length > 0) {
        console.error('Tracked-content secret/artifact scan failed. Findings contain fingerprints only; matched values are never printed.');
        for (const finding of result.unallowlisted) {
            const location = finding.line === null ? finding.path : `${finding.path}:${finding.line}`;
            console.error(`- ${finding.rule} ${location} fingerprint=${finding.fingerprint}`);
        }
    }

    if (result.staleAllowlist.length > 0) {
        console.error('Secret-scan allowlist contains stale entries that must be removed:');
        for (const entry of result.staleAllowlist) {
            console.error(`- ${entry.rule} ${entry.path} fingerprint=${entry.fingerprint}`);
        }
    }

    if (result.unallowlisted.length > 0 || result.staleAllowlist.length > 0) {
        process.exitCode = 1;
        return;
    }

    console.log(`Tracked-content secret/artifact scan passed: ${result.trackedFiles} files, ${result.allowlistedCount} reviewed allowlisted finding(s), 0 unallowlisted findings.`);
}

const isDirectExecution = process.argv[1]
    && pathToFileURL(resolve(process.argv[1])).href === import.meta.url;

if (isDirectExecution) {
    await main();
}
