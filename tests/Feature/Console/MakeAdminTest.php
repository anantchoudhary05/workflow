<?php

namespace Tests\Feature\Console;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MakeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_active_administrator(): void
    {
        $this->artisan('doc:make-admin', ['email' => 'owner@example.com'])
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Password (hidden)', 'a-Long-test-passphrase-9')
            ->expectsQuestion('Confirm password', 'a-Long-test-passphrase-9')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('a-Long-test-passphrase-9', $user->password));
    }

    public function test_fails_when_passwords_do_not_match(): void
    {
        $this->artisan('doc:make-admin', ['email' => 'owner@example.com'])
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Password (hidden)', 'a-Long-test-passphrase-9')
            ->expectsQuestion('Confirm password', 'different-passphrase-9')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_promotes_an_existing_user_after_confirmation(): void
    {
        $user = User::factory()->inactive()->create();

        $this->artisan('doc:make-admin', ['email' => $user->email])
            ->expectsConfirmation("{$user->name} already exists. Promote this account to an active administrator?", 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->is_active);
    }
}
