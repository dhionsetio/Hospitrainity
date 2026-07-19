<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $users = DB::table('users')->orderBy('id')->get(['id', 'email']);
            $tokens = DB::table('password_reset_tokens')->orderBy('email')->get(['email']);

            $userGroups = [];
            foreach ($users as $user) {
                $canonical = $this->canonicalEmail($user->email);
                $userGroups[$canonical][] = (int) $user->id;
            }

            $tokenGroups = [];
            foreach ($tokens as $token) {
                $canonical = $this->canonicalEmail($token->email);
                $tokenGroups[$canonical][] = (string) $token->email;
            }

            $userConflicts = array_filter($userGroups, static fn (array $ids): bool => count($ids) > 1);
            $tokenConflicts = array_filter($tokenGroups, static fn (array $emails): bool => count($emails) > 1);

            if ($userConflicts !== [] || $tokenConflicts !== []) {
                $userIdGroups = array_map(
                    static fn (array $ids): string => '['.implode(',', $ids).']',
                    array_values($userConflicts),
                );

                throw new RuntimeException(sprintf(
                    'Email normalization aborted without changes: %d canonical user collision(s) at ID groups %s and %d password-reset-token collision(s). Resolve these records explicitly, then rerun the migration.',
                    count($userConflicts),
                    $userIdGroups === [] ? 'none' : implode(';', $userIdGroups),
                    count($tokenConflicts),
                ));
            }

            foreach ($users as $user) {
                $canonical = $this->canonicalEmail($user->email);
                if ($canonical !== $user->email) {
                    DB::table('users')->where('id', $user->id)->update(['email' => $canonical]);
                }
            }

            foreach ($tokens as $token) {
                $canonical = $this->canonicalEmail($token->email);
                if ($canonical !== $token->email) {
                    DB::table('password_reset_tokens')
                        ->where('email', $token->email)
                        ->update(['email' => $canonical]);
                }
            }
        }, attempts: 3);
    }

    public function down(): void
    {
        // Canonicalization intentionally has no guessed reverse transformation:
        // the original casing cannot be reconstructed reliably. Re-running up()
        // is idempotent, and collision detection always occurs before mutation.
    }

    private function canonicalEmail(mixed $email): string
    {
        return mb_strtolower(trim((string) $email), 'UTF-8');
    }
};
