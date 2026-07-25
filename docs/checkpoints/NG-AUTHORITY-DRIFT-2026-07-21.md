# Authority drift checkpoint — 2026-07-21

State: CONFIRMED source drift, re-audited before UI implementation  
Owner notification: reported during the active Codex task  
Data mutation: none

## Hash verification

| Authority file | Roadmap hash before check | Current hash | Result |
|---|---|---|---|
| Thesis DOCX | `C38AC625A2F7A34BB004E6B68183D748BC6C1D3C1C9896C3F791909072496D34` | `604BF99BE839E8CD6A6B0991BE878DE759D514A736A5DF235BD0B961551AC7E8` | Changed |
| Learning-material DOCX | `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4` | same | Unchanged |

The thesis file was open in Microsoft Word. Three consecutive shared-read SHA-256 calculations produced the same current hash. The file was not closed, copied over, or modified by this task.

## Re-audit result

The complete current thesis text was reread. The UI and product boundaries relevant to the active work remain:

- primary research users are pre-service learners in the English for Business and Professional Communication and English for Tourism Industry programs at State Polytechnic of Malang;
- the platform should be usable on desktop and mobile;
- the UI/UX should support easy navigation, engagement, and learners with limited digital literacy;
- learning should use authentic hospitality scenarios, short practice, feedback, assessment, progress, role play, and audiovisual components;
- communicative confidence and perceived job readiness are self-reported constructs, not workplace-performance proof;
- hotel names are illustrative scenario sources, not study sites;
- Chapter IV still contains no completed findings or discussion;
- the thesis still conflicts between an “AI integration” scope statement and a product-specification statement that a chatbot was “considered.” AI therefore remains decision-gated and cannot be inferred as a current feature.

No newly read thesis statement authorizes an institution/course/class schema, assignment, gradebook, mastery claim, gamification, public outcome claim, or production release.

## Consequence

The source audit was repeated as required before implementation. The master roadmap hash is updated to the current thesis file. Batch order and the B06 questionnaire boundary do not change. The learning-material hash and canonical learning-content authority remain unchanged.

