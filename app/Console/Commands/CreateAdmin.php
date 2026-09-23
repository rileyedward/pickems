<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

#[Signature('app:create-admin {email} {name} {--password= : Set the password without a prompt (for non-interactive deploy consoles)}')]
#[Description('Create an admin account, or promote an existing one and reset its password')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $name = (string) $this->argument('name');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = $this->option('password');

        if (filled($password) && strlen((string) $password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $password = filled($password) ? (string) $password : password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => strlen($value) < 8 ? 'The password must be at least 8 characters.' : null,
        );

        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now(), 'is_admin' => true, 'is_active' => true])->save();

        $this->info($user->wasRecentlyCreated ? "Admin {$email} created." : "Admin {$email} updated.");

        return self::SUCCESS;
    }
}
