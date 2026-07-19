#!/usr/bin/env python3
from pathlib import Path
import json,sys
root=Path(__file__).resolve().parents[2]; p=root/'docs/hospitrainity-migration/phase-02'
files=['competencies.json','cefr-references.json','outcome-alignments.json','mapping-review.md','checkpoint-report.md','source-manifest.json']
errors=[f'missing {x}' for x in files if not (p/x).is_file()]
if not errors:
 c=json.loads((p/'competencies.json').read_text()); r=json.loads((p/'cefr-references.json').read_text()); a=json.loads((p/'outcome-alignments.json').read_text())
 cids=[x['id'] for x in c['competencies']]; rids=[x['id'] for x in r['references']]; outs=a['outcomes']; oids=[x['id'] for x in outs]
 for label,ids in [('competency',cids),('reference',rids),('outcome',oids)]:
  if len(ids)!=len(set(ids)): errors.append(f'duplicate {label} id')
 if len(cids)!=7: errors.append('expected 7 competencies')
 if len(rids)!=23: errors.append('expected 23 CEFR references')
 if len(outs)!=21 or sorted(set(x['module'] for x in outs))!=list(range(1,8)): errors.append('expected 21 outcomes across modules 1-7')
 cset=set(cids); rset=set(rids)
 for x in outs:
  if not set(x['competency_ids'])<=cset: errors.append(f'broken competency ref {x["id"]}')
  if not set(x['cefr_reference_ids'])<=rset: errors.append(f'broken CEFR ref {x["id"]}')
  if not x['evidence_conditions']: errors.append(f'missing evidence {x["id"]}')
 if r.get('status')!='draft_pending_qualified_review' or a.get('status')!='draft_pending_qualified_review': errors.append('unsafe approval status')
 if any(not x.get('verbatim_text') or not x.get('scale') or not x.get('level') for x in r['references']): errors.append('incomplete CEFR entry')
if errors:
 print('PHASE 2 VERIFICATION: FAIL'); [print('- '+e) for e in errors]; sys.exit(1)
print('PHASE 2 VERIFICATION: PASS'); print('- competencies: 7'); print('- CEFR references: 23'); print('- local outcomes: 21'); print('- reference integrity: PASS'); print('- status: draft pending qualified review')
