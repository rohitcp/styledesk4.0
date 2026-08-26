<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Password rules, spelled out rather than left to Password::default().
     *
     * The sign-up form shows the user a live checklist of exactly these five
     * conditions, so the server has to enforce the same five. Relying on the
     * framework default would let the two drift apart the moment the default
     * changes, and the user would see a green tick next to a rule the server
     * then rejects.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(8)
                ->mixedCase()   // at least one upper and one lower
                ->numbers()
                ->symbols(),
        ];
    }
}
