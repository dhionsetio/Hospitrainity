<?php

namespace App\Console\Commands;

use App\Models\IdentityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class FinalizeIdentityMigration extends Command
{
    private const STATE = 'normalized-institutions-session-revocation-v1';

    private const CONFIRMATION = 'REVOKE-ALL-DATABASE-SESSIONS';

    protected $signature = 'hospitrainity:finalize-identity-migration
        {--confirm= : Must be exactly REVOKE-ALL-DATABASE-SESSIONS}';

    protected $description = 'Explicitly revoke all database sessions after the normalized-institution migration';

    public function handle(): int
    {
        if (! hash_equals(self::CONFIRMATION, (string) $this->option('confirm'))) {
            $this->error('Refusing session revocation: pass --confirm='.self::CONFIRMATION.' only after owner approval.');

            return self::FAILURE;
        }

        if (config('session.driver') !== 'database') {
            $this->error('Refusing session revocation: SESSION_DRIVER must be database so every active session is centrally revocable.');

            return self::FAILURE;
        }

        $sessionTable = (string) config('session.table', 'sessions');
        if (! Schema::hasTable('identity_migration_states')
            || ! Schema::hasTable('identity_audits')
            || ! Schema::hasTable($sessionTable)) {
            $this->error('Refusing session revocation: required identity or session tables are missing. Run migrations first.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($sessionTable): array {
                $state = DB::table('identity_migration_states')
                    ->where('name', self::STATE)
                    ->lockForUpdate()
                    ->first();
                if ($state === null) {
                    throw new RuntimeException('The identity migration state row is missing.');
                }
                if ($state->completed_at !== null) {
                    return ['already_completed' => true, 'revoked' => (int) $state->revoked_session_count];
                }

                $revoked = DB::table($sessionTable)->delete();
                DB::table('identity_migration_states')
                    ->where('name', self::STATE)
                    ->update([
                        'completed_at' => now(),
                        'revoked_session_count' => $revoked,
                        'completion_method' => 'confirmed_console_command',
                        'updated_at' => now(),
                    ]);
                IdentityAudit::query()->create([
                    'event' => 'identity_migration.sessions_revoked',
                    'metadata' => ['sessions_revoked' => $revoked],
                    'created_at' => now(),
                ]);

                return ['already_completed' => false, 'revoked' => $revoked];
            }, 3);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Identity session revocation failed and was rolled back. Review protected application logs before retrying.');

            return self::FAILURE;
        }

        if ($result['already_completed']) {
            $this->info('Identity session revocation was already completed; no sessions were deleted by this run.');

            return self::SUCCESS;
        }

        $this->info("Identity migration finalized; {$result['revoked']} database session(s) revoked.");

        return self::SUCCESS;
    }
}
