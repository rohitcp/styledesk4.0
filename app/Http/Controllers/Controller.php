<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 11+ no longer includes this on the base controller, and policies
    // are the mechanism the spec asks for: authorisation enforced server-side.
    use AuthorizesRequests;
}
