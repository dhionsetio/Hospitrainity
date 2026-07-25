<?php

namespace App\Services;

use App\Enums\CurriculumDraftStatus;
use App\Enums\CurriculumReleaseState;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use App\Enums\InstitutionJoinRequestStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Enums\PlatformRole;
use App\Enums\UserCapability;
use App\Enums\WorkContextRole;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\CurriculumRelease;
use App\Services\Curriculum\CanonicalExerciseTemplateRegistry;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class AgentContextRepository
{
    public const VERSION = '1.0.0';

    public const SECTIONS = [
        'product',
        'domain',
        'capabilities',
        'workflows',
        'architecture',
        'curriculum',
        'routes',
        'glossary',
    ];

    private const DEFAULT_SECTIONS = ['product', 'domain', 'capabilities'];

    public function __construct(
        private readonly CanonicalExerciseTemplateRegistry $exerciseTemplates,
    ) {}

    /** @return list<string> */
    public function resolveIncludes(?string $include): array
    {
        if ($include === null || trim($include) === '') {
            return self::DEFAULT_SECTIONS;
        }

        $requested = array_values(array_unique(array_filter(
            array_map('trim', explode(',', strtolower($include))),
            static fn (string $section): bool => $section !== '',
        )));

        if ($requested === ['all']) {
            return self::SECTIONS;
        }

        $unknown = array_values(array_diff($requested, self::SECTIONS));
        if ($requested === [] || $unknown !== []) {
            $allowed = implode(', ', array_merge(self::SECTIONS, ['all']));
            throw new InvalidArgumentException("Unknown section. Allowed values: {$allowed}.");
        }

        return $requested;
    }

    /**
     * @param  list<string>  $included
     * @return array<string, mixed>
     */
    public function document(array $included): array
    {
        $sections = [];
        foreach ($included as $section) {
            $sections[$section] = $this->section($section);
        }

        $focused = count($included) === 1;

        return [
            'meta' => [
                'id' => 'hospitrainity-agent-context',
                'api_version' => self::VERSION,
                'contract' => 'read_only',
                'scope' => 'non_user_specific',
                'included' => $included,
                'data_excluded' => [
                    'credentials_and_secrets',
                    'personal_data',
                    'learner_responses_and_progress',
                    'draft_curriculum_payloads',
                    'model_answers_and_release_evidence',
                ],
            ],
            'links' => [
                'self' => $focused
                    ? route('api.agent-context.show', ['section' => $included[0]], false)
                    : route('api.agent-context.index', absolute: false),
                'index' => route('api.agent-context.index', absolute: false),
                'openapi' => route('api.agent-context.openapi', absolute: false),
                'focused_section_template' => '/api/v1/agent-context/{section}',
            ],
            'available_sections' => $this->catalog(),
            'sections' => $sections,
        ];
    }

    /** @return array<string, mixed> */
    public function section(string $section): array
    {
        return match ($section) {
            'product' => $this->product(),
            'domain' => $this->domain(),
            'capabilities' => $this->capabilities(),
            'workflows' => $this->workflows(),
            'architecture' => $this->architecture(),
            'curriculum' => $this->curriculum(),
            'routes' => $this->routes(),
            'glossary' => $this->glossary(),
            default => throw new InvalidArgumentException("Unknown agent context section: {$section}."),
        };
    }

    /** @return list<array{key: string, description: string, endpoint: string}> */
    public function catalog(): array
    {
        $descriptions = [
            'product' => 'Purpose, audiences, supported experiences, and explicit non-goals.',
            'domain' => 'Roles, scopes, lifecycle states, entity relationships, and invariants.',
            'capabilities' => 'What each actor can do and which exercise contracts are available.',
            'workflows' => 'Ordered registration, enrollment, learning, authoring, release, and privacy flows.',
            'architecture' => 'Runtime layers, sources of truth, storage boundaries, and operational dependencies.',
            'curriculum' => 'Safe outline and counts from the currently deliverable curriculum package.',
            'routes' => 'Live named-route inventory with inferred access requirements and no controller source.',
            'glossary' => 'Short, canonical definitions for commonly confused Hospitrainity terms.',
        ];

        return array_map(static fn (string $section): array => [
            'key' => $section,
            'description' => $descriptions[$section],
            'endpoint' => route('api.agent-context.show', ['section' => $section], false),
        ], self::SECTIONS);
    }

    /** @return array<string, mixed> */
    private function product(): array
    {
        return [
            'name' => 'Hospitrainity',
            'kind' => 'hospitality English learning and curriculum delivery platform',
            'purpose' => 'Help learners practise workplace English while keeping personal and institution-attributed learning evidence separate.',
            'primary_audiences' => [
                'learner' => 'Studies personally or through an approved institution membership.',
                'instructor' => 'Supports institution learners and sees only institution-attributed progress.',
                'institution_admin' => 'Manages institution-scoped instructor authority and enrollment.',
                'content_author' => 'Authors and reviews shared canonical curriculum.',
                'system_admin' => 'Operates platform-wide identity, evidence, privacy, and release controls.',
            ],
            'supported_locales' => ['en', 'id'],
            'content_language' => 'Canonical learning content remains English unless an approved translation exists.',
            'delivery_modes' => [
                'responsive authenticated web application',
                'generated offline standalone curriculum projection',
            ],
            'core_properties' => [
                'versioned checksum-locked canonical curriculum',
                'verified-account learner delivery',
                'scope-separated progress',
                'institution-scoped supervision',
                'reviewed curriculum publication lifecycle',
                'retained legacy content as read-only evidence while canonical delivery is active',
            ],
            'explicit_non_goals' => [
                'public institution directory',
                'automatic institution enrollment from an email domain',
                'copying personal progress into an institution scope',
                'content marketplace or general-purpose LMS interoperability',
                'production claims not backed by recorded release, legal, or deployment evidence',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function domain(): array
    {
        return [
            'roles' => [
                'work_context_roles' => WorkContextRole::values(),
                'institution_roles' => InstitutionRole::values(),
                'platform_roles' => PlatformRole::values(),
                'separate_capabilities' => UserCapability::values(),
                'authority_model' => [
                    'learner' => 'May access learning only in a currently valid personal or institution learning scope.',
                    'instructor' => 'Institution-scoped; may see only learners and progress attributed to the selected institution.',
                    'institution_admin' => 'Institution-scoped; includes instructor functions and may grant or revoke instructor access in that institution.',
                    'content_author' => 'Shared curriculum authority; grants no enrollment or platform authority.',
                    'system_admin' => 'Global platform authority; privileged operations require stronger assurance and may require a recent password.',
                ],
            ],
            'context_dimensions' => [
                'work_context' => 'The one active role used to resolve navigation and authorization for the request.',
                'institution_context' => 'The revalidated active institution used by institution-scoped staff actions.',
                'learning_context' => 'The personal or institution scope that receives new learning progress.',
            ],
            'core_entities' => [
                'identity' => ['user', 'platform_role_assignment', 'user_capability_assignment'],
                'tenancy' => ['institution', 'institution_membership', 'institution_role_assignment'],
                'enrollment' => ['institution_invitation', 'institution_join_code', 'institution_join_request'],
                'curriculum' => ['curriculum_package', 'curriculum_entity', 'curriculum_release', 'curriculum_draft'],
                'learning_evidence' => ['curriculum_attempt', 'curriculum_response', 'curriculum_activity_progress'],
                'privacy_and_security' => ['data_subject_request', 'data_export', 'security_event', 'identity_audit'],
            ],
            'relationships' => [
                'A user can have multiple institution memberships, but progress belongs to exactly one learning scope.',
                'An institution membership can have role assignments; a membership alone does not imply staff authority.',
                'A curriculum package owns versioned entities and has at most one release record.',
                'An activity belongs to a lesson section; a lesson section belongs to a chapter.',
                'Attempts and progress retain package name, content version, user, and learning-scope identity.',
            ],
            'lifecycle_states' => [
                'institution' => InstitutionStatus::values(),
                'membership' => InstitutionMembershipStatus::values(),
                'join_request' => InstitutionJoinRequestStatus::values(),
                'curriculum_draft' => array_column(CurriculumDraftStatus::cases(), 'value'),
                'curriculum_release' => CurriculumReleaseState::values(),
                'privacy_request' => DataSubjectRequestStatus::values(),
                'privacy_request_types' => DataSubjectRequestType::values(),
            ],
            'invariants' => [
                'Unknown, stale, disabled, or cross-institution contexts fail closed.',
                'Personal progress is never disclosed to an institution and is not reattributed on enrollment.',
                'Invitation acceptance and classroom-code approval grant only the institution Learner role.',
                'Membership identity and provenance are immutable; membership removal is represented by revocation or suspension.',
                'Only one curriculum package may be active, and production delivery requires a fully approved non-draft release.',
                'Canonical content is changed through a reviewed draft/version, not by mutating generated output.',
                'Learner response payloads are bounded and validated against the versioned activity contract.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function capabilities(): array
    {
        $templates = [];
        foreach ($this->exerciseTemplates->all() as $type => $definition) {
            $templates[$type] = [
                'enabled' => $definition['enabled'],
                'unavailable_reason' => $definition['unavailable_reason'],
                'response_form' => $definition['response_form'],
                'scoring_mode' => $definition['scoring_mode'],
                'server_scored' => $this->exerciseTemplates->scoringPolicies()[$definition['scoring_mode']]['server_scored'] ?? false,
                'audio_required' => $definition['audio_required'],
                'rubric_required' => $definition['rubric_required'],
            ];
        }

        return [
            'by_actor' => [
                'visitor' => [
                    'read public help, glossary, about, and policy pages',
                    'register for personal self-study',
                    'sign in, reset a password, or redeem a targeted invitation',
                ],
                'learner' => [
                    'select personal or approved institution learning context',
                    'browse canonical chapters, learning steps, sections, and activities',
                    'submit bounded activity attempts and review own progress',
                    'redeem a classroom code to create a pending join request',
                    'exercise privacy rights and manage account security/preferences',
                ],
                'instructor' => [
                    'view institution-attributed learner progress in the selected institution',
                    'issue or revoke targeted invitations and classroom codes',
                    'approve or reject institution join requests',
                ],
                'institution_admin' => [
                    'perform instructor capabilities',
                    'grant or revoke institution Instructor access',
                ],
                'content_author' => [
                    'create, import, validate, review, preview, and approve curriculum drafts',
                    'inspect retained legacy curriculum evidence read-only',
                    'cannot publish, manage enrollment, or administer platform identities solely from this capability',
                ],
                'system_admin' => [
                    'perform platform-wide administration and audited preview-as-role operations',
                    'publish or roll back curriculum through guarded lifecycle operations',
                    'manage platform roles, institution administration, privacy queues, and retained evidence',
                ],
            ],
            'learning' => [
                'progress_states' => ['not_started', 'viewed', 'started', 'attempted', 'self_checked', 'completed'],
                'completion_is_not_mastery' => true,
                'attempts_are_version_scoped' => true,
                'open_language_scoring' => 'model or rubric self-check; not presented as objective auto-grading',
            ],
            'exercise_contract' => [
                'registry_version' => CanonicalExerciseTemplateRegistry::VERSION,
                'enabled_count' => count($this->exerciseTemplates->enabledTypes()),
                'total_count' => count($templates),
                'templates' => $templates,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function workflows(): array
    {
        return [
            'personal_registration' => [
                'actor' => 'visitor',
                'steps' => ['accept registration policies', 'create personal learner account', 'verify email', 'complete onboarding', 'select personal learning context'],
                'result' => 'Verified personal Learner account with no institution access.',
            ],
            'targeted_invitation' => [
                'actor' => 'authorized institution staff',
                'steps' => ['issue email-bound expiring invitation', 'deliver single-use link', 'recipient authenticates or registers with matching email', 'recipient accepts invitation'],
                'guards' => ['institution scope', 'canonical email match', 'expiry', 'revocation', 'single use'],
                'result' => 'Active institution membership with Learner role only.',
            ],
            'classroom_code_enrollment' => [
                'actor' => 'learner and authorized institution staff',
                'steps' => ['staff issues bounded reusable code', 'learner redeems code', 'system creates pending request', 'staff approves or rejects request'],
                'guards' => ['hashed code storage', 'expiry', 'use limit', 'selected institution scope'],
                'result' => 'Approval creates an institution Learner membership; rejection creates none.',
            ],
            'learning_delivery' => [
                'actor' => 'verified learner',
                'steps' => ['select learning context', 'open chapter', 'work through bounded learning steps and sections', 'attempt or self-check activity', 'record progress in selected scope'],
                'guards' => ['deliverable active package', 'published entities only', 'validated response contract', 'scope ownership'],
                'result' => 'Versioned attempt and progress evidence in exactly one learning scope.',
            ],
            'institution_supervision' => [
                'actor' => 'instructor or institution admin',
                'steps' => ['select work and institution context', 'open supervisor dashboard', 'inspect an authorized learner aggregate or detail'],
                'guards' => ['active membership', 'current institution role', 'strong MFA', 'institution-attributed progress only'],
                'result' => 'No personal or other-institution learning evidence is returned.',
            ],
            'curriculum_authoring_and_release' => [
                'actor' => 'content author and system admin',
                'steps' => ['create isolated draft', 'author or import quarantined content', 'validate', 'review and request changes if needed', 'approve draft', 'system admin confirms and publishes'],
                'guards' => ['immutable source/release checksums', 'role separation', 'recent password for publication', 'human evidence gates for production'],
                'result' => 'One versioned active package; generated projections derive from the same source.',
            ],
            'privacy_request' => [
                'actor' => 'authenticated account owner and authorized system admin',
                'steps' => ['owner submits typed request', 'staff verifies and reviews', 'staff approves, denies, or places a hold', 'approved operation executes', 'owner receives bounded result or export'],
                'guards' => ['owner authorization', 'recent password for sensitive requests', 'encrypted private export', 'signed expiring download', 'append-only lifecycle evidence'],
                'result' => 'Auditable request outcome without exposing another user\'s data.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function architecture(): array
    {
        return [
            'runtime' => [
                'language' => 'PHP '.PHP_VERSION,
                'framework' => app()->version(),
                'database_connection' => (string) config('database.default'),
                'queue_connection' => (string) config('queue.default'),
                'session_driver' => (string) config('session.driver'),
            ],
            'layers' => [
                'routes' => 'Define guest, verified learner, institution staff, content author, and system administrator boundaries.',
                'controllers' => 'Validate HTTP input and delegate delivery or mutations to scoped services.',
                'middleware_and_policies' => 'Enforce authentication, verification, role, MFA, recent-password, record, and security-header boundaries.',
                'services' => 'Own work/learning context, enrollment, curriculum lifecycle, progress, privacy, search, and security behavior.',
                'models' => 'Persist normalized identity, institution, curriculum, evidence, privacy, and security records.',
                'views_and_javascript' => 'Render accessible server-driven pages and bounded interactive exercise/progress clients.',
            ],
            'sources_of_truth' => [
                'curriculum' => 'A versioned canonical curriculum package; database entities and standalone output are projections.',
                'authorization' => 'Normalized platform, capability, membership, and institution-role assignments revalidated per request.',
                'learning_scope' => 'The selected personal or institution learning context, persisted with every new evidence record.',
                'public_trust_content' => 'Versioned application policy/help configuration and translated views.',
            ],
            'storage_boundaries' => [
                'public_media' => 'Learner-facing approved media only.',
                'private_artifacts' => 'Imports, reports, exports, release evidence, and rollback material; never web-served directly.',
                'database' => 'Application state and bounded structured evidence; secrets use hashing or encryption according to purpose.',
                'generated_standalone' => 'Deterministic projection; never an authoring input.',
            ],
            'operational_dependencies' => [
                'queue worker for asynchronous production jobs',
                'scheduler for cleanup and retention work',
                'real production mail transport before accepting users',
                'private artifact backup and recovery procedure',
                'storage link only for explicitly public media',
            ],
            'security_posture' => [
                'deny by default across role and tenant boundaries',
                'CSRF protection for session-authenticated writes',
                'strong MFA for privileged work contexts',
                'recent-password confirmation for selected sensitive operations',
                'rate limits on authentication, enrollment, attempts, privacy, and agent-context reads',
                'CSP and browser security headers on responses',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function curriculum(): array
    {
        if (! Schema::hasTable('curriculum_packages') || ! Schema::hasTable('curriculum_entities')) {
            return [
                'available' => false,
                'status' => 'storage_not_initialized',
                'reason' => 'Curriculum tables have not been installed.',
            ];
        }

        try {
            $package = CurriculumPackage::active();
        } catch (RuntimeException) {
            return [
                'available' => false,
                'status' => 'delivery_refused',
                'reason' => 'The active curriculum failed its delivery guard.',
            ];
        }

        if ($package === null) {
            return [
                'available' => false,
                'status' => 'no_active_package',
                'reason' => 'No curriculum package is currently active.',
            ];
        }

        $entities = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('lifecycle_status', 'published')
            ->whereIn('entity_type', ['chapter', 'lesson-section', 'activity', 'outcome', 'prompt-item', 'answer-model', 'feedback-model', 'rubric'])
            ->orderByRaw('position is null')
            ->orderBy('position')
            ->orderBy('code')
            ->get(['code', 'entity_type', 'parent_code', 'position', 'lifecycle_status', 'payload']);

        $sections = $entities->where('entity_type', 'lesson-section');
        $activities = $entities->where('entity_type', 'activity');
        $outcomes = $entities->where('entity_type', 'outcome');

        $outline = $entities->where('entity_type', 'chapter')->map(function (CurriculumEntity $chapter) use ($sections, $activities, $outcomes): array {
            $chapterSections = $sections->where('parent_code', $chapter->code);
            $chapterPayload = $chapter->payloadData();

            return [
                'code' => $chapter->code,
                'title' => $this->entityTitle($chapter),
                'module' => isset($chapterPayload['module']) ? (int) $chapterPayload['module'] : null,
                'published_outcome_count' => $outcomes->where('parent_code', $chapter->code)->count(),
                'sections' => $chapterSections->map(function (CurriculumEntity $section) use ($activities): array {
                    $sectionActivities = $activities->where('parent_code', $section->code);

                    return [
                        'code' => $section->code,
                        'title' => $this->entityTitle($section),
                        'position' => $section->position === null ? null : (int) $section->position,
                        'activities' => $sectionActivities->map(function (CurriculumEntity $activity): array {
                            $payload = $activity->payloadData();

                            return [
                                'code' => $activity->code,
                                'title' => $this->entityTitle($activity),
                                'response_form' => $payload['response_form'] ?? null,
                                'channel' => $payload['channel'] ?? null,
                                'participation' => $payload['participation'] ?? null,
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        $release = CurriculumRelease::query()
            ->where('curriculum_package_id', $package->getKey())
            ->first();
        $releaseState = $release?->getAttribute('state');
        $importedAt = $package->getAttribute('imported_at');

        return [
            'available' => true,
            'status' => 'deliverable',
            'package' => [
                'name' => $package->package_name,
                'content_version' => $package->content_version,
                'schema_version' => $package->schema_version,
                'lifecycle_status' => $package->lifecycle_status,
                'release_state' => $releaseState instanceof BackedEnum ? $releaseState->value : $releaseState,
                'preview_only' => $release === null
                    ? strtolower((string) $package->lifecycle_status) === 'draft'
                    : (bool) $release->getAttribute('preview_only'),
                'imported_at' => $importedAt instanceof DateTimeInterface ? $importedAt->format(DATE_ATOM) : null,
            ],
            'published_entity_counts' => $entities->countBy('entity_type')->sortKeys()->all(),
            'outline' => $outline,
            'detail_boundary' => 'This API returns navigation-level curriculum context only. Prompt bodies, choices, answers, rubrics, source locators, and learner evidence are intentionally excluded.',
        ];
    }

    /** @return array<string, mixed> */
    private function routes(): array
    {
        $routes = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if (! is_string($name)
                || $name === ''
                || str_starts_with($name, 'ignition.')
                || str_starts_with($name, 'debugbar.')) {
                continue;
            }

            $routes[] = $this->routeContext($route);
        }

        usort($routes, static fn (array $left, array $right): int => [
            $left['audience'], $left['uri'], $left['name'],
        ] <=> [
            $right['audience'], $right['uri'], $right['name'],
        ]);

        $counts = [];
        foreach ($routes as $route) {
            $counts[$route['audience']] = ($counts[$route['audience']] ?? 0) + 1;
        }
        ksort($counts);

        return [
            'inventory_kind' => 'live_named_routes',
            'route_count' => count($routes),
            'counts_by_audience' => $counts,
            'interpretation' => [
                'Routes are browser application surfaces unless their URI begins with /api/.',
                'Mutation means at least one non-GET HTTP method is accepted.',
                'Requirements are inferred from the route middleware currently registered by Laravel.',
                'Record-level policies and service-level guards may impose stricter authorization than this summary.',
            ],
            'routes' => $routes,
        ];
    }

    /** @return array<string, mixed> */
    private function glossary(): array
    {
        return [
            'canonical curriculum' => 'The versioned source package used to create learner delivery and standalone projections.',
            'legacy curriculum' => 'Earlier module/lesson content retained as read-only evidence while canonical delivery is active.',
            'work context' => 'The active role through which a user is operating.',
            'institution context' => 'The active institution against which staff authority is revalidated.',
            'learning context' => 'The personal or institution scope to which new progress is attributed.',
            'membership' => 'A user-to-institution relationship; it grants no staff power without a current role assignment.',
            'institution role' => 'Learner, Instructor, or Institution Admin authority attached to an active membership.',
            'content author' => 'A separately assignable shared-curriculum capability, not an institution role.',
            'system admin' => 'The single global platform role with cross-institution administrative authority.',
            'attempt' => 'One versioned activity submission or self-check event in a specific learning scope.',
            'progress' => 'A bounded state such as viewed, attempted, self-checked, or completed; it is not synonymous with mastery.',
            'deliverable package' => 'The one active package that passes the environment-specific curriculum release guard.',
            'draft preview' => 'A visibly labeled non-production curriculum view; never evidence of production approval.',
            'targeted invitation' => 'An expiring, revocable, single-use institution invitation bound to one canonical email.',
            'classroom code' => 'A bounded reusable secret that creates a pending join request rather than immediate membership.',
        ];
    }

    /** @return array<string, mixed> */
    public function openApi(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Hospitrainity Agent Context API',
                'version' => self::VERSION,
                'description' => 'Read-only, bearer-authenticated, non-user-specific context for software agents and integrations.',
            ],
            'servers' => [['url' => '/']],
            'security' => [['bearerAuth' => []]],
            'paths' => [
                '/api/v1/agent-context' => [
                    'get' => [
                        'summary' => 'Get a compact context bundle',
                        'operationId' => 'getAgentContext',
                        'parameters' => [[
                            'name' => 'include',
                            'in' => 'query',
                            'required' => false,
                            'description' => 'Comma-separated section keys or all. Defaults to product,domain,capabilities.',
                            'schema' => ['type' => 'string', 'maxLength' => 200],
                        ]],
                        'responses' => $this->openApiResponses(),
                    ],
                ],
                '/api/v1/agent-context/openapi' => [
                    'get' => [
                        'summary' => 'Get this OpenAPI document',
                        'operationId' => 'getAgentContextOpenApi',
                        'responses' => $this->openApiDocumentResponses(),
                    ],
                ],
                '/api/v1/agent-context/{section}' => [
                    'get' => [
                        'summary' => 'Get one focused context section',
                        'operationId' => 'getAgentContextSection',
                        'parameters' => [[
                            'name' => 'section',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string', 'enum' => self::SECTIONS],
                        ]],
                        'responses' => $this->openApiResponses(),
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'opaque deployment token',
                    ],
                ],
                'schemas' => [
                    'ContextDocument' => [
                        'type' => 'object',
                        'required' => ['meta', 'links', 'available_sections', 'sections'],
                        'properties' => [
                            'meta' => ['type' => 'object'],
                            'links' => ['type' => 'object'],
                            'available_sections' => ['type' => 'array', 'items' => ['type' => 'object']],
                            'sections' => ['type' => 'object', 'additionalProperties' => ['type' => 'object']],
                        ],
                    ],
                    'OpenApiDocument' => [
                        'type' => 'object',
                        'required' => ['openapi', 'info', 'paths', 'components'],
                        'properties' => [
                            'openapi' => ['type' => 'string'],
                            'info' => ['type' => 'object'],
                            'paths' => ['type' => 'object'],
                            'components' => ['type' => 'object'],
                        ],
                    ],
                    'Error' => [
                        'type' => 'object',
                        'required' => ['error'],
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'required' => ['code', 'message'],
                                'properties' => [
                                    'code' => ['type' => 'string'],
                                    'message' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function entityTitle(CurriculumEntity $entity): string
    {
        $title = $entity->payloadData()['title'] ?? null;

        return is_string($title) && trim($title) !== '' ? trim($title) : $entity->code;
    }

    /** @return array<string, mixed> */
    private function routeContext(LaravelRoute $route): array
    {
        $middleware = array_map('strval', $route->gatherMiddleware());
        $roles = [];
        foreach ($middleware as $item) {
            if (str_starts_with($item, 'role:')) {
                $roles = array_values(array_filter(explode(',', substr($item, strlen('role:')))));
            }
        }

        $name = (string) $route->getName();
        $usesAgentToken = str_starts_with($name, 'api.agent-context.');
        $audience = match (true) {
            $usesAgentToken => 'agent_integration',
            in_array('superadmin', $roles, true) => 'system_admin',
            in_array('admin', $roles, true) => 'content_author',
            in_array('supervisor', $roles, true) => 'institution_staff',
            in_array('user', $roles, true) => 'learner',
            in_array('guest', $middleware, true) => 'visitor',
            in_array('auth', $middleware, true) => 'authenticated_account',
            default => 'public',
        };

        $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
        $authorizationPolicies = array_values(array_filter(
            $middleware,
            static fn (string $item): bool => str_starts_with($item, 'can:'),
        ));

        return [
            'name' => $name,
            'methods' => $methods,
            'uri' => '/'.ltrim($route->uri(), '/'),
            'audience' => $audience,
            'mutation' => array_diff($methods, ['GET']) !== [],
            'requirements' => [
                'guest_only' => in_array('guest', $middleware, true),
                'authenticated' => in_array('auth', $middleware, true) || $roles !== [],
                'bearer_token' => $usesAgentToken,
                'verified_email' => in_array('verified', $middleware, true),
                'strong_mfa' => in_array('privileged.mfa', $middleware, true),
                'recent_password' => in_array('password.confirm', $middleware, true),
                'signed_url' => in_array('signed', $middleware, true),
                'rate_limited' => collect($middleware)->contains(static fn (string $item): bool => str_starts_with($item, 'throttle:')),
                'legacy_roles' => $roles,
                'record_policies' => $authorizationPolicies,
            ],
        ];
    }

    /** @return array<int|string, mixed> */
    private function openApiResponses(): array
    {
        return [
            '200' => [
                'description' => 'Context document.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ContextDocument']]],
            ],
            '401' => [
                'description' => 'Missing or invalid bearer token.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            '404' => [
                'description' => 'API disabled or section unknown.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
            '422' => [
                'description' => 'Invalid include query.',
                'content' => ['application/json' => ['schema' => ['type' => 'object']]],
            ],
            '429' => [
                'description' => 'Rate limit exceeded.',
                'content' => ['application/json' => ['schema' => ['type' => 'object']]],
            ],
            '503' => [
                'description' => 'API enabled without a valid server token.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            ],
        ];
    }

    /** @return array<int|string, mixed> */
    private function openApiDocumentResponses(): array
    {
        $responses = $this->openApiResponses();
        $responses[200] = [
            'description' => 'OpenAPI document.',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/OpenApiDocument']]],
        ];

        return $responses;
    }
}
