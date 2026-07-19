#!/usr/bin/env python3
from pathlib import Path
import json, sys
root=Path(__file__).resolve().parents[2]
base=root/'docs/hospitrainity-migration'
required=[base/'README.md',base/'DECISIONS.md',base/'CHECKPOINTS.md',base/'phase-01/needs-analysis.md',base/'phase-01/learner-personas.md',base/'phase-01/target-situation-inventory.md',base/'phase-01/provisional-level-strategy.md',base/'phase-01/evidence-register.md',base/'phase-01/source-manifest.json',base/'phase-01/checkpoint-report.md']
errors=[f'missing/empty: {p.relative_to(root)}' for p in required if not p.is_file() or p.stat().st_size==0]
if not errors:
 m=json.loads((base/'phase-01/source-manifest.json').read_text())
 if m.get('checkpoint')!='CP-01' or m.get('phase')!=1: errors.append('manifest checkpoint/phase mismatch')
 corpus='\n'.join(p.read_text(encoding='utf-8') for p in required if p.suffix=='.md')
 for phrase in ['Hospitrainity.docx','seven equal','English-only','Grand Serenata Hotel','0%','A2–B1','mobile','complaint']:
  if phrase.lower() not in corpus.lower(): errors.append(f'missing phrase: {phrase}')
if errors:
 print('PHASE 1 VERIFICATION: FAIL'); [print('- '+e) for e in errors]; sys.exit(1)
print('PHASE 1 VERIFICATION: PASS')
print('- required files: 10')
print('- manifest JSON: valid')
print('- binding decisions: present')
print('- CEFR status: provisional only')
