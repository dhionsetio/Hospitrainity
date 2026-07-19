#!/usr/bin/env node
// hsp-import — CP-09 deterministic importer/compiler: canonical package -> relational mirror.
// Implements the CP-08 import contract (import-contract.md) against a real engine (node:sqlite,
// the offline SQLite mirror named in the CP-08 design). Read-only over content: it PROJECTS the
// validated package into storage and re-asserts every invariant; it never edits a content entity.
//
// Deterministic + idempotent + transactional:
//   - schema is driven by phase-08/data-model.json (the CP-08 design artifact; single source)
//   - rows are inserted in dependency order inside one transaction with FK enforcement ON
//   - re-importing the identical package is a no-op (verified by content digest)
//   - engine-independent load script phase-09/curriculum.sql is emitted (sorted, canonical)
//   - a content-addressed build digest is emitted (hash over sorted canonical rows; the .db file
//     bytes are intentionally NOT used because SQLite page layout is not reproducible)
//
// Usage: node --experimental-sqlite hsp-import.mjs [--db <path>] [--keep]
// Exit 0 iff all import-contract invariants pass.
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { DatabaseSync } from 'node:sqlite';

const __dir = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dir, '../../../..');
const MIG = path.join(ROOT, 'docs/hospitrainity-migration');
const P3 = path.join(MIG, 'phase-03');
const P8 = path.join(MIG, 'phase-08');
const PKG = path.join(ROOT, 'curriculum/hospitrainity/0.3.0-draft');
const OUT = path.join(MIG, 'phase-09');

// ---- shared helpers (identical to hsp-validate.mjs / hsp-datamodel.mjs) ----
const readJson = (p) => JSON.parse(readFileSync(p, 'utf8'));
function sortKeys(v){ if(Array.isArray(v)) return v.map(sortKeys); if(v&&typeof v==='object'){const o={};for(const k of Object.keys(v).sort())o[k]=sortKeys(v[k]);return o;} return v; }
const canon = (o) => JSON.stringify(sortKeys(o)); // compact canonical (sorted keys)
function walk(dir){ const out=[]; for(const e of readdirSync(dir)){ const f=path.join(dir,e); const s=statSync(f); if(s.isDirectory()) out.push(...walk(f)); else if(f.endsWith('.json')) out.push(f); } return out; }
function uuidToBytes(u){const h=u.replace(/-/g,'');const b=Buffer.alloc(16);for(let i=0;i<16;i++)b[i]=parseInt(h.substr(i*2,2),16);return b;}
function bytesToUuid(b){const h=[...b].map(x=>x.toString(16).padStart(2,'0')).join('');return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`;}
function uuidv5(name,ns){const hash=createHash('sha1').update(Buffer.concat([uuidToBytes(ns),Buffer.from(name,'utf8')])).digest();const b=Buffer.from(hash.subarray(0,16));b[6]=(b[6]&0x0f)|0x50;b[8]=(b[8]&0x3f)|0x80;return bytesToUuid(b);}
const sha256 = (s) => createHash('sha256').update(s).digest('hex');

// Lifecycle vocabulary is the single source of truth in controlled-vocabularies.json
// (the same list hsp-validate.mjs ties schema enums and checkLifecycle to). Deriving it
// here prevents the importer's CHECK constraint from drifting from the validator.
const LIFECYCLE = (() => {
  const v = readJson(path.join(P3,'controlled-vocabularies.json'));
  const s = v && v.vocabularies && v.vocabularies.status;
  if(!Array.isArray(s) || !s.length){ console.error('FATAL: controlled-vocabularies.json vocabularies.status missing'); process.exit(2); }
  return s;
})();
const q = (n) => '"'+n+'"'; // quote SQL identifier (columns like `order` are reserved words)
const argv = process.argv.slice(2);
const DB_PATH = (() => { const i = argv.indexOf('--db'); return i>=0 ? argv[i+1] : path.join(OUT,'curriculum.db'); })();
const KEEP = argv.includes('--keep');

// ---- 1. load package (same classification as the CP-08 design tool) ----
const model = readJson(path.join(P8,'data-model.json'));
const registry = readJson(path.join(P3,'id-registry.json'));
const NS = registry.namespace;

const entitiesByType = new Map();
const codeToType = new Map();
const frameworkDocs = new Map(); // kind -> {doc, content_version}
for(const f of walk(PKG)){
  const rel = path.relative(PKG, f).replace(/\\/g,'/');
  const o = readJson(f);
  const t = o.entity_type;
  if(typeof t === 'string'){
    if(!entitiesByType.has(t)) entitiesByType.set(t, []);
    entitiesByType.get(t).push(o);
    if(o.id && o.code) codeToType.set(o.code, t);
  } else {
    frameworkDocs.set(path.basename(rel,'.json'), { doc:o, content_version:o.content_version ?? o.version ?? null });
  }
}
// outcome ids (OUT-*) are the 'codes' targeted by outcome_codes[]
const outcomeCodes = new Set();
if(frameworkDocs.has('outcome-alignments')){ for(const o of (frameworkDocs.get('outcome-alignments').doc.outcomes||[])) if(o.id) outcomeCodes.add(o.id); }

const tableName = (t) => t.replace(/-/g,'_');
const typeByTable = new Map([...entitiesByType.keys()].map(t=>[tableName(t),t]));

// scalar SQL foreign keys the engine can enforce directly (dependency-ordered inserts).
const SQL_FK = {
  lesson_section:{chapter_code:'chapter'}, activity:{lesson_code:'lesson_section'},
  prompt_item:{activity_code:'activity'}, answer_model:{prompt_code:'prompt_item'},
  feedback_model:{prompt_code:'prompt_item'}, rubric:{activity_code:'activity'},
};
// full FK spec for verification (incl. array + polymorphic, per CP-08 DEC-028).
const FKS = [
  {table:'lesson_section',column:'chapter_code',card:'one',target:'chapter'},
  {table:'activity',column:'lesson_code',card:'one',target:'lesson-section'},
  {table:'activity',column:'outcome_codes',card:'many',target:'outcome'},
  {table:'chapter',column:'outcome_codes',card:'many',target:'outcome'},
  {table:'prompt_item',column:'activity_code',card:'one',target:'activity'},
  {table:'answer_model',column:'prompt_code',card:'one',target:'prompt-item'},
  {table:'feedback_model',column:'prompt_code',card:'one',target:'prompt-item'},
  {table:'rubric',column:'activity_code',card:'one',target:'activity'},
  {table:'source_provenance',column:'target_code',card:'one',target:'ANY_ENTITY'},
  {table:'migration_edge',column:'canonical_code',card:'one',target:'ANY_ENTITY',nullable:true},
];
function targetHas(target, code){
  if(target==='outcome') return outcomeCodes.has(code);
  if(target==='ANY_ENTITY') return codeToType.has(code) || outcomeCodes.has(code);
  return codeToType.get(code)===target;
}

// ---- 2. schema helpers driven by data-model.json ----
const sqliteType = (t) => t==='integer' ? 'INTEGER' : 'TEXT'; // uuid/text/jsonb -> TEXT
function tableMeta(t){
  const cols = t.columns.map(c=>c.name);
  const has = (n)=>cols.includes(n);
  return { name:t.table, entity_type:t.entity_type, columns:t.columns, cols, has,
    pk: has('id') ? 'id' : (has('code') ? 'code' : '_rowkey'),
    surrogate: !has('id') && !has('code'),
    keyCol: has('code') ? 'code' : (has('id') ? 'id' : '_rowkey'),
    hasStatus: has('status') };
}
const ORDER = ['chapter','lesson_section','activity','prompt_item','answer_model','feedback_model','rubric','source_provenance','migration_edge'];
const orderedTables = [...model.tables].sort((a,b)=>{
  const ia=ORDER.indexOf(a.table), ib=ORDER.indexOf(b.table);
  return (ia<0?99:ia)-(ib<0?99:ib) || (a.table<b.table?-1:1);
});

function ddlFor(meta){
  const L=[]; L.push(`CREATE TABLE ${meta.name} (`);
  const lines=[];
  for(const c of meta.columns){
    let line=`  ${q(c.name)} ${sqliteType(c.type)}${c.nullable?'':' NOT NULL'}`;
    const fk = SQL_FK[meta.name]?.[c.name];
    if(fk) line += ` REFERENCES ${fk}(${q('code')})`;
    lines.push(line);
  }
  if(meta.surrogate) lines.push('  _rowkey TEXT NOT NULL');
  lines.push('  doc TEXT NOT NULL');
  const cons=[];
  cons.push(`  PRIMARY KEY (${q(meta.pk)})`);
  if(meta.has('code')) cons.push(`  UNIQUE (${q('code')})`);
  if(meta.hasStatus) cons.push(`  CHECK (${q('status')} IN (${LIFECYCLE.map(s=>`'${s}'`).join(',')}))`);
  L.push([...lines,...cons].join(',\n'));
  L.push(');');
  return L.join('\n');
}
const FRAMEWORK_DDL = 'CREATE TABLE framework_document (\n  kind TEXT PRIMARY KEY,\n  content_version TEXT,\n  doc TEXT NOT NULL\n);';

function colValue(o, c){
  const v=o[c.name];
  if(v===undefined||v===null) return null;
  if(c.type==='integer') return Number(v);
  if(c.type==='jsonb') return canon(v);
  return String(v);
}

// ---- 3. import into a DatabaseSync (transactional, dependency-ordered) ----
function buildSchema(db){
  db.exec('PRAGMA foreign_keys = ON;');
  db.exec(FRAMEWORK_DDL);
  for(const t of orderedTables) db.exec(ddlFor(tableMeta(t)));
}
function importAll(db){
  let frameworkRows=0, entityRows=0;
  db.exec('BEGIN');
  // framework documents first (upsert by kind)
  const fstmt = db.prepare('INSERT INTO framework_document (kind,content_version,doc) VALUES (?,?,?) ON CONFLICT(kind) DO UPDATE SET content_version=excluded.content_version, doc=excluded.doc');
  for(const kind of [...frameworkDocs.keys()].sort()){ const fd=frameworkDocs.get(kind); fstmt.run(kind, fd.content_version, canon(fd.doc)); frameworkRows++; }
  // entities in dependency order (upsert by natural/surrogate key)
  for(const t of orderedTables){
    const meta=tableMeta(t);
    const type=meta.entity_type;
    const rows=(entitiesByType.get(type)||[]).slice().sort((a,b)=>{
      const ka=a.code??canon(a), kb=b.code??canon(b); return ka<kb?-1:ka>kb?1:0;
    });
    const colNames=meta.columns.map(c=>c.name).concat(meta.surrogate?['_rowkey']:[],['doc']);
    const ph=colNames.map(()=>'?').join(',');
    const upd=colNames.filter(c=>c!==meta.pk).map(c=>`${q(c)}=excluded.${q(c)}`).join(', ');
    const stmt=db.prepare(`INSERT INTO ${meta.name} (${colNames.map(q).join(',')}) VALUES (${ph}) ON CONFLICT(${q(meta.pk)}) DO UPDATE SET ${upd}`);
    for(const o of rows){
      const vals=meta.columns.map(c=>colValue(o,c));
      if(meta.surrogate) vals.push(sha256(canon(o)));
      vals.push(canon(o));
      stmt.run(...vals);
      entityRows++;
    }
  }
  db.exec('COMMIT');
  return { frameworkRows, entityRows };
}

// content digest: hash over sorted (table, key, doc) — engine-independent, reproducible.
function contentDigest(db){
  const parts=[];
  const fw=db.prepare('SELECT kind,doc FROM framework_document ORDER BY kind').all();
  for(const r of fw) parts.push(`framework_document\t${r.kind}\t${r.doc}`);
  for(const t of orderedTables){
    const meta=tableMeta(t);
    const rows=db.prepare(`SELECT ${q(meta.keyCol)} AS k, doc FROM ${meta.name} ORDER BY ${q(meta.keyCol)}`).all();
    for(const r of rows) parts.push(`${meta.name}\t${r.k}\t${r.doc}`);
  }
  return sha256(parts.join('\n'));
}

// ---- 4. self-verification (re-assert the CP-08 import-contract invariants) ----
function verify(db){
  const errors=[];
  let idChecked=0, projChecked=0, refChecked=0, refUnresolved=0;
  // (a) id integrity + (b) projection integrity
  for(const t of orderedTables){
    const meta=tableMeta(t);
    if(!meta.has('id')||!meta.has('code')) { /* migration_edge: no id/code */ }
    const rows=db.prepare(`SELECT * FROM ${meta.name}`).all();
    for(const r of rows){
      const doc=JSON.parse(r.doc);
      if(meta.has('id')&&meta.has('code')){ idChecked++; if(uuidv5(r.code,NS)!==r.id) errors.push(`id integrity: ${r.code} -> ${r.id}`); }
      for(const c of meta.columns){
        const stored=r[c.name]; const src=doc[c.name];
        if(src===undefined||src===null){ if(stored!==null&&stored!==undefined) errors.push(`projection(extra): ${meta.name}.${c.name}`); continue; }
        projChecked++;
        const expect = c.type==='integer' ? Number(src) : c.type==='jsonb' ? canon(src) : String(src);
        if(String(stored)!==String(expect)) errors.push(`projection: ${meta.name}.${c.name} (${r.code??r._rowkey})`);
      }
    }
  }
  // (c) referential integrity: scalar (engine + code), array, polymorphic
  const codeSet = new Map(); // table -> Set(code)
  for(const t of orderedTables){ const meta=tableMeta(t); if(meta.has('code')){ const s=new Set(db.prepare(`SELECT code FROM ${meta.name}`).all().map(r=>r.code)); codeSet.set(meta.name,s);} }
  for(const fk of FKS){
    const meta=tableMeta(model.tables.find(x=>x.table===fk.table));
    const rows=db.prepare(`SELECT * FROM ${fk.table}`).all();
    for(const r of rows){
      const raw=r[fk.column]; if(raw===null||raw===undefined) continue;
      const vals = fk.card==='many' ? JSON.parse(raw) : [raw];
      for(const code of vals){ refChecked++; if(!targetHas(fk.target,code)){ refUnresolved++; errors.push(`ref: ${fk.table}.${fk.column} -> ${code}`); } }
    }
  }
  // (d) lifecycle (belt-and-suspenders; CHECK already enforces)
  for(const t of orderedTables){ const meta=tableMeta(t); if(!meta.hasStatus) continue; for(const r of db.prepare(`SELECT status FROM ${meta.name}`).all()) if(!LIFECYCLE.includes(r.status)) errors.push(`lifecycle: ${r.status}`); }
  // (e) registry coverage: every id-bearing entity code present in id-registry
  const registryCodes=new Set((registry.entries||[]).map(e=>e.code));
  let idBearing=0, missing=0;
  for(const t of orderedTables){ const meta=tableMeta(t); if(!(meta.has('id')&&meta.has('code'))) continue; for(const r of db.prepare(`SELECT code FROM ${meta.name}`).all()){ idBearing++; if(!registryCodes.has(r.code)){ missing++; errors.push(`registry-missing: ${r.code}`); } } }
  return { errors, idChecked, projChecked, refChecked, refUnresolved, idBearing, registryMissing:missing };
}

// ---- 5. deterministic engine-independent load script ----
function sqlLiteral(v){ if(v===null||v===undefined) return 'NULL'; if(typeof v==='number') return String(v); return `'${String(v).replace(/'/g,"''")}'`; }
function emitLoadScript(){
  const L=[];
  L.push('-- Hospitrainity curriculum — deterministic SQLite load script (CP-09).');
  L.push('-- Generated by hsp-import.mjs from the validated canonical package. Engine-independent.');
  L.push('-- Reproduce: node --experimental-sqlite hsp-import.mjs   (regenerates this file identically).');
  L.push('PRAGMA foreign_keys = ON;');
  L.push('BEGIN;');
  L.push(FRAMEWORK_DDL);
  for(const t of orderedTables) L.push(ddlFor(tableMeta(t)));
  L.push('');
  // framework rows
  for(const kind of [...frameworkDocs.keys()].sort()){ const fd=frameworkDocs.get(kind);
    L.push(`INSERT INTO framework_document (kind,content_version,doc) VALUES (${sqlLiteral(kind)},${sqlLiteral(fd.content_version)},${sqlLiteral(canon(fd.doc))});`);
  }
  for(const t of orderedTables){
    const meta=tableMeta(t); const type=meta.entity_type;
    const rows=(entitiesByType.get(type)||[]).slice().sort((a,b)=>{ const ka=a.code??canon(a),kb=b.code??canon(b); return ka<kb?-1:ka>kb?1:0; });
    const colNames=meta.columns.map(c=>c.name).concat(meta.surrogate?['_rowkey']:[],['doc']);
    for(const o of rows){
      const vals=meta.columns.map(c=>sqlLiteral(colValue(o,c)));
      if(meta.surrogate) vals.push(sqlLiteral(sha256(canon(o))));
      vals.push(sqlLiteral(canon(o)));
      L.push(`INSERT INTO ${meta.name} (${colNames.map(q).join(',')}) VALUES (${vals.join(',')});`);
    }
  }
  L.push('COMMIT;');
  L.push('');
  return L.join('\n');
}

// ---- 6. run ----
if(!existsSync(OUT)) mkdirSync(OUT,{recursive:true});
if(existsSync(DB_PATH)) rmSync(DB_PATH);
const db = new DatabaseSync(DB_PATH);
buildSchema(db);
const pass1 = importAll(db);
const digest1 = contentDigest(db);
const pass2 = importAll(db);          // idempotency: re-import must not change content
const digest2 = contentDigest(db);
const idempotent = digest1===digest2;
const v = verify(db);
db.close();
if(!KEEP && existsSync(DB_PATH)) rmSync(DB_PATH);

const loadScript = emitLoadScript();
writeFileSync(path.join(OUT,'curriculum.sql'), loadScript, 'utf8');
const scriptDigest = sha256(loadScript);

const ok = v.errors.length===0 && idempotent && v.refUnresolved===0 && v.registryMissing===0;
const report = {
  generated_by:'CP-09', tool:'hsp-import.mjs',
  approach:'deterministic projection of the validated canonical package into the CP-08 SQLite mirror; idempotent, transactional, FK-enforced',
  engine:`node:sqlite (${process.version})`, physical_target:'PostgreSQL 15+ (JSONB) / SQLite mirror',
  standards:['JSON Schema 2020-12','SemVer 2.0.0','RFC 9562 UUIDv5'],
  rows:{ framework_documents:pass1.frameworkRows, entities:pass1.entityRows },
  tables: orderedTables.map(t=>({ table:t.table, entity_type:t.entity_type, rows:(entitiesByType.get(t.entity_type)||[]).length })),
  invariants:{
    id_integrity_checked:v.idChecked,
    projection_checks:v.projChecked,
    referential_refs_checked:v.refChecked,
    referential_unresolved:v.refUnresolved,
    registry_id_bearing:v.idBearing,
    registry_missing:v.registryMissing,
    idempotent,
    content_digest:digest1,
    load_script_sha256:scriptDigest,
  },
  errors:v.errors.slice(0,50),
  ok,
};
writeFileSync(path.join(OUT,'import-report.json'), JSON.stringify(sortKeys(report),null,2)+'\n', 'utf8');

console.log(`framework_document: ${pass1.frameworkRows} rows; entities: ${pass1.entityRows} rows across ${orderedTables.length} tables`);
console.log(`id integrity: ${v.idChecked} checked; projection: ${v.projChecked} checked; refs: ${v.refChecked} checked, ${v.refUnresolved} unresolved`);
console.log(`registry coverage: ${v.idBearing} id-bearing entities, ${v.registryMissing} missing`);
console.log(`idempotent re-import: ${idempotent} (digest ${digest1.slice(0,12)}…)`);
console.log(`load script: phase-09/curriculum.sql (sha256 ${scriptDigest.slice(0,12)}…)`);
console.log(`import.ok=${ok}${v.errors.length?` — ${v.errors.length} error(s): `+v.errors.slice(0,3).join('; '):''}`);
process.exit(ok?0:2);
