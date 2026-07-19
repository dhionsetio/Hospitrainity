#!/usr/bin/env node
// CP-07 accessibility / UDL / media requirements tool: deterministic, read-only.
// For every activity it (1) derives the REQUIRED accessibility affordances from the
// activity's channel + response_form (grounded in WCAG 2.2 AA + CAST UDL 3.0), maps each to
// specific WCAG success criteria + UDL principles + required media asset affordances, and
// (2) checks the activity's DECLARED accessibility flags against those requirements.
// It writes phase-07/a11y-requirements.json (the requirement matrix) and
// phase-07/a11y-conformance.json (declared-vs-required). It mutates NO content entity.
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dir = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dir, '../../../..');            // E-learning-main
const MIG = path.join(ROOT, 'docs/hospitrainity-migration');
const PKG = path.join(ROOT, 'curriculum/hospitrainity/0.3.0-draft');
const OUT = path.join(MIG, 'phase-07');

const readJson = (p) => JSON.parse(readFileSync(p, 'utf8'));
function sortKeys(v){ if(Array.isArray(v)) return v.map(sortKeys); if(v&&typeof v==='object'){const o={};for(const k of Object.keys(v).sort())o[k]=sortKeys(v[k]);return o;} return v; }
const canonical = (o) => JSON.stringify(sortKeys(o), null, 2) + '\n';
function walk(dir){ const out=[]; for(const e of readdirSync(dir)){ const f=path.join(dir,e); const s=statSync(f); if(s.isDirectory()) out.push(...walk(f)); else if(f.endsWith('.json')) out.push(f); } return out; }

// ---- grounded requirement model (WCAG 2.2 AA + CAST UDL 3.0) ----
// WCAG success criteria satisfied by each accessibility affordance flag.
const WCAG = {
  text_alt:            { sc: ['1.1.1 Non-text Content (A)'], udl: ['Representation'] },
  keyboard_path:       { sc: ['2.1.1 Keyboard (A)', '2.1.2 No Keyboard Trap (A)'], udl: ['Action & Expression'] },
  non_color_cue:       { sc: ['1.4.1 Use of Color (A)'], udl: ['Representation'] },
  no_timing_dependency:{ sc: ['2.2.1 Timing Adjustable (A)'], udl: ['Engagement'] },
  transcript:          { sc: ['1.2.1 Audio-only and Video-only (A)', '1.2.2 Captions (A)', '1.2.3 Audio Description or Media Alternative (A)'], udl: ['Representation'] },
};
// Baseline flags required of every interactive learner activity, regardless of channel.
const BASELINE = ['text_alt', 'keyboard_path', 'non_color_cue', 'no_timing_dependency'];
// A transcript/time-based-media alternative is required only when the activity ships
// pre-recorded audio or video, i.e. a spoken/telephone role-play with a recorded model turn.
function requiresTranscript(a){
  return ['spoken_ftf','telecommunications'].includes(a.channel) && ['role_play','spoken_rehearsal'].includes(a.response_form);
}
// Media asset affordance requirements by media_type (asset schema: image/audio/video/document).
const MEDIA_RULES = {
  image:    ['alt_text required (WCAG 1.1.1)', 'not sole carrier of meaning (WCAG 1.4.1)'],
  audio:    ['text transcript required (WCAG 1.2.1)', 'player keyboard-operable with visible controls (WCAG 2.1.1)', 'no autoplay >3s / user-controlled (WCAG 1.4.2)'],
  video:    ['captions required (WCAG 1.2.2)', 'audio description or full text alternative (WCAG 1.2.3/1.2.5)', 'keyboard-operable controls (WCAG 2.1.1)'],
  document: ['structured/tagged with headings + reading order (WCAG 1.3.1)', 'language identified (WCAG 3.1.1)'],
};

// ---- load activities ----
const activities = walk(path.join(PKG,'assessment'))
  .filter(f => /\/activities\//.test(f))
  .map(readJson)
  .filter(o => o.entity_type === 'activity')
  .sort((a,b) => a.code < b.code ? -1 : 1);

// ---- build requirement matrix + conformance ----
const reqRows = [];
const confRows = [];
let conform = 0, gaps = 0;
for(const a of activities){
  const required = [...BASELINE];
  if(requiresTranscript(a)) required.push('transcript');
  required.sort();
  const sc = [...new Set(required.flatMap(f => WCAG[f].sc))].sort();
  const udl = [...new Set(required.flatMap(f => WCAG[f].udl))].sort();
  // media affordances implied by this activity
  const media = [];
  if(requiresTranscript(a)) media.push('audio (recorded model turn) => ' + MEDIA_RULES.audio.join('; '));
  if(a.channel === 'social_public_reply') media.push('image (screenshots of posts, if used) => ' + MEDIA_RULES.image.join('; '));
  reqRows.push({ code: a.code, channel: a.channel, response_form: a.response_form, required_accessibility: required, wcag_success_criteria: sc, udl_principles: udl, media_asset_requirements: media });

  const declared = [...(a.accessibility||[])].sort();
  const missing = required.filter(f => !declared.includes(f));
  const extra = declared.filter(f => !required.includes(f));
  const status = missing.length === 0 ? 'conform' : 'gap';
  if(status === 'conform') conform++; else gaps++;
  confRows.push({ code: a.code, status, declared, required, missing, extra });
}

const requirements = {
  generated_by: 'CP-07',
  tool: 'hsp-a11y.mjs',
  standards: { wcag: 'WCAG 2.2, Level AA (W3C Recommendation 2023-10-05)', udl: 'CAST UDL Guidelines 3.0 (2024)' },
  target: 'Requirements for the future Hospitrainity renderer/authoring platform (CP-08+). Requirements-only: no content changed.',
  baseline_accessibility: BASELINE,
  transcript_rule: 'transcript / time-based-media alternative required when channel in {spoken_ftf, telecommunications} and response_form in {role_play, spoken_rehearsal}',
  media_rules: MEDIA_RULES,
  activities: reqRows,
};
const summary = { activities: activities.length, conform, gaps };
const conformance = { generated_by: 'CP-07', tool: 'hsp-a11y.mjs', summary, activities: confRows };

if(!existsSync(OUT)) mkdirSync(OUT, { recursive: true });
writeFileSync(path.join(OUT,'a11y-requirements.json'), canonical(requirements), 'utf8');
writeFileSync(path.join(OUT,'a11y-conformance.json'), canonical(conformance), 'utf8');

console.log(`a11y: ${activities.length} activities | declared-vs-required: ${conform} conform, ${gaps} gap(s)`);
for(const r of confRows) if(r.status!=='conform') console.log(`  [gap] ${r.code}: missing ${r.missing.join(', ')}`);
console.log('wrote phase-07/a11y-requirements.json + phase-07/a11y-conformance.json');
process.exit(gaps>0 ? 2 : 0);
