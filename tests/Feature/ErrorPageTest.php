<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every HTTP error the application can present has a StyleDesk page.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public static function codeProvider(): array
    {
        return array_map(
            fn (int $code) => [$code],
            [400, 401, 402, 403, 404, 405, 408, 409, 410, 419, 422, 429, 500, 501, 502, 503, 504]
        );
    }

    #[DataProvider('codeProvider')]
    public function test_every_error_page_renders(int $code): void
    {
        $meta = config('errors.'.$code);

        $this->assertNotNull($meta, "No copy configured for {$code}.");

        $html = view('errors.'.$code, ['code' => $code, 'exception' => new \Exception()])->render();

        $this->assertStringContainsString('Error '.$code, $html);
        $this->assertStringContainsString($meta['title'], $html);
        $this->assertStringContainsString('StyleDesk', $html);
    }

    public function test_a_missing_page_returns_the_styledesk_404(): void
    {
        $this->get('http://styledesk.test/no-such-page')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Error 404');
    }

    public function test_error_pages_do_not_depend_on_the_build_manifest(): void
    {
        // @vite throws when the manifest is missing, which would turn an error
        // page into a second error. The page inlines its styles instead.
        $html = view('errors.500', ['code' => 500, 'exception' => new \Exception()])->render();

        $this->assertStringNotContainsString('/build/', $html);
        $this->assertStringContainsString('<style>', $html);
    }

    public function test_the_error_page_never_leaks_the_exception_message(): void
    {
        // Exception text can carry file paths, SQL or internal identifiers.
        $secret = 'SQLSTATE[42S02]: Base table or view not found: styledesk_v2.secrets';

        $html = view('errors.500', ['code' => 500, 'exception' => new \Exception($secret)])->render();

        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringNotContainsString('SQLSTATE', $html);
    }

    public function test_an_unauthenticated_page_offers_a_way_to_sign_in(): void
    {
        $html = view('errors.401', ['code' => 401, 'exception' => new \Exception()])->render();

        $this->assertStringContainsString(route('login'), $html);
    }
}
