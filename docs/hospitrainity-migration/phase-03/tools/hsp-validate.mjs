#!/usr/bin/env node
// hsp-validate — Hospitrainity canonical-package validator (CP-03 contract, CP-04 content-aware).
// Node + Ajv (JSON Schema Draft 2020-12). No PHP/Composer or network required.
// Usage: node hsp-validate.mjs <all|schema|ids|refs|lifecycle|provenance|serialize|vocab|fixtures> [--strict]
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const P3 = path.resolve(HERE, '..');
const MIG = path.resolve(P3, '..');
const ROOT = path.resolve(MIG, '../..');
const PKG = path.join(ROOT, 'curriculum/hospitrainity/0.3.0-draft');

const readJson = (p) => JSON.parse(readFileSync(p, 'utf8'));
function sortKeys(v){ if(Array.isArray(v)) return v.map(sortKeys); if(v&&typeof v==='object'){const o={};for(const k of Object.keys(v).sort())o[k]=sortKeys(v[k]);return o;} return v; }
const canonical = (o) => JSON.stringify(sortKeys(o), null, 2) + '\n';

function uuidToBytes(u){const h=u.replace(/-/g,'');const b=Buffer.alloc(16);for(let i=0;i<16;i++)b[i]=parseInt(h.substr(i*2,2),16);return b;}
function bytesToUuid(b){const h=[...b].map(x=>x.toString(16).padStart(2,'0')).join('');return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`;}
function uuidv5(name,ns){const hash=createHash('sha1').update(Buffer.concat([uuidToBytes(ns),Buffer.from(name,'utf8')])).digest();const b=Buffer.from(hash.subarray(0,16));b[6]=(b[6]&0x0f)|0x50;b[8]=(b[8]&0x3f)|0x80;return bytesToUuid(b);}

// recursive .json walk
function walkJson(dir){ const out=[]; if(!existsSync(dir)) return out; for(const e of readdirSync(dir)){ const p=path.join(dir,e); const st=statSync(p); if(st.isDirectory()) out.push(...walkJson(p)); else if(e.endsWith('.json')) out.push(p); } return out; }

// ---------- load schemas ----------
const ajv = new Ajv2020({ allErrors:true, strict:false });
addFormats(ajv);
const schemaDir = path.join(P3,'schemas');
const byType = {};
for (const f of readdirSync(schemaDir).filter(f=>f.endsWith('.schema.json'))) {
  const s = readJson(path.join(schemaDir,f));
  ajv.addSchema(s, s.$id);
  byType[f.replace('.schema.json','')] = s.$id;
}
const validators = {};
for (const [t,$id] of Object.entries(byType)) validators[t] = ajv.getSchema($id);

const registry = readJson(path.join(P3,'id-registry.json'));
const vocab = readJson(path.join(P3,'controlled-vocabularies.json'));
const digestPath = path.join(PKG,'provenance','docx-digest.json');
const digest = existsSync(digestPath) ? readJson(digestPath) : { entries:{} };
const aDigestPath = path.join(PKG,'provenance','assessment-digest.json');
const aDigest = existsSync(aDigestPath) ? readJson(aDigestPath) : { entries:{} };
const ASSESS_TYPES = ['activity','prompt-item','answer-model','feedback-model','rubric'];

// discover package content entity files (exclude framework CP-02 files, README, package.json manifest, digest/inventory manifests handled separately)
function contentEntityFiles(){
  const files = [];
  for (const f of walkJson(PKG)){
    const rel = path.relative(PKG, f);
    if (rel.startsWith('framework/')) continue;            // verbatim CP-02
    if (rel === 'package.json') continue;                  // release manifest
    if (rel === 'provenance/docx-digest.json') continue;   // manifest, not an entity
    if (rel === 'provenance/assessment-digest.json') continue; // manifest
    if (rel === 'provenance/legacy-inventory.json') continue; // manifest
    files.push(f);
  }
  return files;
}

const results = [];
const record = (check, ok, detail) => results.push({check, ok, detail});

function fileType(file, obj){
  const base = path.basename(file);
  if (base==='competencies.json') return 'competency-framework';
  if (base==='outcome-alignments.json') return 'outcome-alignments';
  if (base==='cefr-references.json') return 'cefr-references';
  if (base==='package.json') return 'curriculum-release';
  return obj && obj.entity_type ? obj.entity_type : null;
}
function validateFile(file){
  const obj = readJson(file);
  const t = fileType(file,obj);
  if (!t || !validators[t]) return { ok:false, errors:[`no schema for ${path.relative(ROOT,file)}`] };
  const ok = validators[t](obj);
  return { ok, errors: ok?[]:validators[t].errors.map(e=>`${path.relative(ROOT,file)}: ${e.instancePath} ${e.message}`) };
}

function checkSchema(){
  const files = [
    path.join(PKG,'framework','competencies.json'),
    path.join(PKG,'framework','outcome-alignments.json'),
    path.join(PKG,'framework','cefr-references.json'),
    path.join(PKG,'package.json'),
    path.join(MIG,'phase-02','competencies.json'),
    path.join(MIG,'phase-02','outcome-alignments.json'),
    path.join(MIG,'phase-02','cefr-references.json'),
    ...contentEntityFiles(),
  ];
  let errs=[]; for(const f of files){ const r=validateFile(f); if(!r.ok) errs.push(...r.errors); }
  record('schema', errs.length===0, errs.length?errs:`${files.length} files valid (framework, package, CP-02 originals unchanged, ${contentEntityFiles().length} content entities)`);
}

function checkIds(){
  const errs=[]; const seenCode=new Set(), seenUuid=new Set();
  if(!validators['id-registry'](registry)) errs.push(...validators['id-registry'].errors.map(e=>`registry ${e.instancePath} ${e.message}`));
  const byCode = new Map();
  for(const e of registry.entries){
    if(seenCode.has(e.code)) errs.push(`duplicate code ${e.code}`); seenCode.add(e.code);
    if(seenUuid.has(e.uuid)) errs.push(`duplicate uuid ${e.uuid}`); seenUuid.add(e.uuid);
    if(uuidv5(e.code, registry.namespace)!==e.uuid) errs.push(`non-deterministic uuid for ${e.code}`);
    byCode.set(e.code, e);
  }
  // every content entity with a code must be registered with matching uuid + entity_type
  for(const f of contentEntityFiles()){
    const o=readJson(f); if(!o.code) continue;
    const r=byCode.get(o.code);
    if(!r){ errs.push(`unregistered code ${o.code}`); continue; }
    if(r.uuid!==o.id) errs.push(`${o.code} id mismatch: file ${o.id} != registry ${r.uuid}`);
    if(r.entity_type!==o.entity_type) errs.push(`${o.code} entity_type mismatch`);
  }
  record('ids', errs.length===0, errs.length?errs:`${registry.entries.length} ids unique + UUIDv5-deterministic; all content entities registered`);
}

function checkRefs(){
  const comp = readJson(path.join(PKG,'framework','competencies.json'));
  const outA = readJson(path.join(PKG,'framework','outcome-alignments.json'));
  const cefr = readJson(path.join(PKG,'framework','cefr-references.json'));
  const compIds=new Set(comp.competencies.map(c=>c.id));
  const cefrIds=new Set(cefr.references.map(r=>r.id));
  const outIds=new Set(outA.outcomes.map(o=>o.id));
  const errs=[];
  for(const o of outA.outcomes){
    for(const c of o.competency_ids) if(!compIds.has(c)) errs.push(`${o.id} -> missing competency ${c}`);
    for(const r of o.cefr_reference_ids) if(!cefrIds.has(r)) errs.push(`${o.id} -> missing cefr ${r}`);
  }
  // content entity references
  const chapterCodes=new Set(); const allCodes=new Set();
  for(const f of contentEntityFiles()){ const o=readJson(f); if(o.code) allCodes.add(o.code); if(o.entity_type==='chapter') chapterCodes.add(o.code); }
  for(const f of contentEntityFiles()){
    const o=readJson(f);
    if(o.entity_type==='chapter') for(const oc of (o.outcome_codes||[])) if(!outIds.has(oc)) errs.push(`${o.code} -> missing outcome ${oc}`);
    if(o.entity_type==='lesson-section' && !chapterCodes.has(o.chapter_code)) errs.push(`${o.code} -> missing chapter ${o.chapter_code}`);
    if(o.entity_type==='source-provenance' && !allCodes.has(o.target_code)) errs.push(`${o.code} -> missing target ${o.target_code}`);
    if(o.entity_type==='migration-edge' && o.canonical_code && !chapterCodes.has(o.canonical_code)) errs.push(`migration-edge -> missing chapter ${o.canonical_code}`);
  }
  // assessment references
  const sectionCodes=new Set(); const activityCodes=new Set(); const promptCodes=new Set();
  for(const f of contentEntityFiles()){ const o=readJson(f); if(o.entity_type==='lesson-section') sectionCodes.add(o.code); if(o.entity_type==='activity') activityCodes.add(o.code); if(o.entity_type==='prompt-item') promptCodes.add(o.code); }
  for(const f of contentEntityFiles()){
    const o=readJson(f);
    if(o.entity_type==='activity'){
      if(!sectionCodes.has(o.lesson_code)) errs.push(`${o.code} -> missing lesson-section ${o.lesson_code}`);
      for(const oc of o.outcome_codes) if(!outIds.has(oc)) errs.push(`${o.code} -> missing outcome ${oc}`);
    }
    if(o.entity_type==='prompt-item' && !activityCodes.has(o.activity_code)) errs.push(`${o.code} -> missing activity ${o.activity_code}`);
    if(o.entity_type==='answer-model' && !promptCodes.has(o.prompt_code)) errs.push(`${o.code} -> missing prompt ${o.prompt_code}`);
    if(o.entity_type==='feedback-model' && !promptCodes.has(o.prompt_code)) errs.push(`${o.code} -> missing prompt ${o.prompt_code}`);
    if(o.entity_type==='rubric' && !activityCodes.has(o.activity_code)) errs.push(`${o.code} -> missing activity ${o.activity_code}`);
  }
  record('refs', errs.length===0, errs.length?errs:`framework + content + assessment references resolve`);
}

function checkVocab(){
  const errs=[]; const act=readJson(path.join(schemaDir,'activity.schema.json'));
  const map={ channel:'channel', timing:'timing', participation:'participation', scoring_mode:'scoring_mode', cefr_activity:'cefr_activity', pedagogical_function:'pedagogical_function', response_form:'response_form' };
  for(const [p,vk] of Object.entries(map)){
    if(JSON.stringify(act.properties[p].enum)!==JSON.stringify(vocab.vocabularies[vk])) errs.push(`activity.${p} enum drifts from vocabulary.${vk}`);
  }
  record('vocab', errs.length===0, errs.length?errs:`schema enums consistent with controlled-vocabularies.json`);
}

function checkSerialize(){
  const errs=[]; const targets=[];
  for(const f of readdirSync(schemaDir)) targets.push(path.join(schemaDir,f));
  targets.push(path.join(P3,'id-registry.json'), path.join(P3,'controlled-vocabularies.json'), path.join(P3,'fixtures/fixtures-manifest.json'), path.join(PKG,'package.json'), path.join(PKG,'provenance/docx-digest.json'), path.join(PKG,'provenance/assessment-digest.json'), path.join(PKG,'provenance/legacy-inventory.json'));
  for(const dir of ['fixtures/valid','fixtures/invalid']) for(const f of readdirSync(path.join(P3,dir))) targets.push(path.join(P3,dir,f));
  targets.push(...contentEntityFiles());
  const seen=new Set();
  for(const f of targets){ if(seen.has(f)) continue; seen.add(f); const raw=readFileSync(f,'utf8'); if(raw!==canonical(JSON.parse(raw))) errs.push(`non-canonical: ${path.relative(ROOT,f)}`); }
  record('serialize', errs.length===0, errs.length?errs:`${seen.size} authored JSON files byte-identical to canonical form`);
}

function checkProvenance(){
  const errs=[];
  for(const f of contentEntityFiles()){
    const o=readJson(f);
    if(['chapter','lesson-section','source-provenance'].includes(o.entity_type)){
      if(!o.source_locator){ errs.push(`${o.code} missing source_locator`); continue; }
      const want = digest.entries[o.code];
      // chapter/lesson-section: source_locator hash must match digest; provenance: excerpt + locator match target digest
      if(o.entity_type==='source-provenance'){
        const t = digest.entries[o.target_code];
        if(t && o.excerpt_sha256!==t) errs.push(`${o.code} excerpt_sha256 != digest[${o.target_code}]`);
        if(t && o.source_locator.normalized_text_sha256!==t) errs.push(`${o.code} locator hash != digest[${o.target_code}]`);
      } else {
        if(want && o.source_locator.normalized_text_sha256!==want) errs.push(`${o.code} locator hash != docx-digest`);
        if(!want) errs.push(`${o.code} not present in docx-digest`);
      }
    }
    if(ASSESS_TYPES.includes(o.entity_type)){
      if(!o.source_locator){ errs.push(`${o.code} missing source_locator`); continue; }
      const want = aDigest.entries[o.code];
      if(!want) errs.push(`${o.code} not present in assessment-digest`);
      else if(o.source_locator.normalized_text_sha256!==want) errs.push(`${o.code} locator hash != assessment-digest`);
    }
  }
  record('provenance', errs.length===0, errs.length?errs:`all content + assessment entities carry source_locators matching their digest`);
}

function checkLifecycle(){
  const legal={ draft:['review','retired'], review:['approved','draft','retired'], approved:['published','review','retired'], published:['retired'], retired:[] };
  const errs=[];
  for(const e of registry.entries) if(!Object.keys(legal).includes(e.status)) errs.push(`${e.code} illegal status ${e.status}`);
  for(const f of contentEntityFiles()){ const o=readJson(f); if(o.status && !Object.keys(legal).includes(o.status)) errs.push(`${o.code||path.basename(f)} illegal status ${o.status}`); }
  record('lifecycle', errs.length===0, errs.length?errs:`all registry + entity statuses legal`);
}

function runFixtures(){
  const man=readJson(path.join(P3,'fixtures/fixtures-manifest.json'));
  const baseline=new Map(man.baseline_published.map(b=>[b.code,b]));
  const outIds=new Set(readJson(path.join(PKG,'framework','outcome-alignments.json')).outcomes.map(o=>o.id));
  const errs=[];
  for(const c of man.cases){
    const obj=readJson(path.join(P3,'fixtures',c.file)); const t=obj.entity_type;
    const schemaOk=validators[t]?validators[t](obj):false;
    let provOk=!(obj.entity_type && !obj.source_locator);
    let refsOk=true; if(Array.isArray(obj.outcome_codes)) for(const oc of obj.outcome_codes) if(!outIds.has(oc)) refsOk=false;
    let idsOk=true; if(obj.code&&obj.id){ if(uuidv5(obj.code,registry.namespace)!==obj.id) idsOk=false; const b=baseline.get(obj.code); if(b&&b.status==='published'&&b.id!==obj.id) idsOk=false; }
    const checks={ schema:schemaOk, provenance:provOk, refs:refsOk, ids:idsOk };
    const passAll=Object.values(checks).every(Boolean);
    if(c.expect==='pass'&&!passAll) errs.push(`${c.file} expected PASS but failed ${Object.entries(checks).filter(([,v])=>!v).map(([k])=>k)}`);
    if(c.expect==='fail'&&checks[c.check]!==false) errs.push(`${c.file} expected FAIL on ${c.check} but it passed`);
  }
  record('fixtures', errs.length===0, errs.length?errs:`${man.cases.length} fixtures behaved as expected`);
}

const cmd=process.argv[2]||'all';
const run={ schema:checkSchema, ids:checkIds, refs:checkRefs, lifecycle:checkLifecycle, provenance:checkProvenance, serialize:checkSerialize, vocab:checkVocab, fixtures:runFixtures };
if(cmd==='all'){ for(const fn of Object.values(run)) fn(); } else if(run[cmd]) run[cmd](); else { console.error('unknown command',cmd); process.exit(2); }

let failed=0;
for(const r of results){ const tag=r.ok?'PASS':'FAIL'; if(!r.ok) failed++; console.log(`[${tag}] ${r.check}: ${Array.isArray(r.detail)?'\n  - '+r.detail.join('\n  - '):r.detail}`); }
console.log(`\n${results.length-failed}/${results.length} checks passed`);
process.exit(failed>0?1:0);
