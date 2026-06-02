<?php

namespace Tests\Unit;

use Tests\TestCase;

class SmartEmbedHandlerKitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('blocks/smart_embed/handler.php');
    }

    public function test_kit_share_url_is_parsed_and_canonicalized(): void
    {
        $parsed = smartEmbedParse('kit', 'https://tstst.kit.com/4b70f698c6?utm_source=newsletter');

        $this->assertIsArray($parsed);
        $this->assertSame('kit', $parsed['service']);
        $this->assertSame('tstst:4b70f698c6', $parsed['embed_id']);
        $this->assertSame('https://tstst.kit.com/4b70f698c6', $parsed['embed_url']);
        $this->assertSame('https://tstst.kit.com/4b70f698c6', $parsed['source_url']);
    }

    public function test_kit_index_script_url_is_supported(): void
    {
        $parsed = smartEmbedParse('kit', 'https://tstst.kit.com/4b70f698c6/index.js');

        $this->assertIsArray($parsed);
        $this->assertSame('tstst:4b70f698c6', $parsed['embed_id']);
        $this->assertSame('https://tstst.kit.com/4b70f698c6', $parsed['embed_url']);
    }

    public function test_kit_html_snippet_with_hosted_script_is_supported(): void
    {
        $snippet = '<script async data-uid="4b70f698c6" src="https://tstst.kit.com/4b70f698c6/index.js"></script>';

        $parsed = smartEmbedParse('kit', $snippet);

        $this->assertIsArray($parsed);
        $this->assertSame('tstst:4b70f698c6', $parsed['embed_id']);
        $this->assertSame('https://tstst.kit.com/4b70f698c6', $parsed['embed_url']);
    }

    public function test_kit_rejects_non_kit_host_even_with_embedded_kit_url_in_query(): void
    {
        $parsed = smartEmbedParse('kit', 'https://example.com/?next=https://tstst.kit.com/4b70f698c6');

        $this->assertNull($parsed);
    }

    public function test_kit_rejects_code_without_account_host_reference(): void
    {
        $snippet = '<script src="https://f.convertkit.com/ckjs/ck.5.js"></script><form data-uid="4b70f698c6" action="https://app.kit.com/forms/9265257/subscriptions"></form>';

        $parsed = smartEmbedParse('kit', $snippet);

        $this->assertNull($parsed);
    }

    public function test_kit_build_urls_from_stored_embed_id_uses_canonical_template(): void
    {
        $urls = smartEmbedBuildUrlsFromId('kit', 'tstst:4b70f698c6');

        $this->assertIsArray($urls);
        $this->assertSame('https://tstst.kit.com/4b70f698c6', $urls['embed_url']);
        $this->assertSame('https://tstst.kit.com/4b70f698c6', $urls['source_url']);
    }
}
