<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Forms\FormsAccess;
use App\Services\Forms\FormsClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class FormsSignedSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemory();
        $this->createTables();
        $this->insertHubUser(130564, 'hub-owner@example.com');

        config()->set('forms.turnstile_required', false);
        config()->set('forms.bot_protection.provider', 'none');
        config()->set('forms.bot_protection.required', false);
        config()->set('forms.cap.base_url', 'http://127.0.0.1:3030');
        config()->set('forms.cap.site_key', null);
        config()->set('forms.cap.secret', null);
        config()->set('forms.min_submit_seconds', 0);
        config()->set('services.captcha.provider', null);
        config()->set('services.captcha.secret', null);
        config()->set('services.captcha.sitekey', null);
        config()->set('session.driver', 'array');
        config()->set('cache.default', 'array');
        $this->withoutMiddleware(ThrottleRequests::class);

        Mail::fake();
        app()->setLocale('de');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_relative_signed_submit_accepts_custom_host(): void
    {
        $this->bindFormsAccessAllow(130564);
        $this->bindFormsClientSuccess();

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'custom.example.test'])
            ->from('http://custom.example.test/50user/imprint')
            ->post($signedPath, $this->validPayload());

        $response->assertStatus(302);
        $response->assertSessionHas('forms_success_imprint_contact');
        $this->assertSame('/50user/imprint', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
        Mail::assertNothingSent();
    }

    public function test_expired_relative_signature_is_rejected_before_controller(): void
    {
        $this->bindFormsAccessAllow(130564);

        $client = Mockery::mock(FormsClient::class);
        $client->shouldNotReceive('createSubmission');
        $this->app->instance(FormsClient::class, $client);

        $expiredPath = URL::temporarySignedRoute('forms.submit', now()->subMinute(), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $response = $this->post($expiredPath, $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_submit_allows_missing_source_context_when_route_form_key_is_valid(): void
    {
        $this->bindFormsAccessAllow(130564);
        $this->bindFormsClientSuccess();

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $payload = $this->validPayload();
        unset($payload['source_context']);

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'custom.example.test'])
            ->from('http://custom.example.test/50user/imprint')
            ->post($signedPath, $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('forms_success_imprint_contact');
    }

    public function test_submit_allows_null_literal_source_context_when_route_form_key_is_valid(): void
    {
        $this->bindFormsAccessAllow(130564);
        $this->bindFormsClientSuccess();

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $payload = $this->validPayload();
        $payload['source_context'] = 'null';

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'custom.example.test'])
            ->from('http://custom.example.test/50user/imprint')
            ->post($signedPath, $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('forms_success_imprint_contact');
    }

    public function test_submit_allows_missing_started_at_when_captcha_token_is_present(): void
    {
        $this->bindFormsAccessAllow(130564);
        $this->bindFormsClientSuccess();

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $payload = $this->validPayload();
        unset($payload['_forms_started_at']);
        $payload['cf-turnstile-response'] = 'dummy-token';

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'custom.example.test'])
            ->from('http://custom.example.test/50user/imprint')
            ->post($signedPath, $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('forms_success_imprint_contact');
    }

    public function test_submit_accepts_turnstile_testing_secret_response_without_hostname_or_action_match(): void
    {
        config()->set('forms.turnstile_required', true);
        config()->set('forms.bot_protection.provider', 'turnstile');
        config()->set('forms.bot_protection.required', true);
        config()->set('services.captcha.provider', 'turnstile');
        config()->set('services.captcha.sitekeys.forms_imprint_contact', '1x00000000000000000000AA');
        config()->set('services.captcha.secrets.forms_imprint_contact', '1x0000000000000000000000000000000AA');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'example.com',
                'metadata' => [
                    'result_with_testing_key' => true,
                ],
            ], 200),
        ]);

        $this->bindFormsAccessAllow(130564);
        $this->bindFormsClientSuccess();

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $payload = $this->validPayload();
        $payload['cf-turnstile-response'] = 'testing-token';

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->from('http://localhost/50user/imprint')
            ->post($signedPath, $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('forms_success_imprint_contact');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request['secret'] === '1x0000000000000000000000000000000AA'
                && $request['response'] === 'testing-token';
        });
    }

    public function test_submit_accepts_valid_cap_token_on_custom_host(): void
    {
        $this->configureCapProtection();
        Http::fake([
            'http://127.0.0.1:3030/local-site-key/siteverify' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $this->bindFormsAccessAllow(130564);
        $this->bindFormsClientSuccess();

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $payload = $this->validPayload();
        $payload['cap-token'] = 'local-cap-token';

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'custom.example.test'])
            ->from('http://custom.example.test/50user/imprint')
            ->post($signedPath, $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('forms_success_imprint_contact');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:3030/local-site-key/siteverify'
                && $request['secret'] === 'local-secret'
                && $request['response'] === 'local-cap-token';
        });
    }

    public function test_submit_rejects_missing_cap_token_before_creating_submission(): void
    {
        $this->configureCapProtection();
        Http::fake();

        $this->bindFormsAccessAllow(130564);
        $client = Mockery::mock(FormsClient::class);
        $client->shouldNotReceive('createSubmission');
        $this->app->instance(FormsClient::class, $client);

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $response = $this
            ->from('http://custom.example.test/50user/imprint')
            ->post($signedPath, $this->validPayload());

        $response->assertStatus(302);
        $response->assertSessionHasErrors('captcha');
        Http::assertNothingSent();
    }

    public function test_submit_rejects_invalid_cap_token_before_creating_submission(): void
    {
        $this->configureCapProtection();
        Http::fake([
            'http://127.0.0.1:3030/local-site-key/siteverify' => Http::response([
                'success' => false,
                'error' => 'invalid-token',
            ], 200),
        ]);

        $this->bindFormsAccessAllow(130564);
        $client = Mockery::mock(FormsClient::class);
        $client->shouldNotReceive('createSubmission');
        $this->app->instance(FormsClient::class, $client);

        $signedPath = URL::temporarySignedRoute('forms.submit', now()->addMinutes(30), [
            'hub' => 130564,
            'formKey' => 'imprint_contact',
        ], false);

        $payload = $this->validPayload();
        $payload['cap-token'] = 'bad-token';

        $response = $this
            ->from('http://custom.example.test/50user/imprint')
            ->post($signedPath, $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('captcha');
    }

    public function test_embed_component_renders_relative_signed_action(): void
    {
        $this->bindFormsAccessAllow(130564);
        $this->app['view']->share('errors', new ViewErrorBag());

        $hubUser = User::query()->findOrFail(130564);

        $html = view('components.forms.embed', [
            'formKey' => 'imprint_contact',
            'hub' => $hubUser,
            'context' => 'imprint',
        ])->render();

        $this->assertStringContainsString('action="/forms/130564/imprint_contact/submit?expires=', $html);
        $this->assertStringContainsString('&amp;signature=', $html);
        $this->assertStringNotContainsString('action="http', $html);
        $this->assertStringNotContainsString('name="_wayvio_form_email"', $html);
        $this->assertStringNotContainsString('data-wayvio-defer-captcha', $html);
        $this->assertStringContainsString('type="text" name="email"', $html);
        $this->assertStringContainsString('inputmode="email"', $html);
        $this->assertStringNotContainsString('type="email" name="email"', $html);
    }

    public function test_embed_component_renders_cap_widget_when_forms_provider_is_cap(): void
    {
        $this->configureCapProtection();
        $this->bindFormsAccessAllow(130564);
        $this->app['view']->share('errors', new ViewErrorBag());

        $hubUser = User::query()->findOrFail(130564);

        $html = view('components.forms.embed', [
            'formKey' => 'hub_contact_block',
            'hub' => $hubUser,
            'context' => 'hub_block',
        ])->render();

        $this->assertStringContainsString('<cap-widget', $html);
        $this->assertStringContainsString('data-cap-api-endpoint="http://127.0.0.1:3030/local-site-key/"', $html);
        $this->assertStringContainsString('src="http://127.0.0.1:3030/assets/widget.js"', $html);
        $this->assertStringNotContainsString('cf-turnstile', $html);
    }

    public function test_embed_component_shows_basic_tier_notice_when_imprint_form_is_locked(): void
    {
        $this->bindFormsAccessDeny();
        $this->app['view']->share('errors', new ViewErrorBag());

        $hubUser = User::query()->findOrFail(130564);

        $html = view('components.forms.embed', [
            'formKey' => 'imprint_contact',
            'hub' => $hubUser,
            'context' => 'imprint',
        ])->render();

        $this->assertStringContainsString('Kontaktformular verfügbar ab Basic.', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_embed_component_shows_basic_tier_notice_when_hub_block_form_is_locked(): void
    {
        $this->bindFormsAccessDeny();
        $this->app['view']->share('errors', new ViewErrorBag());

        $hubUser = User::query()->findOrFail(130564);

        $html = view('components.forms.embed', [
            'formKey' => 'hub_contact_block',
            'hub' => $hubUser,
            'context' => 'hub_block',
        ])->render();

        $this->assertStringContainsString('Kontaktformular verfügbar ab Basic.', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    private function bindFormsAccessAllow(int $ownerUserId): void
    {
        $access = Mockery::mock(FormsAccess::class);
        $access->shouldReceive('formsAllowedForHub')->andReturnTrue();
        $access->shouldReceive('tenantOwnerUserIdForHub')->andReturn($ownerUserId);
        $access->shouldReceive('actorUserIdForPublicHub')->andReturn($ownerUserId);
        $access->shouldReceive('tierLevelForHub')->andReturn('tier2');
        $access->shouldReceive('retentionDaysForHub')->andReturn(180);

        $this->app->instance(FormsAccess::class, $access);
    }

    private function bindFormsAccessDeny(): void
    {
        $access = Mockery::mock(FormsAccess::class);
        $access->shouldReceive('formsAllowedForHub')->andReturnFalse();
        $this->app->instance(FormsAccess::class, $access);
    }

    private function bindFormsClientSuccess(): MockInterface
    {
        $client = Mockery::mock(FormsClient::class);
        $client->shouldReceive('createSubmission')
            ->once()
            ->andReturn(['ok' => true]);

        $this->app->instance(FormsClient::class, $client);

        return $client;
    }

    private function configureCapProtection(): void
    {
        config()->set('forms.bot_protection.provider', 'cap');
        config()->set('forms.bot_protection.required', true);
        config()->set('forms.cap.base_url', 'http://127.0.0.1:3030');
        config()->set('forms.cap.site_key', 'local-site-key');
        config()->set('forms.cap.secret', 'local-secret');
    }

    /**
     * @return array<string,string>
     */
    private function validPayload(): array
    {
        return [
            'source_context' => 'imprint',
            '_forms_started_at' => Crypt::encryptString((string) (time() - 20)),
            'name' => 'Manual QA',
            'email' => 'qa@gmail.com',
            'subject' => 'Imprint form test',
            'message' => 'Production-like submit check',
            'wayvio_company' => '',
        ];
    }

    private function useSqliteInMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('littlelink_name')->nullable()->unique();
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('theme')->nullable();
            $table->string('locale')->nullable();
            $table->timestamps();
        });

        Schema::create('user_custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('domain');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    private function insertHubUser(int $id, string $email): void
    {
        $now = now();
        DB::table('users')->insert([
            'id' => $id,
            'name' => '50user',
            'email' => $email,
            'password' => 'x',
            'littlelink_name' => '50user',
            'role' => 'user',
            'block' => 'no',
            'theme' => 'default',
            'locale' => 'de',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
