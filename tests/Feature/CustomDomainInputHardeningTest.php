<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agency\AgencyHubContext;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Modules\Tiers\Services\SubscriptionManager;
use Tests\TestCase;

class CustomDomainInputHardeningTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_agency_settings_domain_input_has_suggestion_hardening_attributes(): void
    {
        $user = $this->fakeUser();
        $this->be($user);
        $this->mockPremiumCustomDomainState($user, false);

        $html = view('modules.CustomDomains.views.agency-settings', [
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('id="agency-settings-input"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
        $this->assertStringContainsString('aria-autocomplete="none"', $html);
        $this->assertStringContainsString('data-lpignore="true"', $html);
        $this->assertStringContainsString('data-1p-ignore="true"', $html);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('onfocus="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('onpointerdown="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('ontouchstart="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('onblur="this.setAttribute(\'readonly\', \'readonly\')"', $html);
    }

    public function test_custom_domains_heading_is_domain_for_non_agency_accounts(): void
    {
        $user = $this->fakeUser();
        $this->be($user);
        $this->mockPremiumCustomDomainState($user, false);

        $html = view('modules.CustomDomains.views.agency-settings', [
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertMatchesRegularExpression('/<h3[^>]*>\s*Domain\s*<\/h3>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<h3[^>]*>\s*Branding\s*<\/h3>/', $html);
    }

    public function test_custom_domains_heading_stays_branding_for_agency_accounts(): void
    {
        $user = $this->fakeUser();
        $this->be($user);
        $this->mockPremiumCustomDomainState($user, true);

        $html = view('modules.CustomDomains.views.agency-settings', [
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertMatchesRegularExpression('/<h3[^>]*>\s*Branding\s*<\/h3>/', $html);
    }

    public function test_custom_domains_dns_instructions_are_translated_and_provider_neutral(): void
    {
        app()->setLocale('de');

        $user = $this->fakeUser();
        $this->be($user);
        $this->mockPremiumCustomDomainState($user, false);

        $html = view('modules.CustomDomains.views.agency-settings', [
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('Eigene Domain einrichten', $html);
        $this->assertStringContainsString('Nutze diese empfohlene Eingabe in Wayvio:', $html);
        $this->assertStringContainsString('Lege bei deinem Domain-Anbieter einen CNAME-Eintrag an:', $html);
        $this->assertStringContainsString('www.deinedomain.de', $html);
        $this->assertStringContainsString('Nutze deinedomain.de nur, wenn dein DNS-Anbieter ALIAS, ANAME oder CNAME-Flattening für Root-Domains unterstützt.', $html);
        $this->assertStringContainsString('customers.wayvio.de', $html);
        $this->assertStringContainsString('Speichere die Domain in Wayvio. Der erste Status kann pending bleiben, während DNS und TLS vorbereitet werden.', $html);
        $this->assertStringContainsString('Klicke in Wayvio auf Prüfen, nachdem DNS übertragen wurde.', $html);
        $this->assertStringContainsString('leite deinedomain.de dorthin um', $html);
        $this->assertStringContainsString('Entferne bestehende A-, AAAA- oder CNAME-Einträge für denselben Host.', $html);
        $this->assertStringNotContainsString('Cloudflare', $html);
    }

    public function test_hub_domain_heading_and_dns_instructions_are_translated_and_provider_neutral(): void
    {
        app()->setLocale('de');

        $user = $this->fakeUser();
        $this->be($user);
        $this->mockPremiumCustomDomainState($user, true);

        $html = view('modules.CustomDomains.views.hub-domain')->render();

        $this->assertMatchesRegularExpression('/<h3[^>]*>\s*Hub-Domain\s*<\/h3>/', $html);
        $this->assertStringContainsString('Eigene Domain einrichten', $html);
        $this->assertStringContainsString('Nutze diese empfohlene Eingabe in Wayvio:', $html);
        $this->assertStringContainsString('Lege bei deinem Domain-Anbieter einen CNAME-Eintrag an:', $html);
        $this->assertStringContainsString('www.deinedomain.de', $html);
        $this->assertStringContainsString('Nutze deinedomain.de nur, wenn dein DNS-Anbieter ALIAS, ANAME oder CNAME-Flattening für Root-Domains unterstützt.', $html);
        $this->assertStringContainsString('customers.wayvio.de', $html);
        $this->assertStringContainsString('Speichere die Domain in Wayvio. Der erste Status kann pending bleiben, während DNS und TLS vorbereitet werden.', $html);
        $this->assertStringContainsString('Klicke in Wayvio auf Prüfen, nachdem DNS übertragen wurde.', $html);
        $this->assertStringContainsString('leite www.deinedomain.de dorthin um', $html);
        $this->assertStringContainsString('Entferne bestehende A-, AAAA- oder CNAME-Einträge für denselben Host.', $html);
        $this->assertStringNotContainsString('Cloudflare', $html);
    }

    public function test_hub_domain_input_has_suggestion_hardening_attributes(): void
    {
        $user = $this->fakeUser();
        $this->be($user);
        $this->mockPremiumCustomDomainState($user, false);

        $html = view('modules.CustomDomains.views.hub-domain')->render();

        $this->assertStringContainsString('id="hub-domain-input"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
        $this->assertStringContainsString('aria-autocomplete="none"', $html);
        $this->assertStringContainsString('data-lpignore="true"', $html);
        $this->assertStringContainsString('data-1p-ignore="true"', $html);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('onfocus="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('onpointerdown="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('ontouchstart="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('onblur="this.setAttribute(\'readonly\', \'readonly\')"', $html);
    }

    public function test_agency_branding_link_input_has_suggestion_hardening_attributes(): void
    {
        $user = $this->fakeUser();
        $this->be($user);
        $this->mockPremiumCustomDomainState($user, true);

        $html = view('modules.CustomDomains.views.agency-settings', [
            'errors' => new ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('id="branding-link"', $html);
        $this->assertMatchesRegularExpression('/name="branding_link_tenant_[1-9][0-9]*"/', $html);
        $this->assertStringContainsString('type="url"', $html);
        $this->assertMatchesRegularExpression('/autocomplete="section-tenant-[1-9][0-9]* url"/', $html);
        $this->assertStringContainsString('data-lpignore="true"', $html);
        $this->assertStringContainsString('aria-autocomplete="none"', $html);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('onfocus="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('onpointerdown="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('ontouchstart="this.removeAttribute(\'readonly\')"', $html);
        $this->assertStringContainsString('onblur="this.setAttribute(\'readonly\', \'readonly\')"', $html);
        $this->assertStringContainsString('id="branding-link-hidden"', $html);
        $this->assertStringContainsString('name="branding_link"', $html);
        $this->assertStringContainsString('id="remove-branding-asset-hidden"', $html);
        $this->assertStringContainsString('name="remove_branding_asset"', $html);
        $this->assertStringNotContainsString('value="null"', $html);
    }

    private function mockPremiumCustomDomainState(User $user, bool $isAgencyAccount): void
    {
        $subscriptionManager = Mockery::mock(SubscriptionManager::class)->shouldIgnoreMissing();
        $subscriptionManager
            ->shouldReceive('featureEnabled')
            ->andReturn(true);

        $agencyContext = Mockery::mock(AgencyHubContext::class)->shouldIgnoreMissing();
        $agencyContext
            ->shouldReceive('isAgencyAccount')
            ->andReturn($isAgencyAccount);
        $agencyContext
            ->shouldReceive('sidebarData')
            ->andReturn([
                'is_agency' => $isAgencyAccount,
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
            'id' => 990101,
            'name' => 'Domain Hardened User',
            'email' => 'domain-hardened-user@example.test',
            'role' => 'user',
            'block' => 'no',
            'littlelink_name' => 'domain-hardened-user',
            'theme' => 'default',
        ]);
    }
}
