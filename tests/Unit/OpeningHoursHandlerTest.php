<?php

namespace Tests\Unit;

use Tests\TestCase;

class OpeningHoursHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('blocks/opening_hours/handler.php');
    }

    public function test_sanitize_returns_expected_default_shape(): void
    {
        $sanitized = openingHoursSanitizeData([]);

        $this->assertSame(openingHoursResolveLocale(null), $sanitized['locale']);
        $this->assertSame('clean', $sanitized['preset']);
        $this->assertTrue($sanitized['group_days']);
        $this->assertArrayNotHasKey('highlight_today', $sanitized);

        $this->assertCount(7, $sanitized['days']);
        $this->assertTrue($sanitized['days']['monday']['open']);
        $this->assertArrayNotHasKey('all_day', $sanitized['days']['monday']);
        $this->assertSame([['09:00', '18:00']], $sanitized['days']['monday']['slots']);
        $this->assertFalse($sanitized['days']['wednesday']['open']);
        $this->assertSame([], $sanitized['days']['wednesday']['slots']);
    }

    public function test_closed_days_clear_slots_and_all_day_input_is_ignored(): void
    {
        $sanitized = openingHoursSanitizeData([
            'days' => [
                'monday' => [
                    'open' => '0',
                    'all_day' => '1',
                    'slots' => [
                        ['09:00', '18:00'],
                    ],
                ],
                'tuesday' => [
                    'open' => '1',
                    'all_day' => '1',
                    'slots' => [
                        ['09:00', '12:00'],
                        ['14:00', '18:00'],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($sanitized['days']['monday']['open']);
        $this->assertArrayNotHasKey('all_day', $sanitized['days']['monday']);
        $this->assertSame([], $sanitized['days']['monday']['slots']);

        $this->assertTrue($sanitized['days']['tuesday']['open']);
        $this->assertArrayNotHasKey('all_day', $sanitized['days']['tuesday']);
        $this->assertSame([
            ['09:00', '12:00'],
            ['14:00', '18:00'],
        ], $sanitized['days']['tuesday']['slots']);
    }

    public function test_slot_normalization_keeps_only_two_valid_ranges(): void
    {
        $sanitized = openingHoursSanitizeData([
            'days' => [
                'friday' => [
                    'open' => '1',
                    'slots' => [
                        ['08:00', '12:00'],
                        ['11:00', '10:00'],
                        ['13:00', '17:00'],
                        ['18:00', '22:00'],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            ['08:00', '12:00'],
            ['13:00', '17:00'],
        ], $sanitized['days']['friday']['slots']);
    }

    public function test_locale_is_normalized_and_falls_back_when_unsupported(): void
    {
        $supported = (array) config('app.supported_locales', []);
        $fallback = (string) config('app.fallback_locale', 'en');
        $preferred = $supported[0] ?? $fallback;
        $other = $supported[1] ?? $fallback;
        $defaultResolved = openingHoursResolveLocale(null);

        $this->assertSame($other, openingHoursSanitizeData(['locale' => strtoupper($other)])['locale']);
        $this->assertSame($preferred, openingHoursSanitizeData(['locale' => $preferred . '-CH'])['locale']);
        $this->assertSame($defaultResolved, openingHoursSanitizeData(['locale' => 'zz'])['locale']);
    }

    public function test_extract_locale_from_type_params_json(): void
    {
        $payload = json_encode([
            'opening_hours' => [
                'locale' => 'de',
                'days' => [],
            ],
        ]);

        $this->assertSame('de', openingHoursExtractLocaleFromTypeParams($payload));
        $this->assertNull(openingHoursExtractLocaleFromTypeParams('{}'));
        $this->assertNull(openingHoursExtractLocaleFromTypeParams(''));
    }

    public function test_raw_input_more_than_two_slots_is_detected(): void
    {
        $this->assertTrue(openingHoursRawHasMoreThanTwoSlots([
            'days' => [
                'thursday' => [
                    'slots' => [
                        ['08:00', '10:00'],
                        ['10:30', '13:00'],
                        ['14:00', '17:00'],
                    ],
                ],
            ],
        ]));

        $this->assertFalse(openingHoursRawHasMoreThanTwoSlots([
            'days' => [
                'thursday' => [
                    'slots' => [
                        ['08:00', '10:00'],
                        ['10:30', '13:00'],
                    ],
                ],
            ],
        ]));
    }
}
