<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_token_for_first_user_when_no_email_given(): void
    {
        User::factory()->create(['email' => 'first@example.com']);

        $this->artisan('user:token')->assertExitCode(0);
    }

    public function test_issues_token_for_user_matching_email_argument(): void
    {
        User::factory()->create(['email' => 'other@example.com']);
        User::factory()->create(['email' => 'match@example.com']);

        $this->artisan('user:token', ['email' => 'match@example.com'])->assertExitCode(0);
    }

    public function test_fails_when_no_users_exist(): void
    {
        $this->artisan('user:token')
            ->expectsOutputToContain('No users found.')
            ->assertExitCode(1);
    }

    public function test_fails_when_email_argument_matches_no_user(): void
    {
        $this->artisan('user:token', ['email' => 'missing@example.com'])
            ->expectsOutputToContain('No user found with email: missing@example.com')
            ->assertExitCode(1);
    }
}
