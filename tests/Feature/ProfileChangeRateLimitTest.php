<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ProfileChangeRateLimitTest extends TestCase
{
    public function test_profile_email_change_has_four_per_day_limit(): void
    {
        $limits = $this->limitsFor('profile-email-change-request', 3001);

        $this->assertDailyLimit($limits, 'new_email');
    }

    public function test_profile_password_change_has_four_per_day_limit(): void
    {
        $limits = $this->limitsFor('profile-password-update', 3001);

        $this->assertDailyLimit($limits, 'current_password');
    }

    /**
     * @return array<int, Limit>
     */
    private function limitsFor(string $name, int $userId): array
    {
        $request = Request::create('/studio/profile', 'POST');
        $request->setUserResolver(static fn () => new class ($userId) {
            public function __construct(private int $id)
            {
            }

            public function __get(string $name): ?int
            {
                return $name === 'id' ? $this->id : null;
            }
        });

        $limiter = RateLimiter::limiter($name);
        $this->assertNotNull($limiter);

        return $limiter($request);
    }

    /**
     * @param array<int, Limit> $limits
     */
    private function assertDailyLimit(array $limits, string $errorKey): void
    {
        $dailyLimit = collect($limits)->first(
            fn (Limit $limit): bool => $limit->maxAttempts === 4 && $limit->decayMinutes === 1440
        );

        $this->assertInstanceOf(Limit::class, $dailyLimit);
        $this->assertIsCallable($dailyLimit->responseCallback);

        $response = ($dailyLimit->responseCallback)(Request::create('/studio/profile', 'POST'), [
            'Retry-After' => '3600',
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('3600', $response->headers->get('Retry-After'));
        $this->assertTrue(session('errors')->has($errorKey));
    }
}
