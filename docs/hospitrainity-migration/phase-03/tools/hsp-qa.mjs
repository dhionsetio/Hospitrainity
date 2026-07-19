#!/usr/bin/env node
// hsp-qa — CP-14 integrated QA & pilot-readiness harness.
// Deterministic, offline, no third-party dependency. Composes the whole pipeline
// and asserts every tool agrees end-to-end: registry <-> compiled store <->
// renderer view-model <-> render-report <-> workflow ledgers <-> rendered DOM.
// Delegates to the existing validators (hsp-validate, hsp-a11y, hsp-editorial).
// Emits phase-14/qa-report.json and phase-14/pilot-readiness.json.
// CP-14: reads gate/module state dynamically from phase-14 evidence-backed ledgers;
// checks and criteria adapt to a closed gate / published state instead of assuming
// the gate is always open.
//
// Usage: node --experimental-sqlite hsp-qa.mjs [--standalone <path>] [--no-dom]
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { DatabaseSync } from 'node:sqlite';

const __dir = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dir, '../../../..');
const MIG = path.join(ROOT, 'docs/hospitrainity-migration');
const P3 = path.join(MIG, 'phase-03');
const P9 = path.join(MIG, 'phase-09');
const P11 = path.join(MIG, 'phase-11');
const P12 = path.join(MIG, 'phase-12');
const P13 = path.join(MIG, 'phase-13');
const P14 = path.join(MIG, 'phase-14');
const PKG = path.join(ROOT, 'curriculum/hospitrainity/0.3.0-draft');
const TOOLS = __dir;
if (!existsSync(P14)) mkdirSync(P14, { recursive: true });

const argv = process.argv.slice(2);
const argOf = (f, d) => { const i = argv.indexOf(f); return i >= 0 ? argv[i + 1] : d; };
const STANDALONE = path.resolve(argOf('--standalone', path.join(P14, 'Hospitrainity-Standalone.html')));
const DO_DOM = !argv.includes('--no-dom');
const SQL = path.join(P9, 'curriculum.sql');

const readJson = (p) => JSON.parse(readFileSync(p, 'utf8'));
function sortKeys(v) { if (Array.isArray(v)) return v.map(sortKeys); if (v && typeof v === 'object') { const o = {}; for (const k of Object.keys(v).sort()) o[k] = sortKeys(v[k]); return o; } return v; }
const canonical = (o) => JSON.stringify(sortKeys(o), null, 2) + '\n';
const sha256 = (s) => createHash('sha256').update(s).digest('hex');
const sha256File = (p) => sha256(readFileSync(p));

// ---- check ledger ----
const checks = [];
function record(id, name, ok, detail, evidence) {
  checks.push({ id, name, status: ok ? 'pass' : 'fail', detail, ...(evidence ? { evidence } : {}) });
  const tag = ok ? 'PASS' : 'FAIL';
  console.log(`[${tag}] ${id}: ${detail}`);
}
function eq(a, b) { return JSON.stringify(a) === JSON.stringify(b); }

// ---- load inputs ----
const registry = readJson(path.join(P3, 'id-registry.json'));
const viewModel = readJson(path.join(P14, 'view-model.json'));
const renderReport = readJson(path.join(P14, 'render-report.json'));
const workflowState = readJson(path.join(P14, 'workflow-state.json'));
const approvalLedger = readJson(path.join(P14, 'approval-ledger.json'));
const workflowReport = readJson(path.join(P14, 'workflow-report.json'));

// registry inventory by type + status (authoritative entity list)
const regByType = {}; const regStatus = {};
for (const e of registry.entries) {
  regByType[e.entity_type] = (regByType[e.entity_type] || 0) + 1;
  regStatus[e.status] = (regStatus[e.status] || 0) + 1;
}
const AUTHORED_TYPES = ['chapter', 'lesson-section', 'activity', 'prompt-item', 'answer-model', 'feedback-model', 'rubric'];
const EXCLUDED_TYPES = ['competency-framework', 'competency', 'outcome', 'cefr-reference', 'source-provenance'];
const authoredCount = AUTHORED_TYPES.reduce((n, t) => n + (regByType[t] || 0), 0);
const excludedCount = EXCLUDED_TYPES.reduce((n, t) => n + (regByType[t] || 0), 0);

// ---- load compiled store ----
const db = new DatabaseSync(':memory:');
db.exec(readFileSync(SQL, 'utf8'));
const tableCount = (t) => db.prepare(`SELECT COUNT(*) n FROM ${t}`).get().n;
const allDocs = (t) => db.prepare(`SELECT doc FROM ${t}`).all().map(r => JSON.parse(r.doc));
const STORE_TABLES = { chapter: 'chapter', 'lesson-section': 'lesson_section', activity: 'activity', 'prompt-item': 'prompt_item', 'answer-model': 'answer_model', 'feedback-model': 'feedback_model', rubric: 'rubric', 'source-provenance': 'source_provenance' };

// =====================================================================
// A. Registry internal totals
// =====================================================================
(() => {
  const total = registry.entries.length;
  const gateStatusA = workflowState.human_approval_gate.status;
  const wantAuthoredStatus = gateStatusA === 'closed' ? 'published' : 'review';
  const ok = total === 487 && authoredCount === 428 && excludedCount === 59 && (regStatus[wantAuthoredStatus] || 0) === 428 && (regStatus.draft || 0) === 59;
  record('A_registry_totals', 'Registry inventory totals', ok,
    `total=${total} (want 487), authored=${authoredCount} (428), excluded=${excludedCount} (59), status=${JSON.stringify(regStatus)}, expected authored status=${wantAuthoredStatus}`,
    { regByType, regStatus });
})();

// =====================================================================
// B. Store rows == registry counts (per authored type + source-provenance)
// =====================================================================
(() => {
  const rows = {}; let ok = true; const mism = [];
  for (const [type, tbl] of Object.entries(STORE_TABLES)) {
    const n = tableCount(tbl); rows[tbl] = n;
    const want = regByType[type] || 0;
    if (n !== want) { ok = false; mism.push(`${tbl}=${n} want ${want}`); }
  }
  record('B_store_vs_registry', 'Compiled store rows match registry counts', ok,
    ok ? `all ${Object.keys(STORE_TABLES).length} tables match registry` : `mismatch: ${mism.join(', ')}`, { rows });
})();

// =====================================================================
// C. Store <-> view-model parity (codes + counts)
// =====================================================================
(() => {
  const storeChapters = allDocs('chapter').map(c => c.code).sort();
  const storeActs = allDocs('activity').map(a => a.code).sort();
  const storePrompts = allDocs('prompt_item').map(p => p.code).sort();
  const vmChapters = viewModel.chapters.map(c => c.code).sort();
  const vmActs = []; const vmPrompts = []; let vmSections = 0;
  for (const c of viewModel.chapters) {
    vmSections += (c.sections || []).length;
    for (const a of (c.activities || [])) { vmActs.push(a.code); for (const p of (a.prompts || [])) vmPrompts.push(p.code); }
  }
  vmActs.sort(); vmPrompts.sort();
  const storeSections = tableCount('lesson_section');
  const okChap = eq(storeChapters, vmChapters);
  const okAct = eq(storeActs, vmActs);
  const okPrompt = eq(storePrompts, vmPrompts);
  const okSec = storeSections === vmSections;
  const okOut = (viewModel.outcomes || []).length === (regByType.outcome || 0);
  const ok = okChap && okAct && okPrompt && okSec && okOut;
  record('C_store_vs_viewmodel', 'Store entities == rendered view-model', ok,
    `chapters ${okChap?'=':'≠'}(${vmChapters.length}), sections ${okSec?'=':'≠'}(store ${storeSections}/vm ${vmSections}), activities ${okAct?'=':'≠'}(${vmActs.length}), prompts ${okPrompt?'=':'≠'}(${vmPrompts.length}), outcomes ${okOut?'=':'≠'}(${(viewModel.outcomes||[]).length})`);
})();

// =====================================================================
// D. Render-report count + integrity agreement with the store
// =====================================================================
(() => {
  const c = renderReport.counts, i = renderReport.integrity;
  const prompts = allDocs('prompt_item');
  const answers = allDocs('answer_model');
  const withAccepted = answers.filter(a => Array.isArray(a.accepted) && a.accepted.length > 0).length;
  const acts = allDocs('activity');
  const chapterCodes = new Set(allDocs('chapter').map(x => x.code));
  const orphanActs = acts.filter(a => !chapterCodes.has(String(a.lesson_code || a.chapter_code || '').slice(0, 7))).length;
  const countsOk = c.chapters === 7 && c.sections === 85 && c.activities === 24 && c.prompts === 102 && c.outcomes === 21;
  const integOk = i.orphan_activities === 0 && i.unplaced_prompts === 0 && i.prompts_with_answer_model === 102 && i.prompts_with_feedback === 102 && i.prompts_with_model_answer_text === withAccepted && i.rubrics === 6;
  const bannedOk = (renderReport.banned_terms && renderReport.banned_terms.youkata === 0);
  const ok = countsOk && integOk && bannedOk && orphanActs === 0;
  record('D_render_report', 'Render-report counts + integrity match store', ok,
    `counts ${countsOk?'ok':'BAD'} ${JSON.stringify(c)}; integrity ${integOk?'ok':'BAD'} (store accepted[]>0 = ${withAccepted}, report says ${i.prompts_with_model_answer_text}); orphanActs=${orphanActs}; youkata=${renderReport.banned_terms?.youkata}`);
})();

// =====================================================================
// E. Internal link/route integrity (view-model self-consistency)
// =====================================================================
(() => {
  const chapCodes = new Set(viewModel.chapters.map(c => c.code));
  let ok = true; const bad = [];
  for (const c of viewModel.chapters) {
    for (const a of (c.activities || [])) {
      // every activity's owning chapter is the chapter it is nested under
      if (!chapCodes.has(c.code)) { ok = false; bad.push(a.code); }
      // activity code should carry its module prefix (HSP-C0x)
      const m = /^(HSP-C0\d)/.exec(a.code);
      if (!m || !c.code.startsWith(m[1])) { ok = false; bad.push(`${a.code} not under ${c.code}`); }
    }
  }
  record('E_link_integrity', 'Every activity resolves under its module', ok,
    ok ? 'all activity->chapter references resolve' : `broken: ${bad.slice(0,5).join('; ')}`);
})();

// =====================================================================
// F. Lifecycle / workflow consistency across ledgers
// =====================================================================
(() => {
  const gateStatus = workflowState.human_approval_gate.status;
  const gateClosedEverywhere = gateStatus === viewModel.workflow.gate.status;
  const allPublished = workflowState.modules.every(m => m.state === 'published');
  const wsConsistent = gateStatus === 'closed'
    ? (allPublished && workflowState.modules.every(m => m.approval_gate === 'PUBLISHED'))
    : workflowState.modules.every(m => m.state === 'review' && m.approval_gate === 'BLOCKED');
  const ledgerConsistent = gateStatus === 'closed'
    ? approvalLedger.modules.every(m => m.approval_gate === 'PUBLISHED' && m.required_signoffs.length === 3 && m.required_signoffs.every(s => s.signed === true && s.reviewer))
    : approvalLedger.modules.every(m => m.approval_gate === 'BLOCKED' && m.required_signoffs.length === 3 && m.required_signoffs.every(s => s.signed === false));
  const reportOk = gateStatus === 'closed'
    ? (workflowReport.authored_entity_count === 428 && workflowReport.modules_published === 7 && workflowReport.modules_in_review === 0)
    : (workflowReport.authored_entity_count === 428 && workflowReport.modules_approved === 0 && workflowReport.modules_in_review === 7);
  const wantRegStatus = gateStatus === 'closed' ? 'published' : 'review';
  const regOk = AUTHORED_TYPES.every(t => registry.entries.filter(e => e.entity_type === t).every(e => e.status === wantRegStatus)) &&
                EXCLUDED_TYPES.every(t => registry.entries.filter(e => e.entity_type === t).every(e => e.status === 'draft'));
  const ok = wsConsistent && gateClosedEverywhere && ledgerConsistent && reportOk && regOk;
  record('F_lifecycle_workflow', 'Lifecycle + approval gate consistent everywhere', ok,
    `gate=${gateStatus}, modules state-consistent=${wsConsistent}, ledger consistent=${ledgerConsistent}, report ok=${reportOk}, registry statuses ok=${regOk}`);
})();

// =====================================================================
// G. Determinism: render twice + importer idempotent
// =====================================================================
(() => {
  const NODE = process.execPath;
  const r1 = '/tmp/qa_r1.html', r2 = '/tmp/qa_r2.html';
  try {
    execFileSync(NODE, ['--experimental-sqlite', path.join(TOOLS, 'hsp-render.mjs'), '--out', r1], { stdio: 'pipe' });
    execFileSync(NODE, ['--experimental-sqlite', path.join(TOOLS, 'hsp-render.mjs'), '--out', r2], { stdio: 'pipe' });
    const s1 = sha256File(r1), s2 = sha256File(r2);
    const deliveredSha = sha256File(STANDALONE);
    const ok = s1 === s2 && s1 === deliveredSha;
    record('G_determinism', 'Renderer is deterministic and matches delivered standalone', ok,
      `render#1=${s1.slice(0,12)} render#2=${s2.slice(0,12)} delivered=${deliveredSha.slice(0,12)}`);
  } catch (e) {
    record('G_determinism', 'Renderer is deterministic', false, 'render failed: ' + String(e.message || e).slice(0, 200));
  }
})();

// =====================================================================
// H. Delegated validators (structural 8/8, a11y, editorial)
// =====================================================================
function runTool(label, args, parse) {
  const NODE = process.execPath;
  let out = '', code = 0;
  try { out = execFileSync(NODE, args, { stdio: 'pipe' }).toString(); }
  catch (e) { out = ((e.stdout || '') + (e.stderr || '')).toString(); code = e.status || 1; }
  return parse(out, code);
}
(() => {
  const r = runTool('validate', ['--experimental-sqlite', path.join(TOOLS, 'hsp-validate.mjs'), 'all', '--strict'],
    (out, code) => ({ ok: /8\/8 checks passed/.test(out) && code === 0, line: (out.trim().split('\n').pop() || '') }));
  record('H1_validate', 'Structural validation 8/8', r.ok, r.line);
})();
(() => {
  const r = runTool('a11y', [path.join(TOOLS, 'hsp-a11y.mjs')],
    (out, code) => ({ ok: code === 0 && /0 gap/.test(out), line: (out.split('\n').find(l => /a11y:/.test(l)) || out.trim().split('\n')[0] || '') }));
  record('H2_a11y', 'Accessibility conformance (WCAG 2.2 AA + UDL, source)', r.ok, r.line);
})();
(() => {
  const r = runTool('editorial', [path.join(TOOLS, 'hsp-editorial.mjs')],
    (out, code) => ({ ok: code === 0 && /errors=0/.test(out), line: (out.split('\n').find(l => /editorial scan:/.test(l)) || out.trim().split('\n')[0] || '') }));
  record('H3_editorial', 'Editorial scan (no errors; no banned tokens in content)', r.ok, r.line);
})();

// =====================================================================
// I. Rendered DOM paint proof (headless Chromium)
// =====================================================================
if (DO_DOM) {
  const CH = '/usr/local/bin/chromium';
  const dumpRoute = (route) => {
    const url = 'file://' + STANDALONE + '#' + route;
    return execFileSync(CH, ['--headless', '--no-sandbox', '--disable-gpu', '--virtual-time-budget=4000', '--dump-dom', url], { stdio: 'pipe', maxBuffer: 32 * 1024 * 1024 }).toString();
  };
  const count = (h, re) => (h.match(re) || []).length;
  try {
    const firstAct = viewModel.chapters.find(c => (c.activities || []).length).activities[0].code;
    const home = dumpRoute('/');
    const about = dumpRoute('/about');
    const outcomes = dumpRoute('/outcomes');
    const chapter = dumpRoute('/chapter/HSP-C02');
    const activity = dumpRoute('/activity/' + firstAct);
    // home: 7 chapter cards (+1 template literal in inline script) and 7 In review badges (+1 literal)
    const cards = count(home, /href="#\/chapter\//g);
    const homeYk = /youkata/i.test(home.replace(/id="hsp-data"[\s\S]*?<\/script>/, ''));
    const hasMain = /id="main"/.test(home);
    const hasSkip = /skip/i.test(home) && /#main/.test(home);
    const hasLang = /<html[^>]+lang=/.test(home);
    const navCurrent = /aria-current="page"/.test(home);
    const aboutTable = /class="wf"/.test(about);
    const gateStatusForDom = viewModel.workflow.gate.status;
    const aboutGateStatus = new RegExp(gateStatusForDom, 'i').test(about) && /gate/i.test(about);
    const outCount = count(outcomes, /class="olist"|outcome/gi) > 0;
    const actPrompts = /prompt/i.test(activity);
    const chapReview = gateStatusForDom === 'closed'
      ? (/Published/.test(chapter) && /Approved/.test(chapter))
      : (/In review/.test(chapter) && /Approval: pending/.test(chapter));
    const ok = cards >= 8 && !homeYk && hasMain && hasSkip && hasLang && navCurrent && aboutTable && aboutGateStatus && outCount && actPrompts && chapReview;
    record('I_dom_paint', 'Rendered DOM paints and matches the model', ok,
      `chapter cards(incl literal)=${cards}, main=${hasMain}, skiplink=${hasSkip}, htmlLang=${hasLang}, navCurrent=${navCurrent}, aboutWfTable=${aboutTable}, gateStatus=${gateStatusForDom}(${aboutGateStatus}), outcomesList=${outCount}, activityPrompts=${actPrompts}, chapterBadges=${chapReview}, youkataInContent=${homeYk}`);
  } catch (e) {
    record('I_dom_paint', 'Rendered DOM paints', false, 'chromium failed: ' + String(e.message || e).slice(0, 200));
  }
} else {
  record('I_dom_paint', 'Rendered DOM paints', true, 'skipped (--no-dom)');
}

// =====================================================================
// Summary + pilot readiness
// =====================================================================
const passed = checks.filter(c => c.status === 'pass').length;
const failed = checks.filter(c => c.status === 'fail');
const allPass = failed.length === 0;

const qaReport = {
  checkpoint: 'CP-14',
  generated_from: 'phase-09/curriculum.sql + phase-14 ledgers + rendered standalone',
  standalone_sha256: sha256File(STANDALONE),
  total_checks: checks.length,
  passed,
  failed: failed.length,
  ok: allPass,
  checks: checks.slice().sort((a, b) => a.id.localeCompare(b.id)),
};
writeFileSync(path.join(P14, 'qa-report.json'), canonical(qaReport), 'utf8');

// Pilot readiness: deterministic go/no-go against explicit, sourced criteria.
// Technical QA can be GREEN while the release decision stays HOLD because the
// CP-02 human-approval gate is open (no fabricated sign-offs — Notes 1 & 2).
const gateOpen = workflowState.human_approval_gate.status === 'open';
const allSevenApproved = (workflowReport.modules_approved || 0) + (workflowReport.modules_published || 0) >= 7 || workflowReport.modules_published === 7;
const criteria = [
  { id: 'qa_all_green', requirement: 'All integrated QA checks pass', met: allPass, blocking_for: 'internal_pilot' },
  { id: 'deterministic_build', requirement: 'Standalone is a deterministic projection of the canonical store', met: checks.find(c => c.id === 'G_determinism').status === 'pass', blocking_for: 'internal_pilot' },
  { id: 'structural_valid', requirement: 'Structural validation 8/8', met: checks.find(c => c.id === 'H1_validate').status === 'pass', blocking_for: 'internal_pilot' },
  { id: 'accessibility', requirement: 'WCAG 2.2 AA + UDL conformance, 0 gaps', met: checks.find(c => c.id === 'H2_a11y').status === 'pass', blocking_for: 'internal_pilot' },
  { id: 'no_banned_terms', requirement: 'No legacy hotel identity (Youkata) in learner content', met: checks.find(c => c.id === 'H3_editorial').status === 'pass' && renderReport.banned_terms.youkata === 0, blocking_for: 'internal_pilot' },
  { id: 'modules_approved', requirement: 'All 7 modules approved by qualified human reviewers', met: allSevenApproved, blocking_for: 'public_release' },
  { id: 'cp02_gate_closed', requirement: 'CP-02 human-approval gate closed (recorded sign-off evidence)', met: !gateOpen, blocking_for: 'public_release' },
];
const internalBlockers = criteria.filter(c => c.blocking_for === 'internal_pilot' && !c.met);
const releaseBlockers = criteria.filter(c => c.blocking_for === 'public_release' && !c.met);
const pilot = {
  checkpoint: 'CP-14',
  internal_pilot_ready: internalBlockers.length === 0,
  public_release_ready: releaseBlockers.length === 0 && internalBlockers.length === 0,
  verdict: internalBlockers.length === 0
    ? (releaseBlockers.length === 0 ? 'READY' : 'READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE')
    : 'NOT-READY',
  rationale: internalBlockers.length === 0
    ? (releaseBlockers.length === 0
        ? 'Technical QA is green and the CP-02 human-approval gate is closed on recorded evidence: all seven modules carry recorded sign-offs for the three required review roles and are approved and published. The sign-offs were recorded as one consolidated Project Owner final release verdict rather than three independently-conducted specialist reviews; this is disclosed transparently in the rendered site rather than presented as independent certification. Provisional CEFR bands remain working hypotheses and are not certified CEFR levels.'
        : 'Technical QA is green: the standalone is a deterministic, validated, accessible projection of the DOCX-derived canonical store, suitable for an internal review pilot. Public release is held pending the listed public-release blockers.')
    : 'One or more internal-pilot criteria failed; see blockers.',
  internal_pilot_blockers: internalBlockers.map(c => c.id),
  public_release_blockers: releaseBlockers.map(c => c.id),
  criteria: criteria.slice().sort((a, b) => a.id.localeCompare(b.id)),
};
writeFileSync(path.join(P14, 'pilot-readiness.json'), canonical(pilot), 'utf8');

console.log(`\nQA: ${passed}/${checks.length} checks passed` + (allPass ? '' : ` — ${failed.length} FAILED: ${failed.map(f => f.id).join(', ')}`));
console.log(`Pilot readiness: ${pilot.verdict}`);
console.log(`  internal pilot ready: ${pilot.internal_pilot_ready}; public release ready: ${pilot.public_release_ready}`);
if (releaseBlockers.length) console.log(`  public-release blockers: ${releaseBlockers.map(c => c.id).join(', ')}`);
process.exit(allPass ? 0 : 1);
