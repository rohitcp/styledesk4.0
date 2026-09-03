<?php

namespace Tests\Feature;

use App\Http\Middleware\ScopeSessionCookieToHost;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The session cookie has to be scoped to a domain the host belongs to.
 *
 * Get this wrong and nothing errors: the browser refuses a cookie whose Domain
 * does not cover the host, so there is no session, and the reader logs in and
 * is bounced straight back to the login screen forever.
 */
class SessionCookieHostTest extends TestCase
{
    private function domainFor(string $host): ?string
    {
        config(['session.domain' => '.styledesk.test']);

        (new ScopeSessionCookieToHost)->handle(
            Request::create('https://'.$host.'/settings/email'),
            fn () => response('ok'),
        );

        return config('session.domain');
    }

    public function test_the_configured_domain_stands_for_the_hosts_it_covers(): void
    {
        $this->assertSame('.styledesk.test', $this->domainFor('styledesk.test'));

        /* Tenant booking subdomains share the session with the central app,
           which is what the setting is for. */
        $this->assertSame('.styledesk.test', $this->domainFor('acme.styledesk.test'));
    }

    /** A tunnel, an IP, a staging host: a host-only cookie, which always works. */
    public function test_a_host_the_domain_does_not_cover_gets_a_host_only_cookie(): void
    {
        $this->assertNull($this->domainFor('abc123.ngrok-free.app'));
        $this->assertNull($this->domainFor('localhost'));
    }

    /**
     * A near-miss must not count as covered.
     *
     * `notstyledesk.test` ends with the same letters and is a different site;
     * matching on the suffix alone would hand it the session cookie.
     */
    public function test_a_host_that_merely_ends_with_the_same_letters_is_not_covered(): void
    {
        $this->assertNull($this->domainFor('notstyledesk.test'));
    }
}
