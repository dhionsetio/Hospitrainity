<?php

namespace App\Console\Commands;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionStatus;
use App\Enums\LegacyInstitutionState;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Throwable;

class IdentityMigrationPreflight extends Command
{
    /** @var array<string, string> */
    private const EXACT_LEGACY_MAPPINGS = [
        'Hospitrainity HQ' => 'hospitrainity-hq',
        'Politeknik Negeri Malang' => 'politeknik-negeri-malang',
        'State Polytechnic of Malang' => 'politeknik-negeri-malang',
    ];

    protected $signature = 'hospitrainity:identity-migration-preflight
        {--include-legacy-values : Include plaintext legacy institution labels for an authorized mapping review}';

    protected $description = 'Read-only normalized-identity migration counts, owner gates, and invariants';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('sessions')) {
            $this->error('Identity preflight requires the users and sessions tables. No data was changed.');

            return self::FAILURE;
        }

        try {
            $report = DB::transaction(fn (): array => $this->buildReport(), 3);
            $this->line(json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
        } catch (JsonException) {
            $this->error('Identity preflight could not encode its bounded report. No data was changed.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Identity preflight failed. Review protected application logs; no migration or cleanup was attempted.');

            return self::FAILURE;
        }

        return data_get($report, 'normalized.all_invariants_passed') === false
            ? self::FAILURE
            : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function buildReport(): array
    {
        $normalizedSchema = Schema::hasTable('institutions')
            && Schema::hasTable('institution_memberships')
            && Schema::hasTable('identity_migration_states')
            && Schema::hasColumn('users', 'legacy_institution_state');
        $userColumns = ['id', 'email', 'instansi'];
        if ($normalizedSchema) {
            $userColumns[] = 'legacy_institution_state';
        }

        $users = DB::table('users')->select($userColumns)->orderBy('id')->get();
        $legacyGroups = [];
        $knownDemoEmails = array_fill_keys(array_map(
            static fn (mixed $email): string => User::canonicalEmail($email),
            (array) config('identity.known_demo_emails', []),
        ), true);
        $knownDemoIdentityCount = 0;
        $exactCandidateCount = 0;

        foreach ($users as $user) {
            $legacy = (string) $user->instansi;
            $groupKey = hash('sha256', $legacy);
            $legacyGroups[$groupKey] ??= [
                'value_sha256' => $groupKey,
                'user_count' => 0,
                'planned_institution_key' => self::EXACT_LEGACY_MAPPINGS[$legacy] ?? null,
            ];
            $legacyGroups[$groupKey]['user_count']++;
            if ($this->option('include-legacy-values')) {
                $legacyGroups[$groupKey]['legacy_value'] = $legacy;
            }
            if (isset(self::EXACT_LEGACY_MAPPINGS[$legacy])) {
                $exactCandidateCount++;
            }
            if (isset($knownDemoEmails[User::canonicalEmail($user->email)])) {
                $knownDemoIdentityCount++;
            }
        }

        ksort($legacyGroups, SORT_STRING);
        $sessionCounts = [
            'total' => DB::table('sessions')->count(),
            'authenticated' => DB::table('sessions')->whereNotNull('user_id')->count(),
        ];
        $normalized = $normalizedSchema ? $this->normalizedReport($users) : null;

        return [
            'schema_version' => '1.0.0',
            'operation' => 'read_only_identity_migration_preflight',
            'plaintext_legacy_values_included' => (bool) $this->option('include-legacy-values'),
            'normalized_schema_present' => $normalizedSchema,
            'users' => [
                'total' => $users->count(),
                'exact_mapping_candidates' => $exactCandidateCount,
                'unmatched_or_empty_candidates' => $users->count() - $exactCandidateCount,
                'known_demo_identities' => $knownDemoIdentityCount,
            ],
            'legacy_value_groups' => array_values($legacyGroups),
            'sessions' => $sessionCounts,
            'owner_gates' => [
                'demo_identity_disposition_required' => $knownDemoIdentityCount > 0
                    || (int) data_get($normalized, 'demo_fixture_memberships', 0) > 0
                    || (int) data_get($normalized, 'known_demo_institutions', 0) > 0,
                'all_session_revocation_permission_required' => $sessionCounts['total'] > 0,
                'unmatched_legacy_disposition_required' => $users->count() - $exactCandidateCount > 0,
            ],
            'normalized' => $normalized,
        ];
    }

    /**
     * @param  Collection<int, object>  $users
     * @return array<string, mixed>
     */
    private function normalizedReport(Collection $users): array
    {
        $memberships = DB::table('institution_memberships')
            ->join('institutions', 'institutions.id', '=', 'institution_memberships.institution_id')
            ->select([
                'institution_memberships.user_id',
                'institution_memberships.status',
                'institution_memberships.is_default',
                'institutions.key as institution_key',
            ])
            ->orderBy('institution_memberships.user_id')
            ->get();
        $activeByUser = [];
        $activeMembershipCount = 0;
        foreach ($memberships as $membership) {
            if ($membership->status !== InstitutionMembershipStatus::Active->value) {
                continue;
            }
            $activeMembershipCount++;
            $activeByUser[(string) $membership->user_id][(string) $membership->institution_key] = true;
        }

        $exactCandidateMismatchCount = 0;
        $unresolvedWithActiveMembershipCount = 0;
        $multipleDefaultUserCount = (int) DB::query()->fromSub(
            DB::table('institution_memberships')
                ->select('user_id')
                ->where('status', InstitutionMembershipStatus::Active->value)
                ->where('is_default', true)
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1'),
            'multiple_defaults',
        )->count();

        foreach ($users as $user) {
            $userMemberships = $activeByUser[(string) $user->id] ?? [];
            $expectedKey = self::EXACT_LEGACY_MAPPINGS[(string) $user->instansi] ?? null;
            if ($expectedKey !== null
                && ($user->legacy_institution_state !== LegacyInstitutionState::Mapped->value
                    || ! isset($userMemberships[$expectedKey]))) {
                $exactCandidateMismatchCount++;
            }
            if ($user->legacy_institution_state === LegacyInstitutionState::Unresolved->value
                && $userMemberships !== []) {
                $unresolvedWithActiveMembershipCount++;
            }
        }

        $realInstitutions = [];
        $realInstitutionFailureCount = 0;
        foreach (['hospitrainity-hq', 'politeknik-negeri-malang'] as $key) {
            $institution = DB::table('institutions')->where('key', $key)->first();
            if ($institution === null || $institution->status !== InstitutionStatus::Active->value) {
                $realInstitutionFailureCount++;
            }
            $realInstitutions[$key] = [
                'exists' => $institution !== null,
                'status' => $institution?->status,
                'verified' => $institution?->verified_at !== null,
            ];
        }

        $migrationState = DB::table('identity_migration_states')
            ->where('name', 'normalized-institutions-session-revocation-v1')
            ->first();
        $invariants = [
            [
                'name' => 'exact_candidates_have_expected_active_membership',
                'passed' => $exactCandidateMismatchCount === 0,
                'failure_count' => $exactCandidateMismatchCount,
            ],
            [
                'name' => 'unresolved_users_have_no_active_membership',
                'passed' => $unresolvedWithActiveMembershipCount === 0,
                'failure_count' => $unresolvedWithActiveMembershipCount,
            ],
            [
                'name' => 'at_most_one_active_default_membership_per_user',
                'passed' => $multipleDefaultUserCount === 0,
                'failure_count' => $multipleDefaultUserCount,
            ],
            [
                'name' => 'required_real_institutions_exist_and_are_active',
                'passed' => $realInstitutionFailureCount === 0,
                'failure_count' => $realInstitutionFailureCount,
            ],
            [
                'name' => 'identity_session_finalization_state_exists',
                'passed' => $migrationState !== null,
                'failure_count' => $migrationState === null ? 1 : 0,
            ],
        ];

        return [
            'institutions' => DB::table('institutions')->count(),
            'memberships' => $memberships->count(),
            'active_memberships' => $activeMembershipCount,
            'known_demo_institutions' => DB::table('institutions')
                ->whereIn('key', (array) config('identity.known_demo_institution_keys', []))
                ->count(),
            'demo_fixture_memberships' => DB::table('institution_memberships')
                ->where('provenance', 'disposable_demo_fixture')
                ->count(),
            'real_institutions' => $realInstitutions,
            'identity_session_finalization' => [
                'state_present' => $migrationState !== null,
                'completed' => $migrationState?->completed_at !== null,
                'revoked_session_count' => (int) ($migrationState?->revoked_session_count ?? 0),
                'completion_method' => $migrationState?->completion_method,
            ],
            'all_invariants_passed' => collect($invariants)->every('passed', true),
            'invariants' => $invariants,
        ];
    }
}
