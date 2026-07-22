<?php

namespace Tests\Feature;

use App\Enums\InstitutionRole;
use App\Enums\WorkContextRole;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Services\AgentContextRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentContextApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-agent-context-token-that-is-long-enough-123456789';

    public function test_api_is_not_advertised_while_disabled(): void
    {
        config([
            'agent_context.enabled' => false,
            'agent_context.token' => self::TOKEN,
        ]);

        $this->getJson('/api/v1/agent-context')
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Not found.',
                ],
            ])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_enabled_api_refuses_an_invalid_server_token_configuration(): void
    {
        config([
            'agent_context.enabled' => true,
            'agent_context.token' => 'too-short',
        ]);

        $this->getJson('/api/v1/agent-context')
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'agent_context_not_configured');
    }

    public function test_api_requires_the_exact_bearer_token(): void
    {
        $this->enableApi();

        $this->getJson('/api/v1/agent-context')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="Hospitrainity Agent Context"')
            ->assertJsonPath('error.code', 'invalid_bearer_token');

        $this->withToken(str_repeat('x', 40))
            ->getJson('/api/v1/agent-context')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_bearer_token');

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context')
            ->assertOk();
    }

    public function test_default_document_is_compact_discoverable_and_derived_from_live_contracts(): void
    {
        $this->enableApi();

        $response = $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Agent-Context-Version', AgentContextRepository::VERSION)
            ->assertJsonPath('meta.id', 'hospitrainity-agent-context')
            ->assertJsonPath('meta.api_version', AgentContextRepository::VERSION)
            ->assertJsonPath('meta.contract', 'read_only')
            ->assertJsonPath('meta.scope', 'non_user_specific')
            ->assertJsonPath('meta.included', ['product', 'domain', 'capabilities'])
            ->assertJsonPath('links.openapi', '/api/v1/agent-context/openapi')
            ->assertJsonPath('sections.domain.roles.work_context_roles', WorkContextRole::values())
            ->assertJsonPath('sections.domain.roles.institution_roles', InstitutionRole::values())
            ->assertJsonPath('sections.capabilities.exercise_contract.enabled_count', 12)
            ->assertJsonPath('sections.capabilities.exercise_contract.total_count', 14)
            ->assertJsonPath('sections.capabilities.exercise_contract.templates.spelling_quiz.enabled', false)
            ->assertJsonMissingPath('sections.curriculum')
            ->assertJsonCount(count(AgentContextRepository::SECTIONS), 'available_sections');

        $encoded = $response->getContent();
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('APP_KEY', $encoded);
        $this->assertStringNotContainsString('VAPID_PRIVATE_KEY', $encoded);
    }

    public function test_clients_can_request_selected_all_or_focused_sections(): void
    {
        $this->enableApi();

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context?include=workflows,glossary')
            ->assertOk()
            ->assertJsonPath('meta.included', ['workflows', 'glossary'])
            ->assertJsonStructure(['sections' => ['workflows', 'glossary']])
            ->assertJsonMissingPath('sections.product');

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context?include=all')
            ->assertOk()
            ->assertJsonPath('meta.included', AgentContextRepository::SECTIONS)
            ->assertJsonCount(count(AgentContextRepository::SECTIONS), 'sections');

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context/architecture')
            ->assertOk()
            ->assertJsonPath('meta.included', ['architecture'])
            ->assertJsonPath('links.self', '/api/v1/agent-context/architecture')
            ->assertJsonStructure(['sections' => ['architecture' => ['runtime', 'layers', 'sources_of_truth']]])
            ->assertJsonCount(1, 'sections');
    }

    public function test_unknown_include_and_section_are_rejected(): void
    {
        $this->enableApi();

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context?include=product,secrets')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('include');

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context/secrets')
            ->assertNotFound();
    }

    public function test_openapi_document_describes_security_and_every_section(): void
    {
        $this->enableApi();

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context/openapi')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.version', AgentContextRepository::VERSION)
            ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer')
            ->assertJsonPath(
                'paths./api/v1/agent-context/{section}.get.parameters.0.schema.enum',
                AgentContextRepository::SECTIONS,
            );
    }

    public function test_curriculum_section_returns_only_a_safe_deliverable_outline(): void
    {
        $this->enableApi();
        config(['curriculum.release.allow_draft_active_preview' => true]);

        $package = CurriculumPackage::query()->create([
            'package_name' => 'hospitrainity',
            'content_version' => 'test-draft',
            'schema_version' => '2.1.0',
            'namespace_uuid' => (string) Str::uuid(),
            'lifecycle_status' => 'draft',
            'source_path' => 'private-test-source',
            'source_tree_sha256' => str_repeat('a', 64),
            'source_file_count' => 1,
            'source_byte_count' => 100,
            'counts' => [],
            'projection_meta' => [],
            'laravel_projection_sha256' => str_repeat('b', 64),
            'standalone_sha256' => str_repeat('c', 64),
            'is_active' => true,
            'imported_at' => now(),
        ]);

        $this->entity($package, 'TEST-C01', 'chapter', null, 1, ['title' => 'Guest Service', 'module' => 1]);
        $this->entity($package, 'TEST-C01-S01', 'lesson-section', 'TEST-C01', 1, ['title' => 'Welcoming a guest']);
        $this->entity($package, 'TEST-C01-A01', 'activity', 'TEST-C01-S01', 1, [
            'title' => 'Choose a polite greeting',
            'response_form' => 'selection',
            'channel' => 'written',
            'participation' => 'individual',
        ]);
        $this->entity($package, 'TEST-C01-P01', 'prompt-item', 'TEST-C01-A01', 1, [
            'title' => 'Prompt metadata',
            'stem' => 'CONFIDENTIAL PROMPT BODY',
            'choices' => [['id' => 'secret-choice', 'text' => 'CONFIDENTIAL ANSWER']],
        ]);

        $response = $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context/curriculum')
            ->assertOk()
            ->assertJsonPath('sections.curriculum.available', true)
            ->assertJsonPath('sections.curriculum.status', 'deliverable')
            ->assertJsonPath('sections.curriculum.package.content_version', 'test-draft')
            ->assertJsonPath('sections.curriculum.package.preview_only', true)
            ->assertJsonPath('sections.curriculum.outline.0.code', 'TEST-C01')
            ->assertJsonPath('sections.curriculum.outline.0.sections.0.activities.0.code', 'TEST-C01-A01')
            ->assertJsonPath('sections.curriculum.published_entity_counts.prompt-item', 1);

        $encoded = $response->getContent();
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('CONFIDENTIAL PROMPT BODY', $encoded);
        $this->assertStringNotContainsString('CONFIDENTIAL ANSWER', $encoded);
        $this->assertStringNotContainsString('private-test-source', $encoded);
        $this->assertStringNotContainsString(str_repeat('a', 64), $encoded);
    }

    public function test_curriculum_section_reports_absence_without_fabricating_content(): void
    {
        $this->enableApi();

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent-context/curriculum')
            ->assertOk()
            ->assertJsonPath('sections.curriculum.available', false)
            ->assertJsonPath('sections.curriculum.status', 'no_active_package')
            ->assertJsonMissingPath('sections.curriculum.outline');
    }

    private function enableApi(): void
    {
        config([
            'agent_context.enabled' => true,
            'agent_context.token' => self::TOKEN,
            'agent_context.minimum_token_length' => 32,
            'agent_context.rate_limit_per_minute' => 30,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function entity(
        CurriculumPackage $package,
        string $code,
        string $type,
        ?string $parent,
        int $position,
        array $payload,
    ): CurriculumEntity {
        return CurriculumEntity::query()->create([
            'curriculum_package_id' => $package->getKey(),
            'entity_uuid' => (string) Str::uuid(),
            'code' => $code,
            'entity_type' => $type,
            'parent_code' => $parent,
            'position' => $position,
            'lifecycle_status' => 'published',
            'content_version' => $package->content_version,
            'source_path' => "entities/{$code}.json",
            'source_sha256' => hash('sha256', $code),
            'payload' => $payload,
        ]);
    }
}
