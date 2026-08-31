<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\RequireAccessCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

/**
 * The screen in front of sign-in and sign-up.
 *
 * One field and one fact: the code is either on the list or it is not. There
 * is no account here, nothing to look up and nobody to tell apart, which is
 * why this touches no user and no tenant at all — it sets the cookie that
 * says "this visitor has a code" and gets out of the way. RequireAccessCode
 * is what reads it.
 */
class AccessCodeController extends Controller
{
    public function show(Request $request): RedirectResponse|View
    {
        /* Someone who has already answered it, arriving back here from a
           bookmark or the back button, is sent on rather than asked twice. */
        if (RequireAccessCode::hasPassed($request)) {
            return redirect()->intended(route('login'));
        }

        return view('auth.access-code');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_code' => ['required', 'string', 'max:64'],
        ], [
            'access_code.required' => 'Enter your access code.',
        ]);

        if (! $this->isValidCode($validated['access_code'])) {
            /* The same sentence for an unknown code and a withdrawn one:
               there is nothing useful to distinguish, and a message that
               narrowed it down would be a hint to whoever is guessing. */
            throw ValidationException::withMessages([
                'access_code' => 'That access code is not valid.',
            ]);
        }

        /**
         * Re-keyed on success. The session id that a visitor carried up to the
         * gate should not be the one they carry past it — the same reason
         * Laravel regenerates on login. The intended URL is read first,
         * because regenerating is what would lose it.
         */
        $intended = $request->session()->pull('url.intended', route('login'));

        $request->session()->regenerate();

        return redirect()->to($intended)->withCookie(Cookie::make(
            RequireAccessCode::COOKIE,
            RequireAccessCode::VALUE,
            RequireAccessCode::LIFETIME_MINUTES,
        ));
    }

    /**
     * Whether the typed code is one of the ones handed out.
     *
     * hash_equals rather than ===, and every code is compared rather than
     * stopping at the first match, so the time taken says nothing about how
     * much of a code was right or where on the list it sits.
     */
    private function isValidCode(string $submitted): bool
    {
        $submitted = trim($submitted);
        $matched = false;

        foreach ((array) config('access_gate.codes') as $code) {
            if (hash_equals((string) $code, $submitted)) {
                $matched = true;
            }
        }

        return $matched;
    }
}
