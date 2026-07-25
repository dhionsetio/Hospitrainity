# Hospitrainity Portable Agent Context

This archive is a context-only snapshot for an AI agent or authorized collaborator. It explains what Hospitrainity is, what the website contains, its goals, roles, features, workflows, architecture boundaries, current curriculum outline, and important terminology without providing the application source.

Context API version: `1.0.0`  
Curriculum snapshot: `0.4.0-draft` (`deliverable`)

## Start here

1. Read `AGENT_INSTRUCTIONS.md`.
2. Read `api/v1/agent-context/all.json` for the complete context bundle.
3. For a material adjustment, also read `MATERIALS_CHANGE_GUIDE.md` and use `CHANGE_REQUEST_TEMPLATE.md`.
4. Consult individual files under `api/v1/agent-context/` when a smaller context window is preferable.
5. Use `MANIFEST.json` to verify every packaged file.

## Portable API layout

The JSON files mirror the live read-only context API:

- `index.json`: compact default context—product, domain, and capabilities.
- `all.json`: all context sections in one document.
- `product.json`: purpose, audiences, delivery modes, and non-goals.
- `domain.json`: roles, scopes, entities, lifecycles, and invariants.
- `capabilities.json`: actor features and exercise contracts.
- `workflows.json`: registration, enrollment, learning, authoring, release, and privacy flows.
- `architecture.json`: runtime layers, sources of truth, storage, and security boundaries.
- `curriculum.json`: safe navigation-level outline of the currently deliverable package.
- `routes.json`: named web/API surfaces and their access requirements.
- `glossary.json`: canonical project terminology.
- `openapi.json`: machine-readable contract for the equivalent live API.

These are static files, so they require no server or bearer token after extraction.

## What is deliberately absent

This archive contains no PHP, Blade, JavaScript, CSS, SQL, configuration secrets, credentials, user records, institution records, learner attempts, progress, free-form responses, private exports, raw draft payloads, prompt bodies, model answers, answer keys, checksums from curriculum source files, or release evidence.

When an agent must revise a specific lesson, section, prompt, or answer, provide that target material separately. The archive supplies the surrounding product and governance context; it does not silently substitute an incomplete copy for the authoritative material.

## Handling

Treat this as private project context. It is safer than sharing the repository, but the route inventory and product architecture are still non-public project information. Share it only with an agent or collaborator authorized for the requested work.

The context is a snapshot. Regenerate the ZIP after material, feature, role, workflow, route, or curriculum-release changes.
