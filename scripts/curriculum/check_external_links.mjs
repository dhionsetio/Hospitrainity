import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(fileURLToPath(new URL('../../', import.meta.url)));
const packageRoot = path.join(repoRoot, 'curriculum', 'hospitrainity', '0.4.0-draft');
const defaultOutput = path.join(repoRoot, 'curriculum', 'evidence', 'cf-7-link-health.json');
const outputIndex = process.argv.indexOf('--output');
const outputPath = outputIndex >= 0 ? path.resolve(repoRoot, process.argv[outputIndex + 1] ?? '') : defaultOutput;
const timeoutMilliseconds = 20_000;
const userAgent = 'Hospitrainity-Curriculum-Link-Check/1.0 (+local source-fidelity QA)';

async function filesBelow(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const nested = await Promise.all(entries.map(async entry => {
        const target = path.join(directory, entry.name);
        return entry.isDirectory() ? filesBelow(target) : [target];
    }));

    return nested.flat();
}

async function sourceLinks() {
    const occurrences = [];
    for (const file of await filesBelow(packageRoot)) {
        if (!file.endsWith('.json')) continue;
        const document = JSON.parse(await readFile(file, 'utf8'));
        for (const block of document.blocks ?? []) {
            if (block.type !== 'external_link') continue;
            for (const link of block.links ?? []) {
                occurrences.push({
                    section_code: document.code,
                    block_id: block.id,
                    text: link.text,
                    target: link.target,
                    source_body_index: block.source_locator?.body_index ?? null,
                });
            }
        }
    }

    occurrences.sort((left, right) => left.target.localeCompare(right.target) || left.section_code.localeCompare(right.section_code));
    return occurrences;
}

function classification(status) {
    if (status >= 200 && status < 300) return 'reachable';
    if ([401, 403, 405, 406, 429].includes(status)) return 'blocked_or_rate_limited';
    if ([404, 410].includes(status)) return 'broken';
    if (status >= 500) return 'transient_server_error';
    return 'http_error';
}

async function probe(target) {
    let lastError = null;
    for (let attempt = 1; attempt <= 2; attempt += 1) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), timeoutMilliseconds);
        const started = Date.now();
        try {
            const response = await fetch(target, {
                headers: {
                    Accept: 'text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.1',
                    Range: 'bytes=0-4095',
                    'User-Agent': userAgent,
                },
                redirect: 'follow',
                signal: controller.signal,
            });
            await response.body?.cancel();
            const result = {
                target,
                final_url: response.url,
                http_status: response.status,
                classification: classification(response.status),
                content_type: response.headers.get('content-type'),
                elapsed_ms: Date.now() - started,
                attempts: attempt,
            };
            clearTimeout(timeout);
            if (result.classification !== 'transient_server_error' || attempt === 2) return result;
        } catch (error) {
            clearTimeout(timeout);
            lastError = error;
            if (attempt === 2) {
                return {
                    target,
                    final_url: null,
                    http_status: null,
                    classification: error?.name === 'AbortError' ? 'timeout' : 'network_error',
                    error: error instanceof Error ? error.message : String(error),
                    elapsed_ms: Date.now() - started,
                    attempts: attempt,
                };
            }
        }
    }

    throw lastError;
}

const occurrences = await sourceLinks();
const targets = [...new Set(occurrences.map(link => link.target))].sort();
if (occurrences.length !== 18 || targets.length !== 16) {
    throw new Error(`Source-link inventory changed: expected 18 relationships / 16 unique targets; found ${occurrences.length} / ${targets.length}.`);
}

const results = [];
for (let index = 0; index < targets.length; index += 4) {
    results.push(...await Promise.all(targets.slice(index, index + 4).map(probe)));
}

const counts = Object.fromEntries(
    Object.entries(Object.groupBy(results, result => result.classification))
        .map(([key, members]) => [key, members.length]),
);
const report = {
    report_version: '1.0.0',
    status: results.every(result => result.classification === 'reachable') ? 'verified' : 'review_required',
    checked_at: new Date().toISOString(),
    source_package: 'curriculum/hospitrainity/0.4.0-draft',
    policy: 'Non-destructive availability probe only. A non-reachable source link is reported for content-owner review and is never silently replaced or removed.',
    request: { method: 'GET', redirects: 'follow', timeout_ms: timeoutMilliseconds, concurrency: 4, attempts_for_transient_failure: 2, user_agent: userAgent },
    relationship_count: occurrences.length,
    unique_target_count: targets.length,
    classification_counts: counts,
    results,
    occurrences,
};

await mkdir(path.dirname(outputPath), { recursive: true });
await writeFile(outputPath, `${JSON.stringify(report, null, 4)}\n`, { encoding: 'utf8', flag: 'w' });
process.stdout.write(`${JSON.stringify({ status: report.status, output: path.relative(repoRoot, outputPath).replaceAll('\\', '/'), classification_counts: counts }, null, 4)}\n`);
