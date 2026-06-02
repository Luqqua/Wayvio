<?php

namespace Tests\Unit;

use App\Support\EmailLocaleResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class EmailLocaleResolverTest extends TestCase
{
    public function test_resolves_account_locale_first(): void
    {
        config()->set('app.supported_locales', ['en', 'de', 'fr']);

        $resolved = EmailLocaleResolver::resolve('fr', 'en-US', 'de-DE');

        $this->assertSame('fr', $resolved);
    }

    public function test_uses_last_login_locale_when_account_locale_is_empty(): void
    {
        config()->set('app.supported_locales', ['en', 'de']);

        $resolved = EmailLocaleResolver::resolve(null, 'en-US', 'de-DE');

        $this->assertSame('en', $resolved);
    }

    public function test_falls_back_to_german_when_no_locale_matches(): void
    {
        config()->set('app.supported_locales', ['en']);

        $resolved = EmailLocaleResolver::resolve(null, null, null);

        $this->assertSame('de', $resolved);
    }

    public function test_resolves_supported_browser_locale_from_request(): void
    {
        config()->set('app.supported_locales', ['en', 'de']);

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'fr-CH, en-US;q=0.9, de;q=0.8',
        ]);

        $resolved = EmailLocaleResolver::resolveFromRequest($request);

        $this->assertSame('en', $resolved);
    }
}
