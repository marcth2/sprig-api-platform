<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateUserToken extends Command
{
    protected $signature = 'user:token {email? : Email of the user to issue a token for (defaults to the first user)}';

    protected $description = 'Issue a Sanctum bearer token for local development';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = is_string($email) ? User::where('email', $email)->first() : User::first();

        if (! $user) {
            $this->error(is_string($email) ? "No user found with email: {$email}" : 'No users found.');
            $this->line('Run: php artisan migrate --seed');

            return self::FAILURE;
        }

        $this->line($user->createToken('dev')->plainTextToken);

        return self::SUCCESS;
    }
}
