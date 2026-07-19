#!/usr/bin/env node
// CP-06 editorial linter: deterministic, read-only editorial + integrity scan across
// canonical package content + assessment entities. Emits a machine-readable editorial
// register. It NEVER mutates content (DOCX is the sole source of truth, DEC-001); it only
// reports findings and recommended dispositions.
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dir = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dir, '../../../..');            // E-learning-main
const MIG = path.join(ROOT, 'docs/hospitrainity-migration');
const PKG = path.join(ROOT, 'curriculum/hospitrainity/0.3.0-draft');

const readJson = (p) => JSON.parse(readFileSync(p, 'utf8'));
function sortKeys(v){ if(Array.isArray(v)) return v.map(sortKeys); if(v&&typeof v==='object'){const o={};for(const k of Object.keys(v).sort())o[k]=sortKeys(v[k]);return o;} return v; }
const canonical = (o) => JSON.stringify(sortKeys(o), null, 2) + '\n';

function walkJson(dir){ const out=[]; for(const e of readdirSync(dir)){ const f=path.join(dir,e); const s=statSync(f); if(s.isDirectory()) out.push(...walkJson(f)); else if(f.endsWith('.json')) out.push(f); } return out; }
function contentEntityFiles(){
  const files=[];
  for(const f of walkJson(PKG)){
    const rel=path.relative(PKG,f);
    if(rel.startsWith('framework/')) continue;
    if(rel==='package.json') continue;
    if(rel.startsWith('provenance/')) continue;
    files.push(f);
  }
  return files.sort();
}

// ---- policy inputs (grounded in project binding rules / DECISIONS) ----
// Banned legacy identity tokens in canonical learner content (DEC-004 hotel; rebrand DEC).
// The StayReady -> Hospitrainity rebrand was executed learner-facing in CP-13; these tokens
// remain permanent regression guards so a legacy brand name can never resurface.
const BANNED = [
  { token: 'Youkata', why: 'legacy hotel identity is forbidden in canonical content (DEC-004)' },
  { token: 'StayReady', why: 'legacy product brand; rebranded to Hospitrainity in CP-13 (permanent regression guard)' },
  { token: 'EngageEnglish', why: 'legacy product brand; rebranded to Hospitrainity in CP-13 (permanent regression guard)' },
];
// Allowed typographic non-ASCII (curly quotes, dashes, ellipsis, common accents in loanwords).
const ALLOWED_NONASCII = /[\u2018\u2019\u201C\u201D\u2013\u2014\u2026\u00A3\u20AC\u00E9\u00E8\u00E0\u00FC\u00F1\u00E7\u00A0]/;

const findings = [];
let idn = 0;
function add(severity, rule, code, field, message){ findings.push({ id: `ED-${String(++idn).padStart(4,'0')}`, severity, rule, entity_code: code, field, message }); }

// text-bearing fields per entity type
function* texts(o){
  const t = o.entity_type;
  if(['chapter','lesson-section','activity'].includes(t) && o.title) yield ['title', o.title];
  if(t==='prompt-item' && o.stem) yield ['stem', o.stem];
  if(t==='answer-model' && Array.isArray(o.accepted)){ let i=0; for(const a of o.accepted){ if(typeof a==='string') yield [`accepted[${i}]`, a]; i++; } }
  if(t==='feedback-model' && Array.isArray(o.messages)){ let i=0; for(const m of o.messages){ if(m&&typeof m.text==='string') yield [`messages[${i}].text`, m.text]; i++; } }
  if(t==='rubric' && Array.isArray(o.criteria)){ let i=0; for(const c of o.criteria){ if(c.descriptor) yield [`criteria[${i}].descriptor`, c.descriptor]; if(Array.isArray(c.levels)){ let j=0; for(const l of c.levels){ if(typeof l==='string') yield [`criteria[${i}].levels[${j}]`, l]; j++; } } i++; } }
}

// ---- load entities ----
const entities = contentEntityFiles().map(f => ({ f, o: readJson(f) }));
const byCode = new Map(entities.map(e => [e.o.code, e.o]));

// ---- text-level editorial checks ----
for(const { o } of entities){
  for(const [field, val] of texts(o)){
    // banned legacy tokens
    for(const b of BANNED){ if(val.includes(b.token)) add('error','legacy-identity', o.code, field, `contains banned token "${b.token}" — ${b.why}`); }
    // whitespace hygiene
    if(/^\s|\s$/.test(val)) add('warn','whitespace', o.code, field, 'leading/trailing whitespace');
    if(/  /.test(val)) add('warn','whitespace', o.code, field, 'double space');
    if(/\t/.test(val)) add('warn','whitespace', o.code, field, 'tab character');
    // stray non-English letters (outside the allowed typographic/loanword set)
    for(const ch of val){ const cp=ch.codePointAt(0); if(cp>0x7f && !ALLOWED_NONASCII.test(ch)){ add('info','non-english-char', o.code, field, `non-ASCII char U+${cp.toString(16).toUpperCase().padStart(4,'0')} "${ch}"`); break; } }
    // empty text
    if(val.trim()==='') add('error','empty-text', o.code, field, 'empty text field');
  }
}

// ---- structural integrity checks (assessment) ----
function optionLetters(stem){ // returns uppercase letters that appear as inline options like "A)" or "(a)"
  const set=new Set();
  for(const m of stem.matchAll(/(?:^|\s|\()([A-Ca-c])[)\.]/g)) set.add(m[1].toUpperCase());
  return set;
}
for(const { o } of entities){
  if(o.entity_type==='answer-model'){
    const prompt = byCode.get(o.prompt_code);
    if(['objective_exact','objective_normalized'].includes(o.scoring_mode)){
      if(!Array.isArray(o.accepted) || o.accepted.length===0 || o.accepted.some(a=>String(a).trim()==='')) add('error','answer-key', o.code, 'accepted', `${o.scoring_mode} answer-model has empty accepted value`);
      // quiz/guided answer key letter should appear among the prompt's inline options
      if(o.scoring_mode==='objective_exact' && prompt && /^[A-Ca-c]$/.test(String(o.accepted?.[0]||''))){
        const letters = optionLetters(prompt.stem||'');
        const key = String(o.accepted[0]).toUpperCase();
        if(letters.size && !letters.has(key)) add('error','answer-key', o.code, 'accepted', `answer key "${o.accepted[0]}" not among prompt options {${[...letters].sort().join(',')}}`);
      }
    }
  }
  if(o.entity_type==='feedback-model'){
    if(!Array.isArray(o.messages) || o.messages.length===0) add('error','feedback', o.code, 'messages', 'no feedback messages');
    else o.messages.forEach((m,i)=>{ if(!m.text || !m.text.trim()) add('error','feedback', o.code, `messages[${i}].text`, 'empty feedback text'); });
  }
  if(o.entity_type==='rubric'){
    if(!Array.isArray(o.criteria) || o.criteria.length===0) add('error','rubric', o.code, 'criteria', 'no criteria');
    else o.criteria.forEach((c,i)=>{ if(!Array.isArray(c.levels)||c.levels.length<2) add('error','rubric', o.code, `criteria[${i}].levels`, 'fewer than 2 levels'); });
  }
}

// ---- duplicate stem detection within an activity ----
const byActivity = new Map();
for(const { o } of entities){ if(o.entity_type==='prompt-item'){ const k=o.activity_code; if(!byActivity.has(k)) byActivity.set(k,[]); byActivity.get(k).push(o); } }
for(const [act, prompts] of byActivity){
  const seen=new Map();
  for(const p of prompts){ const norm=(p.stem||'').replace(/\s+/g,' ').trim().toLowerCase(); if(seen.has(norm)) add('warn','duplicate-stem', p.code, 'stem', `duplicate stem also in ${seen.get(norm)} (activity ${act})`); else seen.set(norm,p.code); }
}

// ---- summary + write register ----
const summary = { error:0, warn:0, info:0 };
for(const f of findings) summary[f.severity] = (summary[f.severity]||0)+1;
findings.sort((a,b)=> a.entity_code<b.entity_code?-1 : a.entity_code>b.entity_code?1 : a.id<b.id?-1:1);
const register = {
  generated_by: 'CP-06',
  tool: 'hsp-editorial.mjs',
  scope: `${entities.length} canonical content + assessment entities`,
  policy: { banned_tokens: BANNED.map(b=>b.token), notes: 'DOCX is sole source of truth (DEC-001); findings are advisory, no content mutated.' },
  summary,
  findings,
};
const outDir = path.join(MIG, 'phase-06');
if(!existsSync(outDir)) mkdirSync(outDir, { recursive: true });
writeFileSync(path.join(outDir, 'editorial-register.json'), canonical(register), 'utf8');

console.log(`editorial scan: ${entities.length} entities | errors=${summary.error} warn=${summary.warn} info=${summary.info}`);
for(const f of findings.slice(0, 40)) console.log(`  [${f.severity}] ${f.rule} ${f.entity_code}.${f.field}: ${f.message}`);
if(findings.length>40) console.log(`  ... ${findings.length-40} more (see editorial-register.json)`);
process.exit(summary.error>0 ? 2 : 0);
