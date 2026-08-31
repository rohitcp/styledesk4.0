<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The front door answers.
     *
     * A redirect rather than a page: while StyleDesk is invitation only, '/'
     * hands the visitor on to the access-code screen or, once past it, to the
     * login form. AccessCodeTest covers which of the two, and when.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
