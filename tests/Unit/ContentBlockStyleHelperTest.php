<?php

namespace Tests\Unit;

use Tests\TestCase;

class ContentBlockStyleHelperTest extends TestCase
{
    public function test_normalizes_legacy_aliases_to_canonical_styles(): void
    {
        $this->assertSame('glass', normalizeContentBlockStyle('soft', 'clean'));
        $this->assertSame('glass', normalizeContentBlockStyle('accent', 'clean'));
        $this->assertSame('clean', normalizeContentBlockStyle('ghost', 'glass'));
        $this->assertSame('clean', normalizeContentBlockStyle('minimal', 'glass'));
        $this->assertSame('bold', normalizeContentBlockStyle('BOLD', 'clean'));
    }

    public function test_invalid_style_falls_back_to_default(): void
    {
        $this->assertSame('glass', normalizeContentBlockStyle('unknown', 'glass'));
        $this->assertSame('clean', normalizeContentBlockStyle(null, 'clean'));
        $this->assertSame('glass', normalizeContentBlockStyle('unknown', 'invalid-default'));
    }

    public function test_validation_inputs_include_canonical_and_legacy_values(): void
    {
        $inputs = contentBlockStyleValidInputs();

        $this->assertContains('clean', $inputs);
        $this->assertContains('glass', $inputs);
        $this->assertContains('bold', $inputs);
        $this->assertContains('soft', $inputs);
        $this->assertContains('ghost', $inputs);
        $this->assertContains('accent', $inputs);
        $this->assertContains('minimal', $inputs);
        $this->assertStringStartsWith('in:', contentBlockStyleInRule());
    }
}
