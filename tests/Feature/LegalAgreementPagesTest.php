<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalAgreementPagesTest extends TestCase
{
    private ?string $agbFile = null;
    private ?string $agbEnFile = null;
    private ?string $avvFile = null;
    private ?string $avvEnFile = null;
    private ?string $privacyFile = null;
    private ?string $privacyEnFile = null;
    private ?string $imprintFile = null;
    private ?string $imprintEnFile = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        $this->agbFile = sys_get_temp_dir() . '/wayvio-legal-page-agb-' . uniqid('', true) . '.txt';
        $this->agbEnFile = sys_get_temp_dir() . '/wayvio-legal-page-agb-en-' . uniqid('', true) . '.txt';
        $this->avvFile = sys_get_temp_dir() . '/wayvio-legal-page-avv-' . uniqid('', true) . '.txt';
        $this->avvEnFile = sys_get_temp_dir() . '/wayvio-legal-page-avv-en-' . uniqid('', true) . '.txt';
        $this->privacyFile = sys_get_temp_dir() . '/wayvio-legal-page-privacy-' . uniqid('', true) . '.txt';
        $this->privacyEnFile = sys_get_temp_dir() . '/wayvio-legal-page-privacy-en-' . uniqid('', true) . '.txt';
        $this->imprintFile = sys_get_temp_dir() . '/wayvio-legal-page-imprint-' . uniqid('', true) . '.txt';
        $this->imprintEnFile = sys_get_temp_dir() . '/wayvio-legal-page-imprint-en-' . uniqid('', true) . '.txt';

        file_put_contents($this->agbFile, "AGB Volltext Test\nAbschnitt A");
        file_put_contents($this->agbEnFile, "AGB Fulltext Test\nSection A");
        file_put_contents($this->avvFile, "AVV Volltext Test\nAbschnitt B");
        file_put_contents($this->avvEnFile, "AVV Fulltext Test\nSection B");
        file_put_contents($this->privacyFile, "Datenschutz Volltext Test\nAbschnitt D");
        file_put_contents($this->privacyEnFile, "Privacy Fulltext Test\nSection D");
        file_put_contents($this->imprintFile, "Impressum Volltext Test\nAbschnitt I");
        file_put_contents($this->imprintEnFile, "Imprint Fulltext Test\nSection I");

        config()->set('legal.agreements.agb.version', '0.6');
        config()->set('legal.agreements.avv.version', '0.6');
        config()->set('legal.agreements.agb.text_file', $this->agbFile);
        config()->set('legal.agreements.agb.text_file_en', $this->agbEnFile);
        config()->set('legal.agreements.avv.text_file', $this->avvFile);
        config()->set('legal.agreements.avv.text_file_en', $this->avvEnFile);
        config()->set('legal.documents.privacy.version', '2026-03');
        config()->set('legal.documents.privacy.text_file', $this->privacyFile);
        config()->set('legal.documents.privacy.text_file_en', $this->privacyEnFile);
        config()->set('legal.documents.imprint.version', '2026-03');
        config()->set('legal.documents.imprint.text_file', $this->imprintFile);
        config()->set('legal.documents.imprint.text_file_en', $this->imprintEnFile);
    }

    protected function tearDown(): void
    {
        foreach ([$this->agbFile, $this->agbEnFile, $this->avvFile, $this->avvEnFile, $this->privacyFile, $this->privacyEnFile, $this->imprintFile, $this->imprintEnFile] as $file) {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_agb_page_renders_full_document_text_from_configured_file(): void
    {
        $response = $this->get('/pages/agb');

        $response->assertOk();
        $response->assertSee('AGB Volltext Test');
        $response->assertSee('Abschnitt A');
        $response->assertSee(hash('sha256', "AGB Volltext Test\nAbschnitt A"));
    }

    public function test_avv_page_renders_full_document_text_from_configured_file(): void
    {
        $response = $this->get('/pages/avv');

        $response->assertOk();
        $response->assertSee('AVV Volltext Test');
        $response->assertSee('Abschnitt B');
        $response->assertSee(hash('sha256', "AVV Volltext Test\nAbschnitt B"));
    }

    public function test_privacy_page_renders_full_document_text_from_configured_file(): void
    {
        $response = $this->get(route('pagesPrivacy'));

        $response->assertOk();
        $response->assertSee('Datenschutz Volltext Test');
        $response->assertSee('Abschnitt D');
        $response->assertSee(hash('sha256', "Datenschutz Volltext Test\nAbschnitt D"));
    }

    public function test_imprint_page_renders_full_document_text_from_configured_file(): void
    {
        $response = $this->get(route('pagesImprint'));

        $response->assertOk();
        $response->assertSee('Impressum Volltext Test');
        $response->assertSee('Abschnitt I');
        $response->assertSee(hash('sha256', "Impressum Volltext Test\nAbschnitt I"));
    }

    public function test_legal_pages_render_english_document_when_legal_lang_query_is_en(): void
    {
        $agbResponse = $this->get('/pages/agb?legal_lang=en');
        $agbResponse->assertOk();
        $agbResponse->assertSee('AGB Fulltext Test');
        $agbResponse->assertSee(hash('sha256', "AGB Fulltext Test\nSection A"));
        $agbResponse->assertSee('href="/pages/agb?legal_lang=en"', false);
        $agbResponse->assertSee('href="/pages/agb?legal_lang=de"', false);

        $avvResponse = $this->get('/pages/avv?legal_lang=en');
        $avvResponse->assertOk();
        $avvResponse->assertSee('AVV Fulltext Test');

        $privacyResponse = $this->get(route('pagesPrivacy', ['legal_lang' => 'en']));
        $privacyResponse->assertOk();
        $privacyResponse->assertSee('Privacy Fulltext Test');
        $privacyResponse->assertSee(hash('sha256', "Privacy Fulltext Test\nSection D"));

        $imprintResponse = $this->get(route('pagesImprint', ['legal_lang' => 'en']));
        $imprintResponse->assertOk();
        $imprintResponse->assertSee('Imprint Fulltext Test');
        $imprintResponse->assertSee(hash('sha256', "Imprint Fulltext Test\nSection I"));
    }

    public function test_privacy_alias_routes_redirect_to_canonical_privacy_route(): void
    {
        $this->get('/pages/datenschutz')
            ->assertRedirect(route('pagesPrivacy'));

        $this->get('/pages/datenschutzerklaerung')
            ->assertRedirect(route('pagesPrivacy'));

        $this->get('/pages/datenschutz?legal_lang=en')
            ->assertRedirect(route('pagesPrivacy', ['legal_lang' => 'en']));

        $this->get('/pages/datenschutzerklaerung?legal_lang=en')
            ->assertRedirect(route('pagesPrivacy', ['legal_lang' => 'en']));

        $this->get('/pages/imprint')
            ->assertRedirect(route('pagesImprint'));

        $this->get('/pages/imprint?legal_lang=en')
            ->assertRedirect(route('pagesImprint', ['legal_lang' => 'en']));
    }
}
