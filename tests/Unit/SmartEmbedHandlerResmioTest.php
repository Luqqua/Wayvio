<?php

namespace Tests\Unit;

use Tests\TestCase;

class SmartEmbedHandlerResmioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('blocks/smart_embed/handler.php');
    }

    public function test_resmio_booking_url_is_parsed_and_canonicalized(): void
    {
        $parsed = smartEmbedParse('resmio_booking', 'https://app.resmio.com/testete/widget?lang=de');

        $this->assertIsArray($parsed);
        $this->assertSame('resmio_booking', $parsed['service']);
        $this->assertSame('testete', $parsed['embed_id']);
        $this->assertSame('https://app.resmio.com/testete/widget', $parsed['embed_url']);
        $this->assertSame('https://app.resmio.com/testete/widget', $parsed['source_url']);
    }

    public function test_resmio_menu_url_is_parsed_and_canonicalized(): void
    {
        $parsed = smartEmbedParse('resmio_menu', 'https://app.resmio.com/testete/menu-widget?lang=de');

        $this->assertIsArray($parsed);
        $this->assertSame('resmio_menu', $parsed['service']);
        $this->assertSame('testete', $parsed['embed_id']);
        $this->assertSame('https://app.resmio.com/testete/menu-widget', $parsed['embed_url']);
        $this->assertSame('https://app.resmio.com/testete/menu-widget', $parsed['source_url']);
    }

    public function test_resmio_booking_html_snippet_is_supported(): void
    {
        $snippet = '<div id="resmio-testete"></div><script src="//static.resmio.com/static/de/widget.js#id=testete&height=460&width=330&fontSize=14px"></script>';
        $parsed = smartEmbedParse('resmio_booking', $snippet);

        $this->assertIsArray($parsed);
        $this->assertSame('resmio_booking', $parsed['service']);
        $this->assertSame('testete', $parsed['embed_id']);
    }

    public function test_resmio_menu_html_snippet_is_supported(): void
    {
        $snippet = '<script data-resmio-menu="testete"></script>';
        $parsed = smartEmbedParse('resmio_menu', $snippet);

        $this->assertIsArray($parsed);
        $this->assertSame('resmio_menu', $parsed['service']);
        $this->assertSame('testete', $parsed['embed_id']);
    }

    public function test_resmio_rejects_wrong_widget_type_for_selected_service(): void
    {
        $bookingFromMenuUrl = smartEmbedParse('resmio_booking', 'https://app.resmio.com/testete/menu-widget');
        $menuFromBookingUrl = smartEmbedParse('resmio_menu', 'https://app.resmio.com/testete/widget');

        $this->assertNull($bookingFromMenuUrl);
        $this->assertNull($menuFromBookingUrl);
    }

    public function test_resmio_build_urls_from_embed_id(): void
    {
        $bookingUrls = smartEmbedBuildUrlsFromId('resmio_booking', 'testete');
        $menuUrls = smartEmbedBuildUrlsFromId('resmio_menu', 'testete');

        $this->assertIsArray($bookingUrls);
        $this->assertSame('https://app.resmio.com/testete/widget', $bookingUrls['embed_url']);
        $this->assertSame('https://app.resmio.com/testete/widget', $bookingUrls['source_url']);

        $this->assertIsArray($menuUrls);
        $this->assertSame('https://app.resmio.com/testete/menu-widget', $menuUrls['embed_url']);
        $this->assertSame('https://app.resmio.com/testete/menu-widget', $menuUrls['source_url']);
    }
}
