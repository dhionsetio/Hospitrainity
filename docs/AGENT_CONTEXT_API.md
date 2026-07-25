# Hospitrainity Agent Context API

## Purpose and boundary

The Agent Context API gives another software agent a compact, machine-readable description of Hospitrainity without copying the repository into its prompt. It is a read-only integration surface, not a learner or administration API.

The API can describe the product, domain model, actor capabilities, workflows, architecture, current deliverable curriculum outline, live named routes, and glossary. It never returns credentials, environment secrets, personal data, learner responses or progress, draft curriculum payloads, model answers, release evidence, controller source, or database rows belonging to users.

All endpoints are under `/api/v1/agent-context`. They use JSON, accept only `GET`, are rate-limited, send `Cache-Control: no-store, private`, and require the same opaque bearer token.

## Enable it

Generate a deployment-specific random token. This example prints a 64-character token and does not write it to a file:

```powershell
& 'C:\php\php.exe' -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Supply the result through the deployment secret store or untracked local `.env`:

```dotenv
AGENT_CONTEXT_API_ENABLED=true
AGENT_CONTEXT_API_TOKEN=<random value of at least 32 characters>
AGENT_CONTEXT_API_RATE_LIMIT=30
```

After changing configuration in an environment that caches Laravel config, rebuild or clear that cache through the deployment's normal process. Never commit the populated token. Leave the API disabled in deployments that do not need an agent integration.

The server returns:

- `404` while the API is disabled, so an unused integration surface is not advertised;
- `503` if it is enabled without a token of at least 32 characters;
- `401` with `WWW-Authenticate: Bearer` for a missing or incorrect token;
- `429` when the configured per-minute limit is exceeded.

## Requests

PowerShell example:

```powershell
$agentHeaders = @{ Authorization = "Bearer $env:AGENT_CONTEXT_API_TOKEN" }
Invoke-RestMethod -Uri 'http://localhost:8000/api/v1/agent-context' -Headers $agentHeaders
```

The index includes `product`, `domain`, and `capabilities` by default. Request any comma-separated set, or all sections:

```text
GET /api/v1/agent-context?include=product,workflows,curriculum
GET /api/v1/agent-context?include=all
```

Request one focused section when token budget matters:

```text
GET /api/v1/agent-context/product
GET /api/v1/agent-context/domain
GET /api/v1/agent-context/capabilities
GET /api/v1/agent-context/workflows
GET /api/v1/agent-context/architecture
GET /api/v1/agent-context/curriculum
GET /api/v1/agent-context/routes
GET /api/v1/agent-context/glossary
```

The machine-readable contract is available from:

```text
GET /api/v1/agent-context/openapi
```

Unknown `include` values produce `422`. Unknown focused section paths produce `404`.

## Response shape

Every context response has the same envelope:

```json
{
  "meta": {
    "id": "hospitrainity-agent-context",
    "api_version": "1.0.0",
    "contract": "read_only",
    "scope": "non_user_specific",
    "included": ["product"]
  },
  "links": {
    "self": "/api/v1/agent-context/product",
    "index": "/api/v1/agent-context",
    "openapi": "/api/v1/agent-context/openapi"
  },
  "available_sections": [],
  "sections": {
    "product": {}
  }
}
```

`meta.data_excluded` is part of the actual response and states the privacy/content boundary explicitly. `available_sections` provides discovery metadata and focused endpoint links.

### Section semantics

| Section | Source and intended use |
|---|---|
| `product` | Curated stable product purpose, audiences, modes, and non-goals. |
| `domain` | Live enum values plus curated relationships and authorization invariants. |
| `capabilities` | Actor capability summary plus the live canonical exercise-template registry. |
| `workflows` | Ordered, guarded user and administration workflows. |
| `architecture` | Runtime configuration names, layers, sources of truth, storage boundaries, and dependencies; never configuration values that are secrets. |
| `curriculum` | Live outline of the active package after the same delivery guard used by learner pages. Only published navigation-level metadata is included. |
| `routes` | Live named Laravel routes with HTTP methods and access requirements inferred from registered middleware. Controller code and request payloads are excluded. |
| `glossary` | Canonical definitions for terms that agents commonly conflate. |

The curriculum section fails closed. It reports `storage_not_initialized`, `no_active_package`, or `delivery_refused` instead of bypassing curriculum-release safeguards. A successful outline omits prompt bodies, choices, correct answers, feedback models, rubrics, source locators, checksums, and learner evidence.

## Recommended agent usage

Start with the default index. Fetch a focused section only when the task needs it:

- feature reasoning: `product`, `domain`, and `capabilities`;
- user-flow changes: `workflows` and `routes`;
- infrastructure reasoning: `architecture`;
- curriculum navigation or naming: `curriculum`;
- terminology: `glossary`.

Treat the API as context, not authorization. Route summaries explicitly warn that record policies and service guards can be stricter than route middleware. An agent must not infer that a route is callable merely because it appears in the inventory.

## Portable ZIP for another agent

Create an offline context-only archive when the receiving agent cannot call the live API or should not receive repository access:

```powershell
& 'C:\php\php.exe' scripts\operations\export-agent-context.php
```

The command writes a timestamped, verified ZIP to `storage/app/private/agent-context-exports`. It contains static JSON equivalents of every context endpoint, the OpenAPI document, receiving-agent instructions, a materials-change guide, a change-request template, and a SHA-256 manifest. It includes no application source, credentials, personal/learner data, prompt bodies, or answer keys.

Give the ZIP to the receiving agent and instruct it to begin with `README.md`. Provide the exact target lesson, prompt, answer, or rubric separately whenever the requested adjustment depends on content outside the safe curriculum outline.

## Validation checklist

Run the focused automated contract tests:

```powershell
& 'C:\php\php.exe' artisan test --filter=AgentContextApiTest
```

Then verify the registered surface and formatting:

```powershell
& 'C:\php\php.exe' artisan route:list --path=api/v1/agent-context
& 'C:\php\php.exe' vendor\bin\pint --test
```

For a manual check, enable the API with a disposable local token, start Laravel, and verify the four security states: disabled `404`, missing token `401`, wrong token `401`, and correct token `200`. Confirm that a correct response contains no email addresses, user IDs, response payloads, draft payloads, source paths, hashes, or secrets.

When adding a role, workflow, lifecycle enum, or canonical exercise type, update the curated descriptions and extend `AgentContextApiTest`. Live enum, exercise-registry, route, and deliverable-curriculum fields will otherwise follow their application sources automatically.
