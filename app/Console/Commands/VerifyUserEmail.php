<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Console\Command;

class VerifyUserEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-user-email {email : The email address of the user to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marks a user\'s email address as verified for local testing and administration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] was not found.");

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info("User [{$email}] is already verified.");

            return self::SUCCESS;
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            $this->info("Successfully verified email for user [{$email}].");

            return self::SUCCESS;
        }

        $this->error("Failed to mark email as verified for user [{$email}].");

        return self::FAILURE;
    }
}
