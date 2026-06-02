<?php

namespace Tests\Unit;

use App\Services\Compliance\LegalAgreementTextService;
use Tests\TestCase;

class LegalAgreementTextServiceTest extends TestCase
{
    private ?string $agbFile = null;
    private ?string $agbEnFile = null;
    private ?string $privacyFile = null;
    private ?string $privacyEnFile = null;
    private ?string $imprintFile = null;
    private ?string $imprintEnFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agbFile = sys_get_temp_dir() . '/wayvio-legal-agb-' . uniqid('', true) . '.txt';
        file_put_contents($this->agbFile, "AGB Titel\n\nAbschnitt 1");
        $this->agbEnFile = sys_get_temp_dir() . '/wayvio-legal-agb-en-' . uniqid('', true) . '.txt';
        file_put_contents($this->agbEnFile, "AGB Title\n\nSection 1");
        $this->privacyFile = sys_get_temp_dir() . '/wayvio-legal-privacy-' . uniqid('', true) . '.txt';
        file_put_contents($this->privacyFile, "Datenschutz Titel\n\nAbschnitt P");
        $this->privacyEnFile = sys_get_temp_dir() . '/wayvio-legal-privacy-en-' . uniqid('', true) . '.txt';
        file_put_contents($this->privacyEnFile, "Privacy Title\n\nSection P");
        $this->imprintFile = sys_get_temp_dir() . '/wayvio-legal-imprint-' . uniqid('', true) . '.txt';
        file_put_contents($this->imprintFile, "Impressum Titel\n\nAbschnitt I");
        $this->imprintEnFile = sys_get_temp_dir() . '/wayvio-legal-imprint-en-' . uniqid('', true) . '.txt';
        file_put_contents($this->imprintEnFile, "Imprint Title\n\nSection I");

        config()->set('legal.agreements.agb.text_file', $this->agbFile);
        config()->set('legal.agreements.agb.text_file_en', $this->agbEnFile);
        config()->set('legal.documents.privacy.text_file', $this->privacyFile);
        config()->set('legal.documents.privacy.text_file_en', $this->privacyEnFile);
        config()->set('legal.documents.imprint.text_file', $this->imprintFile);
        config()->set('legal.documents.imprint.text_file_en', $this->imprintEnFile);
    }

    protected function tearDown(): void
    {
        if (is_string($this->agbFile) && is_file($this->agbFile)) {
            @unlink($this->agbFile);
        }
        if (is_string($this->agbEnFile) && is_file($this->agbEnFile)) {
            @unlink($this->agbEnFile);
        }
        if (is_string($this->privacyFile) && is_file($this->privacyFile)) {
            @unlink($this->privacyFile);
        }
        if (is_string($this->privacyEnFile) && is_file($this->privacyEnFile)) {
            @unlink($this->privacyEnFile);
        }
        if (is_string($this->imprintFile) && is_file($this->imprintFile)) {
            @unlink($this->imprintFile);
        }
        if (is_string($this->imprintEnFile) && is_file($this->imprintEnFile)) {
            @unlink($this->imprintEnFile);
        }

        parent::tearDown();
    }

    public function test_loads_text_and_hash_from_configured_file(): void
    {
        $service = app(LegalAgreementTextService::class);
        $result = $service->loadAgreementText('agb');

        $this->assertNotNull($result);
        $this->assertSame("AGB Titel\n\nAbschnitt 1", $result['text']);
        $this->assertSame($this->agbFile, $result['path']);
        $this->assertSame(hash('sha256', "AGB Titel\n\nAbschnitt 1"), $result['sha256']);
    }

    public function test_loads_locale_specific_file_when_english_locale_is_requested(): void
    {
        $service = app(LegalAgreementTextService::class);
        $result = $service->loadAgreementText('agb', 'en-US');

        $this->assertNotNull($result);
        $this->assertSame("AGB Title\n\nSection 1", $result['text']);
        $this->assertSame($this->agbEnFile, $result['path']);
        $this->assertSame(hash('sha256', "AGB Title\n\nSection 1"), $result['sha256']);
        $this->assertSame('en', $result['locale']);
    }

    public function test_returns_null_for_unsupported_agreement_type(): void
    {
        $service = app(LegalAgreementTextService::class);

        $this->assertNull($service->loadAgreementText('kontaktformular'));
    }

    public function test_loads_privacy_text_from_configured_privacy_file(): void
    {
        $service = app(LegalAgreementTextService::class);
        $result = $service->loadAgreementText('privacy');

        $this->assertNotNull($result);
        $this->assertSame("Datenschutz Titel\n\nAbschnitt P", $result['text']);
        $this->assertSame($this->privacyFile, $result['path']);
        $this->assertSame(hash('sha256', "Datenschutz Titel\n\nAbschnitt P"), $result['sha256']);
    }

    public function test_falls_back_to_default_file_when_localized_file_is_missing(): void
    {
        config()->set('legal.documents.privacy.text_file_en', '/tmp/wayvio-does-not-exist-' . uniqid('', true) . '.txt');

        $service = app(LegalAgreementTextService::class);
        $result = $service->loadAgreementText('privacy', 'en');

        $this->assertNotNull($result);
        $this->assertSame("Datenschutz Titel\n\nAbschnitt P", $result['text']);
        $this->assertSame($this->privacyFile, $result['path']);
        $this->assertNull($result['locale']);
    }

    public function test_loads_imprint_text_from_configured_file_and_translates_to_english_when_requested(): void
    {
        $service = app(LegalAgreementTextService::class);
        $defaultResult = $service->loadAgreementText('impressum');
        $englishResult = $service->loadAgreementText('impressum', 'en');

        $this->assertNotNull($defaultResult);
        $this->assertSame("Impressum Titel\n\nAbschnitt I", $defaultResult['text']);
        $this->assertSame($this->imprintFile, $defaultResult['path']);

        $this->assertNotNull($englishResult);
        $this->assertSame("Imprint Title\n\nSection I", $englishResult['text']);
        $this->assertSame($this->imprintEnFile, $englishResult['path']);
        $this->assertSame('en', $englishResult['locale']);
    }

    public function test_replaces_provider_placeholders_when_provider_config_is_set(): void
    {
        file_put_contents(
            $this->agbFile,
            "[VORNAME NACHNAME]\n[STRASSE NR, PLZ ORT]\n[EMAIL@DOMAIN.DE]"
        );

        config()->set('legal.provider.name', 'Wayvio Betreiber');
        config()->set('legal.provider.address', 'Musterstrasse 1, 12345 Musterstadt');
        config()->set('legal.provider.email', 'kontakt@wayvio.example');

        $service = app(LegalAgreementTextService::class);
        $result = $service->loadAgreementText('agb');

        $this->assertNotNull($result);
        $this->assertSame(
            "Wayvio Betreiber\nMusterstrasse 1, 12345 Musterstadt\nkontakt@wayvio.example",
            $result['text']
        );
        $this->assertSame(
            hash('sha256', "Wayvio Betreiber\nMusterstrasse 1, 12345 Musterstadt\nkontakt@wayvio.example"),
            $result['sha256']
        );
    }
}
