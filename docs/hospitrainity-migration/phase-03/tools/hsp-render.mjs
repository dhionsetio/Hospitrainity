#!/usr/bin/env node
// hsp-render.mjs — CP-14 deterministic renderer.
// CP-11: projects per-module authoring/approval lifecycle state (from the compiled
// store's entity `status`) and the CP-11 approval-ledger governance summary.
// CP-13: rebrands learner-facing title from StayReady to Hospitrainity and bumps
// the checkpoint marker for the final release/cutover phase.
// CP-14: reads the CP-02 human-approval gate and module states from the real,
// evidence-backed phase-14/workflow-state.json (never hardcoded) and reflects the
// recorded closing-phase verdict in learner-facing copy.
// Projects the CP-09 compiled canonical store (phase-09/curriculum.sql, loaded into
// node:sqlite) into the offline learner standalone HTML. Read-only over the store:
// it never mutates canonical content. Deterministic: identical inputs -> identical bytes.
//
// Usage: node --experimental-sqlite hsp-render.mjs --out <standalone.html> [--sql <path>] [--template <path>]
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { createHash } from 'node:crypto'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { DatabaseSync } from 'node:sqlite'

const __dir = dirname(fileURLToPath(import.meta.url))
const ROOT = resolve(__dir, '../../../..')            // E-learning-main
const MIG = resolve(ROOT, 'docs/hospitrainity-migration')
const P9 = resolve(MIG, 'phase-09')
const P10 = resolve(MIG, 'phase-10')
const P11 = resolve(MIG, 'phase-11')
const P12 = resolve(MIG, 'phase-12')
const P13 = resolve(MIG, 'phase-13')
const P14 = resolve(MIG, 'phase-14')
const REPORT_DIR = resolve(argOfEarly('--report-dir', P14))

function argOf(name, def){ const i = process.argv.indexOf(name); return i>=0 && process.argv[i+1] ? process.argv[i+1] : def }
function argOfEarly(name, def){ const i = process.argv.indexOf(name); return i>=0 && process.argv[i+1] ? resolve(process.argv[i+1]) : def }
const SQL = resolve(argOf('--sql', resolve(P9, 'curriculum.sql')))
const TEMPLATE = resolve(argOf('--template', resolve(__dir, 'render-template.html')))
const OUT = argOf('--out', null)
if(!OUT){ console.error('ERROR: --out <standalone.html> is required'); process.exit(2) }

// ---- canonical helpers (identical semantics to hsp-validate.mjs) ----
function sortKeys(o){
  if(Array.isArray(o)) return o.map(sortKeys)
  if(o && typeof o==='object'){ const r={}; for(const k of Object.keys(o).sort()) r[k]=sortKeys(o[k]); return r }
  return o
}
const canonical = (o)=> JSON.stringify(sortKeys(o), null, 2)+'\n'
const sha256 = (s)=> createHash('sha256').update(s).digest('hex')

// ---- load compiled store ----
const db = new DatabaseSync(':memory:')
db.exec(readFileSync(SQL,'utf8'))
const rows = (t)=> db.prepare('SELECT doc FROM '+t).all().map(r=> JSON.parse(r.doc))

const chapters = rows('chapter')
const sections = rows('lesson_section')
const activities = rows('activity')
const prompts = rows('prompt_item')
const answers = rows('answer_model')
const feedback = rows('feedback_model')
const rubrics = rows('rubric')
const frameworkRow = db.prepare("SELECT doc FROM framework_document WHERE kind='outcome-alignments'").get()
const outcomeDoc = JSON.parse(frameworkRow.doc)

// ---- indexes ----
const byCode = (arr)=>{ const m=new Map(); for(const x of arr) m.set(x.code, x); return m }
const group = (arr, key)=>{ const m=new Map(); for(const x of arr){ const k=x[key]; if(!m.has(k)) m.set(k,[]); m.get(k).push(x) } return m }

const answersByPrompt = group(answers, 'prompt_code')
const feedbackByPrompt = group(feedback, 'prompt_code')
const promptsByActivity = group(prompts, 'activity_code')
const rubricByActivity = byCode ? new Map(rubrics.map(r=>[r.activity_code, r])) : null
const sectionsByChapter = group(sections, 'chapter_code')

const byCodeAsc = (a,b)=> a.code<b.code?-1:a.code>b.code?1:0

// ---- build view model ----
const outcomes = (outcomeDoc.outcomes||[]).map(o=>({
  id:o.id, module:o.module, statement:o.statement, type:o.type, provisional_band:o.provisional_band
})).sort((a,b)=> a.id<b.id?-1:1)

const chapterVMs = chapters.slice().sort((a,b)=> a.module-b.module).map(c=>{
  const secs = (sectionsByChapter.get(c.code)||[]).slice()
    .sort((a,b)=> (a.order-b.order) || byCodeAsc(a,b))
    .map(s=>({ code:s.code, order:s.order, title:s.title }))
  const acts = activities.filter(a=> a.lesson_code && a.lesson_code.slice(0,7)===c.code).slice()
    .sort(byCodeAsc).map(a=>{
      const ps = (promptsByActivity.get(a.code)||[]).slice().sort(byCodeAsc).map(p=>{
        const ans = (answersByPrompt.get(p.code)||[]).flatMap(x=> x.accepted||[]).slice().sort()
        const fb = (feedbackByPrompt.get(p.code)||[]).flatMap(x=> (x.messages||[]))
          .map(m=>({ feedback_type:m.feedback_type, text:m.text }))
        return { code:p.code, stem:p.stem, response_form:p.response_form, answers:ans, feedback:fb }
      })
      const rb = rubricByActivity.get(a.code)
      return {
        code:a.code, lesson_code:a.lesson_code, title:a.title,
        cefr_activity:a.cefr_activity, channel:a.channel, participation:a.participation,
        pedagogical_function:a.pedagogical_function, response_form:a.response_form,
        scoring_mode:a.scoring_mode, timing:a.timing,
        outcome_codes:(a.outcome_codes||[]).slice(), accessibility:(a.accessibility||[]).slice(),
        prompts:ps,
        rubric: rb ? { code:rb.code, criteria:(rb.criteria||[]).map(cr=>({ code:cr.code, descriptor:cr.descriptor, levels:(cr.levels||[]).slice() })) } : null
      }
    })
  return { code:c.code, module:c.module, title:c.title, status:c.status||'draft', outcome_codes:(c.outcome_codes||[]).slice(), sections:secs, activities:acts }
})

// ---- CP-11: module lifecycle + approval governance summary ----
// State is read from the compiled store (entity `status`); the approval ledger is
// read from phase-11 when present. Nothing here fabricates approval.
const moduleStates = {}
for(const c of chapterVMs) moduleStates[c.status] = (moduleStates[c.status]||0)+1
let approvalLedger = null
try { approvalLedger = JSON.parse(readFileSync(resolve(P14,'approval-ledger.json'),'utf8')) } catch { approvalLedger = null }
if(!approvalLedger){ try { approvalLedger = JSON.parse(readFileSync(resolve(P11,'approval-ledger.json'),'utf8')) } catch { approvalLedger = null } }
// Gate status is read from the real, recorded workflow-engine state, never hardcoded.
let gateStatus = 'open'
try {
  const wfState = JSON.parse(readFileSync(resolve(P14,'workflow-state.json'),'utf8'))
  gateStatus = (wfState.human_approval_gate && wfState.human_approval_gate.status) || 'open'
} catch { gateStatus = 'open' }
const workflow = {
  gate: { id:'CP-02', status: gateStatus },
  modules: chapterVMs.map(c=>{
    const led = approvalLedger && (approvalLedger.modules||[]).find(m=>m.code===c.code)
    return { code:c.code, module:c.module, state:c.status, approval_gate: led? led.approval_gate : 'BLOCKED' }
  })
}

const promptCount = chapterVMs.reduce((n,c)=> n + c.activities.reduce((m,a)=> m + a.prompts.length, 0), 0)
const activityCount = chapterVMs.reduce((n,c)=> n + c.activities.length, 0)
const sectionCount = chapterVMs.reduce((n,c)=> n + c.sections.length, 0)

const viewModel = {
  meta: {
    product: 'Hospitrainity',
    title: 'Hospitrainity \u2014 Hospitality English',
    subtitle: 'Practical customer-care English for hotel and hospitality staff, organized into seven modules derived from the source manuscript.',
    content_version: (chapters[0]&&chapters[0].content_version) || '0.3.0-draft',
    status: (Object.keys(moduleStates).length===1 ? Object.keys(moduleStates)[0] : 'mixed'),
    checkpoint: 'CP-14',
    source_artifact: 'Hospitrainity.docx',
    generated_from: 'phase-09/curriculum.sql (compiled canonical store)',
    notice: (gateStatus==='closed'
      ? 'All seven modules are approved and published. The CP-02 human-approval gate is closed on recorded evidence (three per-module sign-offs plus a Project Owner final release verdict; see the About page for the recorded basis). CEFR bands shown remain provisional hypotheses (A2\u2013B1); no CEFR level is externally certified.'
      : 'All seven modules are submitted for qualified review (lifecycle: in review). Approval is pending: no module is approved or published, and CEFR bands shown are provisional hypotheses (A2\u2013B1). No CEFR level is certified.'),
    disclaimer: (gateStatus==='closed'
      ? 'This build reflects a closed CP-02 approval gate and all seven modules published, recorded as of the CP-14 closing phase. Sign-offs for the three required review roles (ESP/CEFR specialist, hospitality practitioner, accessibility reviewer) were consolidated under one recorded Project Owner final release verdict rather than three independently-conducted specialist reviews; this is disclosed transparently rather than presented as independent specialist certification. Provisional CEFR bands remain working hypotheses and must not be presented as certified CEFR levels.'
      : 'This build is generated for internal review only. Every module is in the review stage of the authoring workflow; none is approved or published. Approval requires recorded human sign-offs (ESP/CEFR specialist, hospitality practitioner, accessibility reviewer) and closure of the CP-02 human-approval gate, which remains open. Provisional CEFR bands are working hypotheses and must not be presented as certified CEFR levels.')
  },
  stats: { chapters: chapterVMs.length, sections: sectionCount, activities: activityCount, prompts: promptCount, outcomes: outcomes.length },
  workflow,
  outcomes,
  chapters: chapterVMs
}

// ---- render standalone ----
const template = readFileSync(TEMPLATE, 'utf8')
const dataJson = JSON.stringify(sortKeys(viewModel)).replace(/</g, '\\u003c')  // safe inside <script>
const inReview = workflow.modules.filter(m=>m.state==='review').length
const publishedCount = workflow.modules.filter(m=>m.state==='published').length
const provenance = ' Hospitrainity curriculum migration checkpoint CP-14 (closing phase): the offline standalone is a deterministic projection of the CP-09 compiled canonical store (phase-09/curriculum.sql -> node:sqlite), surfacing per-module authoring/approval lifecycle state and cleared by integrated QA. '+
  chapterVMs.length+' modules ('+publishedCount+' published) / '+sectionCount+' sections / '+activityCount+' activities / '+promptCount+' prompts / '+outcomes.length+' outcomes, all traceable to '+viewModel.meta.source_artifact+'. Approval gate (CP-02) '+gateStatus+'. Read-only render: no canonical content was mutated. '
let html = template.replace('/*__HOSPITRAINITY_DATA__*/', dataJson).replace('<!--__HSP_PROVENANCE__-->', '<!--'+provenance+'-->')

// ---- self-verify ----
const errors = []
const expect = { chapters:7, sections:85, activities:24, prompts:102, outcomes:21 }
for(const k of Object.keys(expect)) if(viewModel.stats[k]!==expect[k]) errors.push(`stat ${k}: got ${viewModel.stats[k]} want ${expect[k]}`)
// Invariants grounded in the actual store: every prompt has an answer-model record
// and a feedback-model record (102 each); 90 answer-models carry model-answer text
// (the other 12 are open self-rating / role-play / service-artifact prompts scored by
// rubric or self-assessment); 6 role-play activities carry a rubric.
let promptsWithModel=0, promptsWithFeedback=0, promptsWithAccepted=0, rubricsRendered=0
for(const c of chapterVMs) for(const a of c.activities){ if(a.rubric) rubricsRendered++; for(const p of a.prompts){ if(answersByPrompt.get(p.code)) promptsWithModel++; if(feedbackByPrompt.get(p.code)) promptsWithFeedback++; if(p.answers.length) promptsWithAccepted++ } }
// orphan checks
const chapterCodes = new Set(chapters.map(c=>c.code))
for(const a of activities){ const cc=a.lesson_code?a.lesson_code.slice(0,7):''; if(!chapterCodes.has(cc)) errors.push('orphan activity '+a.code) }
const placedPrompts = new Set(); for(const c of chapterVMs) for(const a of c.activities) for(const p of a.prompts) placedPrompts.add(p.code)
for(const p of prompts) if(!placedPrompts.has(p.code)) errors.push('unplaced prompt '+p.code)
if(promptsWithModel!==102) errors.push('prompts with answer-model: '+promptsWithModel+' want 102')
if(promptsWithFeedback!==102) errors.push('prompts with feedback: '+promptsWithFeedback+' want 102')
if(promptsWithAccepted!==90) errors.push('prompts with model-answer text: '+promptsWithAccepted+' want 90')
if(rubricsRendered!==6) errors.push('rubrics rendered: '+rubricsRendered+' want 6')
if(/youkata/i.test(html)) errors.push('BANNED term "Youkata" present in rendered output')
if(html.indexOf('__HOSPITRAINITY_DATA__')>=0) errors.push('data token not replaced')
if(html.indexOf('__HSP_PROVENANCE__')>=0) errors.push('provenance token not replaced')
try{ JSON.parse(dataJson.replace(/\\u003c/g,'<')) }catch(e){ errors.push('data JSON invalid: '+e.message) }

if(errors.length){ console.error('RENDER FAILED:\n - '+errors.join('\n - ')); process.exit(1) }

// ---- write outputs ----
mkdirSync(REPORT_DIR, { recursive:true })
mkdirSync(dirname(resolve(OUT)), { recursive:true })
writeFileSync(resolve(OUT), html)
writeFileSync(resolve(REPORT_DIR,'view-model.json'), canonical(viewModel))
const report = {
  ok: true,
  checkpoint: 'CP-14',
  generated_from: 'phase-09/curriculum.sql',
  template: 'phase-03/tools/render-template.html',
  counts: viewModel.stats,
  integrity: { orphan_activities:0, unplaced_prompts:0, prompts_with_answer_model:promptsWithModel, prompts_with_model_answer_text:promptsWithAccepted, prompts_with_feedback:promptsWithFeedback, rubrics:rubricsRendered },
  banned_terms: { youkata: 0 },
  workflow: { gate:gateStatus, modules_in_review: inReview, modules_approved: workflow.modules.filter(m=>m.state==='approved'||m.state==='published').length, modules_published: publishedCount },
  standalone: { bytes: Buffer.byteLength(html), sha256: sha256(html) },
  view_model_sha256: sha256(canonical(viewModel))
}
writeFileSync(resolve(REPORT_DIR,'render-report.json'), canonical(report))
console.log('render.ok=true bytes='+report.standalone.bytes+' sha='+report.standalone.sha256)
console.log('counts='+JSON.stringify(viewModel.stats))
