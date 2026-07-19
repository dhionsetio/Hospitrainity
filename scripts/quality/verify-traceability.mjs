import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';

const REGISTRY_PATH = 'docs/traceability/ng-traceability.json';
const ROADMAP_PATH = 'docs/HOSPITRAINITY_NEXT_GENERATION_AUDIT_AND_UPDATE_ROADMAP.md';
const STATUS_VALUES = new Set(['open', 'in_progress', 'human_review', 'legal_review', 'production_gate', 'closed']);
const EVIDENCE_FIELDS = [
    'code',
    'migrations',
    'automated_tests',
    'browser_device_checks',
    'human_legal_reviews',
    'production_evidence',
    'checkpoints',
];

const EXPECTED_FINDING_IDS = [
    ...Array.from({ length: 5 }, (_, index) => `NG-P0-${String(index + 1).padStart(3, '0')}`),
    ...Array.from({ length: 12 }, (_, index) => `NG-P1-${String(index + 6).padStart(3, '0')}`),
    ...Array.from({ length: 17 }, (_, index) => `NG-P2-${String(index + 18).padStart(3, '0')}`),
    ...Array.from({ length: 9 }, (_, index) => `NG-P3-${String(index + 35).padStart(3, '0')}`),
];

function requireNonEmptyString(value, field, errors) {
    if (typeof value !== 'string' || value.trim() === '') {
        errors.push(`${field} must be a non-empty string.`);
    }
}

function requireNonEmptyStringArray(value, field, errors) {
    if (!Array.isArray(value) || value.length === 0 || value.some((entry) => typeof entry !== 'string' || entry.trim() === '')) {
        errors.push(`${field} must be a non-empty string array.`);
    }
}

export function validateRegistry(registry, roadmapSha256) {
    const errors = [];

    if (registry.schema_version !== '1.0.0') {
        errors.push('schema_version must be 1.0.0.');
    }
    if (registry.roadmap?.path !== ROADMAP_PATH) {
        errors.push(`roadmap.path must be ${ROADMAP_PATH}.`);
    }
    if (registry.roadmap?.sha256 !== roadmapSha256) {
        errors.push('roadmap.sha256 does not match the current roadmap bytes.');
    }
    if (!Array.isArray(registry.findings)) {
        errors.push('findings must be an array.');
        return errors;
    }

    const ids = registry.findings.map((finding) => finding.id);
    if (new Set(ids).size !== ids.length) {
        errors.push('finding ids must be unique.');
    }
    if (JSON.stringify(ids) !== JSON.stringify(EXPECTED_FINDING_IDS)) {
        errors.push('findings must contain all 43 roadmap ids in canonical order.');
    }

    for (const finding of registry.findings) {
        const prefix = finding.id ?? '<missing-id>';
        requireNonEmptyStringArray(finding.batches, `${prefix}.batches`, errors);
        requireNonEmptyString(finding.requirement, `${prefix}.requirement`, errors);
        requireNonEmptyStringArray(finding.decisions, `${prefix}.decisions`, errors);
        requireNonEmptyString(finding.required_evidence, `${prefix}.required_evidence`, errors);
        if (!STATUS_VALUES.has(finding.status)) {
            errors.push(`${prefix}.status is invalid.`);
        }
        if (finding.evidence === null || typeof finding.evidence !== 'object' || Array.isArray(finding.evidence)) {
            errors.push(`${prefix}.evidence must be an object.`);
            continue;
        }
        for (const field of EVIDENCE_FIELDS) {
            if (!Array.isArray(finding.evidence[field]) || finding.evidence[field].some((entry) => typeof entry !== 'string')) {
                errors.push(`${prefix}.evidence.${field} must be a string array.`);
            }
        }
        if (finding.status === 'closed') {
            if (finding.evidence.automated_tests.length === 0 || finding.evidence.checkpoints.length === 0) {
                errors.push(`${prefix} cannot be closed without automated-test and checkpoint evidence.`);
            }
        }
    }

    return errors;
}

async function main() {
    const roadmapBytes = await readFile(ROADMAP_PATH);
    const roadmapSha256 = createHash('sha256').update(roadmapBytes).digest('hex');
    const registry = JSON.parse(await readFile(REGISTRY_PATH, 'utf8'));
    const errors = validateRegistry(registry, roadmapSha256);

    if (errors.length > 0) {
        console.error('Traceability verification failed:');
        for (const error of errors) {
            console.error(`- ${error}`);
        }
        process.exitCode = 1;
        return;
    }

    console.log(`Traceability verification passed: ${registry.findings.length} findings mapped to decisions, required tests, and evidence slots.`);
}

await main();
