<?php

namespace Tests\Unit;

use App\Services\Templates\TemplateCatalogService;
use Tests\TestCase;

class TemplateCatalogServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('template-catalog.enabled', true);
        config()->set('template-catalog.include_uncatalogued', false);
        config()->set('template-catalog.templates', [
            'alpha' => [
                'theme' => 'default',
                'label' => 'Alpha',
                'capabilities' => [
                    'custom_buttons' => false,
                    'text_accent' => true,
                ],
                'variants' => [
                    'default' => [
                        'label' => 'Default',
                        'css' => null,
                    ],
                ],
            ],
            'bravo' => [
                'theme' => 'Aurora',
                'label' => 'Bravo',
                'capabilities' => [
                    'custom_buttons' => true,
                    'text_accent' => false,
                ],
                'variants' => [
                    'default' => [
                        'label' => 'Default',
                        'css' => null,
                    ],
                    'emerald' => [
                        'label' => 'Emerald',
                        'css' => 'variants/emerald.css',
                    ],
                ],
            ],
        ]);

        TemplateCatalogService::flushCache();
    }

    protected function tearDown(): void
    {
        TemplateCatalogService::flushCache();
        parent::tearDown();
    }

    public function test_text_accent_capability_is_forced_by_custom_buttons_flag(): void
    {
        $service = app(TemplateCatalogService::class);

        $alpha = $service->template('alpha');
        $bravo = $service->template('bravo');

        $this->assertNotNull($alpha);
        $this->assertNotNull($bravo);

        $this->assertFalse((bool) ($alpha['capabilities']['custom_buttons'] ?? true));
        $this->assertFalse((bool) ($alpha['capabilities']['text_accent'] ?? true));

        $this->assertTrue((bool) ($bravo['capabilities']['custom_buttons'] ?? false));
        $this->assertTrue((bool) ($bravo['capabilities']['text_accent'] ?? false));
    }

    public function test_template_lookup_by_theme_is_case_insensitive(): void
    {
        $service = app(TemplateCatalogService::class);

        $template = $service->templateByTheme('AuRoRa');

        $this->assertSame('bravo', $template['id'] ?? null);
    }

    public function test_variant_normalization_falls_back_and_rejects_unsafe_css_paths(): void
    {
        config()->set('template-catalog.templates.bravo.variants', [
            'default' => [
                'label' => 'Default',
                'css' => null,
            ],
            'unsafe' => [
                'label' => 'Unsafe',
                'css' => '../secret.css',
            ],
        ]);

        TemplateCatalogService::flushCache();
        $service = app(TemplateCatalogService::class);

        $this->assertSame('default', $service->normalizeVariantId('bravo', 'not-existing'));

        $unsafeVariant = $service->variantForTemplateId('bravo', 'unsafe');
        $this->assertSame('unsafe', $unsafeVariant['id'] ?? null);
        $this->assertNull($unsafeVariant['css'] ?? null);
        $this->assertNull($unsafeVariant['css_url'] ?? null);
    }

    public function test_theme_normalization_neutralizes_path_traversal_fragments(): void
    {
        config()->set('template-catalog.templates', [
            'unsafe' => [
                'theme' => '../..',
                'label' => 'Unsafe',
                'variants' => [
                    'default' => [
                        'label' => 'Default',
                        'css' => null,
                    ],
                ],
            ],
        ]);

        TemplateCatalogService::flushCache();
        $service = app(TemplateCatalogService::class);

        $template = $service->template('unsafe');
        $this->assertSame('default', strtolower((string) ($template['theme'] ?? '')));
    }
}
