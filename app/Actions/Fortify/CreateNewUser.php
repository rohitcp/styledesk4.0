<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\InputCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Mirrors the fields in the prototype's signup.html: first and last name
     * separately, and an explicit terms checkbox. The terms box is accepted
     * rather than assumed — the prototype flags this as a spec requirement,
     * so consent is recorded with a timestamp instead of being implied by the
     * act of pressing the button.
     *
     * No tenant is created here. A user exists first and picks up a
     * `tenant_id` during onboarding; App\Http\Middleware\InitializeTenancyFromUser
     * lets a tenant-less user through so those routes stay reachable.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Normalized before validation so the unique check and the stored row
        // agree; without it the same inbox can register twice under different
        // casing and the unique index will not stop it.
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'You must accept the Terms of Service and Privacy Policy.',
            // The sign-up view keys its "Log in instead" panel off this wording.
            'email.unique' => 'An account already exists with this email address.',
        ])->validate();

        return User::create([
            // Names follow the project capitalisation rule; the email above
            // deliberately does not, because case there is not the user's to
            // choose and lower-casing it is what keeps the address unique.
            'first_name' => InputCase::sentence($input['first_name']),
            'last_name' => InputCase::sentence($input['last_name']),
            'email' => $input['email'],
            'password' => $input['password'],
            'terms_accepted_at' => now(),
        ]);
    }
}
