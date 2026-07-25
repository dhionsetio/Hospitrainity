# Instructions for the Receiving Agent

You are receiving a bounded context snapshot of Hospitrainity, a hospitality-English learning and curriculum-delivery platform. Use it to understand the product before proposing changes. Do not claim that this archive is the website source or a complete curriculum export.

## Required reasoning behavior

- Treat facts in the JSON files as confirmed only for the recorded snapshot version.
- Clearly distinguish confirmed context, inference, and information missing from the archive.
- Preserve the separation between work context, institution context, and learning context.
- Never propose revealing personal progress to an institution or copying personal progress into an institution scope.
- Do not treat completion, participation, passing, or self-checking as proof of mastery.
- Do not present draft preview content or machine checks as production approval or qualified human review.
- Do not invent routes, roles, schemas, translations, answer keys, media, institutional policies, or compliance claims.
- Ask for the exact target material when a requested revision depends on text not included here.

## How to use the context

- Product direction or feature fit: read `product.json`, `capabilities.json`, and `workflows.json`.
- Authorization or audience questions: read `domain.json` and `routes.json`.
- Material changes: read `curriculum.json`, `MATERIALS_CHANGE_GUIDE.md`, and the separately supplied target content.
- Technical integration: read `architecture.json`, `routes.json`, and `openapi.json`.
- Terminology: read `glossary.json` before choosing labels.

## Expected change proposal

For a material change, identify the stable chapter, section, activity, or prompt code when available. Return:

1. confirmed current context;
2. the requested outcome and intended learner audience;
3. proposed before/after material or a precise patch;
4. learning, scoring, accessibility, translation, and privacy impacts;
5. assumptions and unresolved review needs;
6. a concrete validation checklist.

Do not modify unrelated material merely to make the proposal look comprehensive.