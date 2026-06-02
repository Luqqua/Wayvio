<?php

namespace Tests\Feature;

use App\Services\Compliance\ComplianceAuditService;
use App\Services\Security\CaptchaVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Partners\Services\PartnerManager;
use Tests\TestCase;

class RegistrationLocalePersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('littlelink_name')->unique();
            $table->string('password');
            $table->string('role')->default('user');
            $table->string('block')->default('no');
            $table->string('theme')->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('last_login_locale', 10)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->longText('data')->nullable();
            $table->timestamps();
        });

        config()->set('auth.allow_registration', true);
        config()->set('auth.register_auth_middleware', null);
        config()->set('app.supported_locales', ['en', 'de']);
        config()->set('app.fallback_locale', 'de');

        $partnerManager = $this->createMock(PartnerManager::class);
        $partnerManager->method('validateSignupCodeOrThrow')->willReturnCallback(static function (): void {
        });
        $partnerManager->method('applyAttributionForNewUser')->willReturnCallback(static function (): void {
        });
        $partnerManager->method('captureReferralCode')->willReturnCallback(static function (): void {
        });
        $this->app->instance(PartnerManager::class, $partnerManager);

        $captchaVerifier = $this->createMock(CaptchaVerifier::class);
        $captchaVerifier->method('provider')->willReturn(null);
        $captchaVerifier->method('validate')->willReturnCallback(static function (): void {
        });
        $this->app->instance(CaptchaVerifier::class, $captchaVerifier);

        $complianceAudit = $this->createMock(ComplianceAuditService::class);
        $complianceAudit->method('recordAgreementAcceptance')->willReturnCallback(static function (): void {
        });
        $complianceAudit->method('record')->willReturnCallback(static function (): void {
        });
        $this->app->instance(ComplianceAuditService::class, $complianceAudit);
    }

    public function test_registration_persists_german_account_locale_as_public_default(): void
    {
        $response = $this->post('/register', [
            'name' => 'Max Beispiel',
            'littlelink_name' => 'max-beispiel',
            'email' => 'max@example.test',
            'password' => 'SehrSicher123',
            'password_confirmation' => 'SehrSicher123',
            'accept_agb' => '1',
            'accept_avv' => '1',
        ], [
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en;q=0.8',
        ]);

        $response->assertRedirect(url('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'max@example.test',
            'locale' => 'de',
            'last_login_locale' => 'de',
        ]);
    }
}
