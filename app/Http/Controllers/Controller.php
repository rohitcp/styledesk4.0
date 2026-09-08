<?php

namespace App\Http\Controllers;

use App\Support\ReturnTo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    // Laravel 11+ no longer includes this on the base controller, and policies
    // are the mechanism the spec asks for: authorisation enforced server-side.
    use AuthorizesRequests;

    /**
     * What an edit view needs to send somebody back where they came from.
     *
     * `returnTo` is the address Back and Cancel point at; `returnPath` is the
     * same thing in the form the hidden field carries, so a save that had to
     * be corrected first still ends up there.
     *
     * @return array{returnTo: string, returnPath: string|null}
     */
    protected function returnTo(Request $request, string $fallback): array
    {
        return [
            'returnTo' => ReturnTo::resolve($request, $fallback),
            'returnPath' => ReturnTo::path($request),
        ];
    }
}
