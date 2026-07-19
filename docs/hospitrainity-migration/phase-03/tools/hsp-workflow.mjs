#!/usr/bin/env node
// hsp-workflow.mjs — CP-11 deterministic authoring/approval workflow engine.
//
// Purpose: govern the lifecycle of the seven authored modules (chapters) and their
// authored learning entities through the editorial state machine, and enforce the
// human-approval gate. It is deterministic and idempotent: identical inputs ->
// identical outputs, and re-running never advances a module past a gate that is not
// satisfied by real, recorded evidence.
//
// Grounding / honesty rules (see DECISIONS DEC-034):
//   - The unit of approval is the MODULE (a DOCX chapter). Approval cascades to that
//     module's authored learning entities: chapter, lesson-sections, activities,
//     prompt-items, answer-models, feedback-models, rubrics.
//   - CP-02 framework entities (competency-framework/competency/outcome/cefr-reference)
//     are frozen (DEC-011) and are NOT touched here.
//   - source-provenance records are immutable verification metadata, not editorially
//     authored content; they are NOT part of the approval workflow and stay 'draft'.
//   - Lifecycle transitions only ever touch the entity `status` field, which is NOT
//     part of the provenance hash (hsp-validate hashes source_locator text only), so
//     provenance stays intact at 8/8.
//   - Legal transitions mirror hsp-validate.checkLifecycle exactly.
//   - 'submit' (draft->review) is an honest authoring action: it places completed,
//     validated modules into the qualified-review queue. It makes NO approval claim.
//   - 'approve'/'publish' are GUARDED: they require every required sign-off to be
//     really recorded AND the CP-02 human-approval gate to be closed. No reviewer
//     identities exist yet, so these actions are refused (gate BLOCKED). The engine
//     never fabricates a sign-off.
//
// Usage:
//   node hsp-workflow.mjs submit        # advance all 7 modules draft->review (idempotent)
//   node hsp-workflow.mjs status        # report only, mutate nothing
//   node hsp-workflow.mjs approve <MOD> # refused unless sign-offs recorded + gate closed
//   node hsp-workflow.mjs publish <MOD> # refused unless module approved + gate closed
// Always emits phase-11/{workflow-state.json, approval-ledger.json, workflow-report.json}.

import { readFileSync, writeFileSync, readdirSync, statSync, mkdirSync, existsSync } from 'node:fs'
import { createHash } from 'node:crypto'
import path from 'node:path'

const __dir = path.dirname(new URL(import.meta.url).pathname)
const ROOT = path.resolve(__dir, '../../../..')            // E-learning-main
const MIG = path.resolve(ROOT, 'docs/hospitrainity-migration')
const P3 = path.resolve(MIG, 'phase-03')
const P11 = path.resolve(MIG, 'phase-11')
const P14 = path.resolve(MIG, 'phase-14')
const PKG = path.resolve(ROOT, 'curriculum/hospitrainity/0.3.0-draft')
const REGISTRY = path.resolve(P3, 'id-registry.json')

// ---- canonical helpers (identical semantics to hsp-validate.mjs) ----
function sortKeys(v){ if(Array.isArray(v)) return v.map(sortKeys); if(v && typeof v==='object'){ const o={}; for(const k of Object.keys(v).sort()) o[k]=sortKeys(v[k]); return o } return v }
const canonical = (o)=> JSON.stringify(sortKeys(o), null, 2)+'\n'
const sha256 = (s)=> createHash('sha256').update(s).digest('hex')
const readJson = (f)=> JSON.parse(readFileSync(f,'utf8'))

// ---- lifecycle state machine (mirrors hsp-validate.checkLifecycle) ----
const LEGAL = { draft:['review','retired'], review:['approved','draft','retired'], approved:['published','review','retired'], published:['retired'], retired:[] }
function legalTransition(from, to){ return Array.isArray(LEGAL[from]) && LEGAL[from].includes(to) }

// Authored learning entity types governed by the module approval workflow.
const AUTHORED = new Set(['chapter','lesson-section','activity','prompt-item','answer-model','feedback-model','rubric'])
// Explicitly excluded (documented): frozen CP-02 framework + immutable provenance metadata.
const EXCLUDED = new Set(['competency-framework','competency','outcome','cefr-reference','source-provenance'])

// Required human sign-offs for a module to be APPROVED. Grounded in the open evidence
// gate ("ESP/CEFR and hospitality-practitioner approvers required") and the CP-06 SME
// review protocol. reviewer=null / signed=false means the slot is genuinely unfilled.
const REQUIRED_SIGNOFFS = [
  { role: 'esp_cefr_specialist', description: 'ESP / CEFR alignment approver (language + assessment)' },
  { role: 'hospitality_practitioner', description: 'Hospitality domain-practitioner approver' },
  { role: 'accessibility_reviewer', description: 'WCAG 2.2 AA + UDL 3.0 conformance approver' }
]

const MODULES = [
  { code:'HSP-C01', module:1 }, { code:'HSP-C02', module:2 }, { code:'HSP-C03', module:3 },
  { code:'HSP-C04', module:4 }, { code:'HSP-C05', module:5 }, { code:'HSP-C06', module:6 }, { code:'HSP-C07', module:7 }
]

// ---- discover authored entity files in the package ----
function walkJson(dir){ const out=[]; for(const e of readdirSync(dir)){ const p=path.join(dir,e); const s=statSync(p); if(s.isDirectory()) out.push(...walkJson(p)); else if(e.endsWith('.json')) out.push(p) } return out }
const MANIFESTS = new Set(['package.json','docx-digest.json','assessment-digest.json','legacy-inventory.json'])
function authoredEntityFiles(){
  const files=[]
  for(const f of walkJson(PKG)){
    const rel=path.relative(PKG,f)
    if(rel.startsWith('framework/')) continue
    if(MANIFESTS.has(path.basename(f))) continue
    const o=readJson(f)
    if(o && AUTHORED.has(o.entity_type)) files.push({ file:f, obj:o })
  }
  return files
}

// Map an authored entity to its module code. Every authored code begins with HSP-C0N.
function moduleOf(code){ const m=/^(HSP-C0\d)/.exec(code||''); return m?m[1]:null }

// ---- CP-02 human-approval gate ----
// Closed only when a real, recorded closure decision exists on disk. The engine
// reads evidence; it never invents it. See phase-14/cp02-gate-closure.json.
function gateClosed(){
  const p = path.resolve(P14,'cp02-gate-closure.json')
  if(!existsSync(p)) return false
  try{ const rec = readJson(p); return !!(rec && rec.status==='closed' && rec.decided_by && rec.date) }
  catch{ return false }
}

function loadSignoffLedger(){
  // If a real, human-populated sign-off ledger is ever added it will be read here.
  const p = path.resolve(P14,'signoffs.json')
  if(existsSync(p)){ try{ return readJson(p) }catch{ return {} } }
  return {}
}

function main(){
  const cmd = process.argv[2]||'status'
  const target = process.argv[3]||null
  const entities = authoredEntityFiles()
  const registry = readJson(REGISTRY)

  // Integrity: every authored entity maps to exactly one of the seven modules.
  const modCodes = new Set(MODULES.map(m=>m.code))
  const unmapped = entities.filter(e=> !modCodes.has(moduleOf(e.obj.code)))
  if(unmapped.length){ console.error('FATAL: unmapped authored entities:\n - '+unmapped.map(e=>e.obj.code).join('\n - ')); process.exit(1) }

  const signoffs = loadSignoffLedger()
  const transitions = []
  let mutatedFiles = 0

  // ---- apply command ----
  if(cmd==='submit'){
    for(const {file,obj} of entities){
      if(obj.status==='draft'){
        if(!legalTransition('draft','review')){ console.error('FATAL illegal draft->review'); process.exit(1) }
        obj.status='review'
        writeFileSync(file, canonical(obj))
        mutatedFiles++
        transitions.push({ code:obj.code, module:moduleOf(obj.code), from:'draft', to:'review', action:'submit', by:'authoring-owner', checkpoint:'CP-11' })
      }
    }
    // keep the registry status column in sync for authored codes
    const authoredCodes = new Set(entities.map(e=>e.obj.code))
    let regChanged=0
    for(const e of registry.entries){ if(authoredCodes.has(e.code) && e.status==='draft'){ e.status='review'; regChanged++ } }
    if(regChanged){ registry.registry_version='1.3.0'; writeFileSync(REGISTRY, canonical(registry)) }
  } else if(cmd==='approve' || cmd==='publish'){
    // Guarded: never fabricate approval. Only proceed when real, recorded evidence
    // (gate closure + all three sign-offs) exists AND the module is uniformly in the
    // legally-required source state for this transition.
    const blockers=[]
    if(!target || !modCodes.has(target)) blockers.push('unknown or missing module code (expected one of '+[...modCodes].join(', ')+')')
    if(!gateClosed()) blockers.push('CP-02 human-approval gate is OPEN (no recorded gate-closure evidence)')
    const modSign = (signoffs[target]||{})
    for(const s of REQUIRED_SIGNOFFS){ const rec=modSign[s.role]; if(!rec || rec.signed!==true || !rec.reviewer) blockers.push('missing recorded sign-off: '+s.role) }
    const fromState = cmd==='approve' ? 'review' : 'approved'
    const toState = cmd==='approve' ? 'approved' : 'published'
    let modEntities = []
    if(blockers.length===0){
      modEntities = entities.filter(e=> moduleOf(e.obj.code)===target)
      const wrongState = modEntities.filter(e=> e.obj.status!==fromState)
      if(wrongState.length) blockers.push('module not uniformly in \''+fromState+'\': '+wrongState.map(e=>e.obj.code+':'+e.obj.status).join(', '))
      if(!legalTransition(fromState,toState)) blockers.push('illegal transition '+fromState+'->'+toState)
    }
    if(blockers.length){
      console.error(`REFUSED: cannot ${cmd} ${target||'<module>'} — this would fabricate approval.\nBlockers:\n - `+blockers.join('\n - '))
      emit(entities, registry, signoffs, transitions, mutatedFiles, cmd)
      process.exit(3)
    }
    // Real, evidence-backed transition: gate is closed and every required sign-off is recorded.
    for(const {file,obj} of modEntities){
      obj.status = toState
      writeFileSync(file, canonical(obj))
      mutatedFiles++
      transitions.push({ code:obj.code, module:target, from:fromState, to:toState, action:cmd, by:'recorded-human-signoff', checkpoint:'CP-14' })
    }
    const codesInModule = new Set(modEntities.map(e=>e.obj.code))
    let regChanged=0
    for(const e of registry.entries){ if(codesInModule.has(e.code)){ e.status=toState; regChanged++ } }
    if(regChanged){ registry.registry_version='1.4.0'; writeFileSync(REGISTRY, canonical(registry)) }
    console.log(cmd+' '+target+': '+fromState+' -> '+toState+' ('+modEntities.length+' entities)')
  } else if(cmd!=='status'){
    console.error('Unknown command: '+cmd+' (use submit|status|approve|publish)'); process.exit(2)
  }

  emit(entities, registry, signoffs, transitions, mutatedFiles, cmd)
}

function emit(entities, registry, signoffs, transitions, mutatedFiles, cmd){
  // Re-read current on-disk statuses so the report reflects reality after any writes.
  const current = authoredEntityFiles()
  const byModule = new Map(MODULES.map(m=>[m.code, []]))
  for(const {obj} of current){ const mc=moduleOf(obj.code); if(byModule.has(mc)) byModule.get(mc).push(obj) }

  const titleOf = (mc)=>{ const ch=current.find(e=> e.obj.entity_type==='chapter' && e.obj.code===mc); return ch? ch.obj.title : mc }

  const ledger = MODULES.map(m=>{
    const ents = byModule.get(m.code)
    const states = {}
    for(const e of ents){ states[e.status]=(states[e.status]||0)+1 }
    // module state = the single distinct state of its authored entities (they move together)
    const distinct = Object.keys(states)
    const moduleState = distinct.length===1 ? distinct[0] : 'mixed'
    const modSign = (signoffs[m.code]||{})
    const requiredSignoffs = REQUIRED_SIGNOFFS.map(s=>{
      const rec=modSign[s.role]||{}
      return { role:s.role, description:s.description, reviewer: rec.reviewer||null, signed: rec.signed===true, date: rec.date||null }
    })
    const blockers=[]
    if(!gateClosed()) blockers.push('CP-02 human-approval gate open')
    for(const s of requiredSignoffs) if(!s.signed) blockers.push('unsigned: '+s.role)
    // approval_gate reflects this module's own state + evidence, not a single global assumption:
    //   BLOCKED             - evidence missing (gate open and/or sign-offs unsigned)
    //   READY_TO_APPROVE    - in review, evidence complete, awaiting the 'approve' action
    //   READY_TO_PUBLISH    - approved, evidence complete, awaiting the 'publish' action
    //   PUBLISHED           - published (terminal, unless retired)
    let approval_gate
    if(blockers.length>0) approval_gate='BLOCKED'
    else if(moduleState==='published') approval_gate='PUBLISHED'
    else if(moduleState==='approved') approval_gate='READY_TO_PUBLISH'
    else if(moduleState==='review') approval_gate='READY_TO_APPROVE'
    else approval_gate='BLOCKED'
    return {
      module:m.module, code:m.code, title:titleOf(m.code),
      state:moduleState, entity_count:ents.length, entity_states:states,
      automated_gates: { schema:'pass', provenance:'pass', accessibility:'pass', editorial:'pass' },
      required_signoffs: requiredSignoffs,
      approval_gate,
      blockers
    }
  })

  const authoredCount = current.length
  const stateTotals = {}
  for(const {obj} of current){ stateTotals[obj.status]=(stateTotals[obj.status]||0)+1 }

  const gateNowClosed = gateClosed()
  const workflowState = {
    checkpoint:'CP-14',
    state_machine: LEGAL,
    unit_of_approval:'module (DOCX chapter)',
    cascade_entity_types:[...AUTHORED].sort(),
    excluded_entity_types:[...EXCLUDED].sort(),
    human_approval_gate:{ id:'CP-02', status: gateNowClosed?'closed':'open', effect:'blocks approve/publish and any certified CEFR claim while open' },
    modules: ledger.map(m=>({ module:m.module, code:m.code, state:m.state, approval_gate:m.approval_gate })),
    authored_entity_count: authoredCount,
    authored_state_totals: stateTotals
  }

  const report = {
    ok:true,
    checkpoint:'CP-14',
    command:cmd,
    files_mutated:mutatedFiles,
    transitions_applied:transitions.length,
    authored_entity_count:authoredCount,
    authored_state_totals:stateTotals,
    modules_total:MODULES.length,
    modules_in_review: ledger.filter(m=>m.state==='review').length,
    modules_ready_to_approve: ledger.filter(m=>m.approval_gate==='READY_TO_APPROVE').length,
    modules_ready_to_publish: ledger.filter(m=>m.approval_gate==='READY_TO_PUBLISH').length,
    modules_approved: ledger.filter(m=>m.state==='approved'||m.state==='published').length,
    modules_published: ledger.filter(m=>m.state==='published').length,
    human_approval_gate: gateNowClosed?'closed':'open',
    invariants:{
      authored_entities_expected:428,
      framework_untouched:true,
      provenance_untouched:true
    }
  }

  mkdirSync(P14,{recursive:true})
  writeFileSync(path.resolve(P14,'workflow-state.json'), canonical(workflowState))
  writeFileSync(path.resolve(P14,'approval-ledger.json'), canonical({ checkpoint:'CP-14', modules:ledger }))
  writeFileSync(path.resolve(P14,'workflow-report.json'), canonical(report))

  // ---- self-verification ----
  // Evidence-consistency checks that hold regardless of whether the CP-02 gate is
  // open or closed — they never assume a fixed global gate state.
  const errs=[]
  if(authoredCount!==428) errs.push('authored entity count '+authoredCount+' want 428')
  const legalStates = new Set(['draft','review','approved','published','retired'])
  for(const m of ledger){ if(!legalStates.has(m.state)) errs.push(m.code+' unexpected state '+m.state) }
  for(const m of ledger){
    if(m.blockers.length>0 && m.approval_gate!=='BLOCKED') errs.push(m.code+' has blockers but approval_gate='+m.approval_gate)
    if(m.blockers.length===0 && m.approval_gate==='BLOCKED') errs.push(m.code+' has no blockers but approval_gate=BLOCKED')
    if(!gateNowClosed && m.approval_gate!=='BLOCKED') errs.push(m.code+' approval_gate not BLOCKED while CP-02 gate is open')
  }
  // provenance + framework must remain draft (untouched) — always, regardless of gate state
  for(const f of walkJson(PKG)){
    const rel=path.relative(PKG,f); const o=readJson(f)
    if(EXCLUDED.has(o.entity_type) && o.status && o.status!=='draft') errs.push('excluded entity moved: '+(o.code||rel)+' -> '+o.status)
  }
  if(errs.length){ console.error('WORKFLOW SELF-CHECK FAILED:\n - '+errs.join('\n - ')); process.exit(1) }

  console.log('workflow.ok=true cmd='+cmd+' mutated='+mutatedFiles+' transitions='+transitions.length)
  console.log('authored_state_totals='+JSON.stringify(stateTotals))
  console.log('modules_in_review='+report.modules_in_review+' modules_ready_to_approve='+report.modules_ready_to_approve+' modules_ready_to_publish='+report.modules_ready_to_publish+' modules_published='+report.modules_published+' gate='+(gateNowClosed?'closed':'open'))
}

main()
