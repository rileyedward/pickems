<?php

namespace App\Actions\Fortify;

use App\Actions\Weeks\EnrollInOpenWeek;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private EnrollInOpenWeek $enroll) {}

    /**
     * Validate and create a newly registered user. New accounts start
     * inactive and non-admin until an admin activates them, unless the
     * registration passcode is given, which activates them right away.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'passcode' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! $this->passcodeMatches($value)) {
                    $fail("That passcode isn't right. Leave it blank to wait for an admin instead.");
                }
            }],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if (filled($input['passcode'] ?? null)) {
            $user->forceFill(['is_active' => true])->save();
            $this->enroll->handle($user);
        }

        return $user;
    }

    private function passcodeMatches(mixed $value): bool
    {
        $passcode = (string) config('app.registration_passcode');

        return $passcode !== '' && is_string($value) && hash_equals($passcode, $value);
    }
}
