<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use Mockery;
use Modules\Tiers\Services\SubscriptionManager;
use Tests\TestCase;

class CustomDomainLockedViewsIsolationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_agency_settings_locked_view_does_not_render_fake_domain_actions(): void
    {
        $user = $this->fakeUser();
        $this->be($user);
        $this->mockLockedCustomDomainState($user);

        $html = view('modules.CustomDomains.views.agency-settings')->render();

        $this->assertStringNotContainsString('example.com', $html);
        $this->assertStringNotContainsString('your-domain.tld', $html);
        $this->assertStringNotContainsString('>Verify<', $html);
        $this->assertStringNotContainsString('>Remove<', $html);
        $this->assertStringContainsString('No custom domain is active for this account on the current tier.', $html);
    }

    public function test_hub_domain_locked_view_does_not_render_fake_domain_actions(): void
    {
        $user = $this->fakeUser();
        $this->be($user);
        $this->mockLockedCustomDomainState($user);

        $html = view('modules.CustomDomains.views.hub-domain')->render();

        $this->assertStringNotContainsString('example.com', $html);
        $this->assertStringNotContainsString('your-domain.tld', $html);
        $this->assertStringNotContainsString('>Verify<', $html);
        $this->assertStringNotContainsString('>Remove<', $html);
        $this->assertStringContainsString('No custom domain is active for this workspace on the current tier.', $html);
    }

    private function mockLockedCustomDomainState(User $user): void
    {
        $subscriptionManager = Mockery::mock(SubscriptionManager::class)->shouldIgnoreMissing();
        $subscriptionManager
            ->shouldReceive('featureEnabled')
            ->andReturn(false);

        $agencyContext = Mockery::mock(AgencyHubContext::class)->shouldIgnoreMissing();
        $agencyContext
            ->shouldReceive('isAgencyAccount')
            ->andReturn(false);
        $agencyContext
            ->shouldReceive('sidebarData')
            ->andReturn([
                'is_agency' => false,
                'active_user_id' => (int) $user->id,
                'active_user' => $user,
                'hubs' => collect(),
                'slots' => ['used' => 1, 'total' => 1],
            ]);

        $this->app->instance(SubscriptionManager::class, $subscriptionManager);
        $this->app->instance(AgencyHubContext::class, $agencyContext);
    }

    private function fakeUser(): User
    {
        return new User([
            'id' => 990001,
            'name' => 'Locked Domain User',
            'email' => 'locked-domain-user@example.test',
            'role' => 'user',
            'block' => 'no',
            'littlelink_name' => 'locked-domain-user',
            'theme' => 'default',
        ]);
    }
}
