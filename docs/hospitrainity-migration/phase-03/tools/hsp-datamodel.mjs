#!/usr/bin/env node
// CP-08 platform/database design tool: deterministic, read-only.
// Reads the 17 JSON schemas + the canonical package + id-registry and derives a storage model:
//   - phase-08/data-model.json : logical tables (columns, primary key, foreign keys) per entity
//   - phase-08/schema.sql      : PostgreSQL DDL (canonical doc JSONB + extracted columns + PK/FK/CHECK)
//   - phase-08/coverage.json   : verification that every package entity maps to a table, every
//                                declared foreign key resolves against real data, and the id-registry
//                                covers every id-bearing entity.
// ADDITIVE ARCHITECTURE only: mutates no content entity; asserts the design against the real
// package so nothing is invented.
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dir = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dir, '../../../..');
const MIG = path.join(ROOT, 'docs/hospitrainity-migration');
const P3 = path.join(MIG, 'phase-03');
const PKG = path.join(ROOT, 'curriculum/hospitrainity/0.3.0-draft');
const OUT = path.join(MIG, 'phase-08');

const readJson = (p) => JSON.parse(readFileSync(p, 'utf8'));
function sortKeys(v){ if(Array.isArray(v)) return v.map(sortKeys); if(v&&typeof v==='object'){const o={};for(const k of Object.keys(v).sort())o[k]=sortKeys(v[k]);return o;} return v; }
const canonical = (o) => JSON.stringify(sortKeys(o), null, 2) + '\n';
function walk(dir){ const out=[]; for(const e of readdirSync(dir)){ const f=path.join(dir,e); const s=statSync(f); if(s.isDirectory()) out.push(...walk(f)); else if(f.endsWith('.json')) out.push(f); } return out; }

// ---- 1. load every package JSON, classify ----
const files = walk(PKG);
const entitiesByType = new Map();   // entity_type -> [obj]
const codeToType = new Map();       // code -> entity_type (id-bearing content entities)
const frameworkDocs = [];           // {kind, rel} singletons with no entity_type const
for(const f of files){
  const rel = path.relative(PKG, f).replace(/\\/g,'/');
  const o = readJson(f);
  const t = o.entity_type;
  if(typeof t === 'string'){
    if(!entitiesByType.has(t)) entitiesByType.set(t, []);
    entitiesByType.get(t).push(o);
    if(o.id && o.code) codeToType.set(o.code, t);
  } else {
    frameworkDocs.push({ kind: path.basename(rel, '.json'), rel });
  }
}
// id-registry lives beside the schemas in phase-03, not inside the package.
const registry = readJson(path.join(P3, 'id-registry.json'));

// ---- 2. schema-grounded foreign-key specification (verified against real data below) ----
// cardinality: 'one' = scalar code column; 'many' = jsonb array of codes.
const FKS = [
  { table: 'lesson_section',    column: 'chapter_code',   card: 'one',  target: 'chapter' },
  { table: 'activity',          column: 'lesson_code',    card: 'one',  target: 'lesson-section' },
  { table: 'activity',          column: 'outcome_codes',  card: 'many', target: 'outcome' },
  { table: 'chapter',           column: 'outcome_codes',  card: 'many', target: 'outcome' },
  { table: 'prompt_item',       column: 'activity_code',  card: 'one',  target: 'activity' },
  { table: 'answer_model',      column: 'prompt_code',    card: 'one',  target: 'prompt-item' },
  { table: 'feedback_model',    column: 'prompt_code',    card: 'one',  target: 'prompt-item' },
  { table: 'rubric',            column: 'activity_code',  card: 'one',  target: 'activity' },
  { table: 'source_provenance', column: 'target_code',    card: 'one',  target: 'ANY_ENTITY' },
  { table: 'migration_edge',    column: 'canonical_code', card: 'one',  target: 'ANY_ENTITY', nullable: true },
];
const tableName = (t) => t.replace(/-/g,'_');

// 'outcome' codes are the ids (OUT-*) declared in framework/outcome-alignments.json outcomes[].id
const outcomeCodes = new Set();
const oa = frameworkDocs.find(d => d.kind === 'outcome-alignments');
if(oa){ const doc = readJson(path.join(PKG, oa.rel)); for(const o of (doc.outcomes||[])) if(o.id) outcomeCodes.add(o.id); }
function targetHas(target, code){
  if(target === 'outcome') return outcomeCodes.has(code);
  if(target === 'ANY_ENTITY') return codeToType.has(code) || outcomeCodes.has(code);
  return codeToType.get(code) === target;
}

// ---- 3. column typing (canonical doc + extracted scalar columns) ----
const SCALAR_TEXT = new Set(['code','content_version','status','entity_type','title','stem','channel','timing','participation','pedagogical_function','response_form','scoring_mode','cefr_activity','media_type','filename','checksum','relation','disposition','legacy_ref','excerpt_sha256','target_code','chapter_code','lesson_code','activity_code','prompt_code','canonical_code','module','note','alt_text']);
function sqlType(field){
  if(field === 'id') return 'uuid';
  if(field === 'order') return 'integer';
  if(SCALAR_TEXT.has(field)) return 'text';
  return 'jsonb'; // arrays/objects: outcome_codes, accessibility, source_locator, messages, criteria, accepted, ...
}

const tables = [];
for(const [type, rows] of [...entitiesByType.entries()].sort((a,b)=>a[0]<b[0]?-1:1)){
  const cols = new Set();
  for(const r of rows) for(const k of Object.keys(r)) cols.add(k);
  const columns = [...cols].sort().map(c => ({ name: c, type: sqlType(c), nullable: !['id','code','content_version','status','entity_type'].includes(c) }));
  const pk = rows[0].id ? 'id' : null;
  const fks = FKS.filter(fk => fk.table === tableName(type)).map(fk => ({ column: fk.column, cardinality: fk.card, references: fk.target, nullable: !!fk.nullable }));
  tables.push({ table: tableName(type), entity_type: type, row_count: rows.length, primary_key: pk, unique: ['code'], columns, foreign_keys: fks, canonical_column: 'doc jsonb (full canonical entity)' });
}

// ---- 4. verify FKs against real data ----
const fkChecks = [];
let fkViolations = 0;
for(const fk of FKS){
  const srcType = [...entitiesByType.keys()].find(t => tableName(t) === fk.table);
  const srcRows = entitiesByType.get(srcType) || [];
  let checked = 0, unresolved = 0;
  for(const r of srcRows){
    const v = r[fk.column];
    if(v == null) continue;
    const vals = fk.card === 'many' ? (Array.isArray(v)?v:[]) : [v];
    for(const code of vals){ checked++; if(!targetHas(fk.target, code)){ unresolved++; fkViolations++; } }
  }
  fkChecks.push({ table: fk.table, column: fk.column, references: fk.target, checked, unresolved });
}

// ---- 5. registry coverage ----
const registryCodes = new Set((registry?.entries||[]).map(e => e.code).filter(Boolean));
const idBearingCodes = [...codeToType.keys()].sort();
const notInRegistry = idBearingCodes.filter(c => !registryCodes.has(c));
const registryExtra = [...registryCodes].filter(c => !codeToType.has(c)).sort(); // framework/outcome/competency/cefr ids: expected, not an error

// ---- 6. emit DDL ----
function ddl(){
  const L = [];
  L.push('-- Hospitrainity canonical package — PostgreSQL physical model (CP-08, additive design).');
  L.push('-- Generated deterministically by hsp-datamodel.mjs from the JSON schemas + package.');
  L.push('-- Pattern: each entity is stored as its full canonical JSON in doc JSONB, with extracted');
  L.push('-- scalar columns for indexing/constraints, plus code-based foreign keys and lifecycle CHECKs.');
  L.push('CREATE SCHEMA IF NOT EXISTS hospitrainity;');
  L.push('');
  L.push('-- Framework singletons + id-registry stored as versioned documents.');
  L.push('CREATE TABLE hospitrainity.framework_document (');
  L.push('  kind text PRIMARY KEY,');
  L.push('  content_version text,');
  L.push('  doc jsonb NOT NULL');
  L.push(');');
  L.push('');
  for(const t of tables){
    L.push(`-- ${t.entity_type} (${t.row_count} rows in current package)`);
    L.push(`CREATE TABLE hospitrainity.${t.table} (`);
    const lines = [];
    for(const c of t.columns){ lines.push(`  ${c.name} ${c.type}${c.nullable ? '' : ' NOT NULL'}`); }
    lines.push('  doc jsonb NOT NULL');
    L.push(lines.join(',\n'));
    const cons = [];
    if(t.primary_key) cons.push(`  PRIMARY KEY (${t.primary_key})`);
    cons.push('  UNIQUE (code)');
    cons.push("  CHECK (status IN ('draft','draft_pending_qualified_review','in_review','approved','published','archived','superseded'))");
    L[L.length-1] += ',';
    L.push(cons.join(',\n'));
    L.push(');');
    L.push('');
  }
  L.push('-- Foreign keys (code-based). Polymorphic + array refs are validated by the importer/validator.');
  for(const fk of FKS){
    if(fk.target === 'ANY_ENTITY'){ L.push(`-- ${fk.table}.${fk.column} -> any entity code (polymorphic; validated on import)`); continue; }
    if(fk.target === 'outcome'){ L.push(`-- ${fk.table}.${fk.column} (jsonb array) -> framework_document('outcome-alignments').outcomes[].id (validated on import)`); continue; }
    if(fk.card === 'many'){ L.push(`-- ${fk.table}.${fk.column} (jsonb array) -> ${tableName(fk.target)}.code (validated on import)`); continue; }
    L.push(`ALTER TABLE hospitrainity.${fk.table} ADD CONSTRAINT fk_${fk.table}_${fk.column} FOREIGN KEY (${fk.column}) REFERENCES hospitrainity.${tableName(fk.target)}(code);`);
  }
  L.push('');
  return L.join('\n');
}

// ---- 7. write outputs ----
if(!existsSync(OUT)) mkdirSync(OUT, { recursive: true });
const dataModel = {
  generated_by: 'CP-08', tool: 'hsp-datamodel.mjs',
  approach: 'document-relational hybrid: canonical entity in doc JSONB + extracted scalar columns + code FKs + lifecycle CHECK',
  physical_target: 'PostgreSQL 15+ (JSONB); SQLite mirror for the offline standalone renderer',
  standards: ['JSON Schema 2020-12', 'SemVer 2.0.0 (schema_version vs content_version)', 'RFC 9562 UUIDv5 ids'],
  framework_documents: frameworkDocs.map(d=>d.kind).sort(),
  tables,
};
const coverage = {
  generated_by: 'CP-08', tool: 'hsp-datamodel.mjs',
  entity_types: [...entitiesByType.keys()].sort(),
  total_id_bearing_entities: idBearingCodes.length,
  registry_entries: registryCodes.size,
  fk_checks: fkChecks,
  fk_violations: fkViolations,
  registry_missing_ids: notInRegistry,
  registry_non_entity_ids: registryExtra.length,
  ok: fkViolations === 0 && notInRegistry.length === 0,
};
writeFileSync(path.join(OUT,'data-model.json'), canonical(dataModel), 'utf8');
writeFileSync(path.join(OUT,'coverage.json'), canonical(coverage), 'utf8');
writeFileSync(path.join(OUT,'schema.sql'), ddl(), 'utf8');

console.log(`data-model: ${tables.length} entity tables + framework_document; id-bearing entities=${idBearingCodes.length}`);
console.log(`fk checks: ${fkChecks.reduce((a,c)=>a+c.checked,0)} refs checked, ${fkViolations} violation(s)`);
console.log(`registry: ${registryCodes.size} entries; missing=${notInRegistry.length}, non-entity(framework)=${registryExtra.length}`);
console.log(`coverage.ok=${coverage.ok}`);
process.exit(coverage.ok ? 0 : 2);
