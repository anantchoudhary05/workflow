<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the first (or another) administrator account interactively.
 * The password is read with a hidden prompt and never echoed or logged.
 */
#[Signature('doc:make-admin {email? : Email address of the administrator}')]
#[Description('Create an administrator account, or promote an existing user to administrator')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('Email'));
        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            if (! $this->confirm("{$existing->name} already exists. Promote this account to an active administrator?")) {
                return self::FAILURE;
            }
            $existing->forceFill(['role' => Role::Admin, 'is_active' => true])->save();
            Activity::log('user.promoted', "Promoted {$existing->name} to administrator from the console", $existing, [], true);
            $this->info("{$existing->email} is now an administrator.");

            return self::SUCCESS;
        }

        $name = (string) $this->ask('Name');
        $password = (string) $this->secret('Password (hidden)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::defaults()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email]);
        $user->forceFill(['password' => $password, 'role' => Role::Admin, 'is_active' => true])->save();
        Activity::log('user.created', "Created administrator {$user->name} from the console", $user, [], true);
        $this->info("Administrator {$user->email} created. Sign in at ".route('login'));

        return self::SUCCESS;
    }
}
