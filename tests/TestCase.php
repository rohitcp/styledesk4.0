<?php

namespace Tests;

use App\Http\Middleware\RequireAccessCode;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test starts past the access-code gate.
     *
     * The gate is a door in front of the product, not a behaviour of the
     * product: a test about signing in is about signing in, and asserting a
     * redirect to /access in each of them would say nothing about the thing
     * under test. AccessCodeTest flushes the session and exercises the door
     * itself.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withCookie(RequireAccessCode::COOKIE, RequireAccessCode::VALUE);
    }
}
