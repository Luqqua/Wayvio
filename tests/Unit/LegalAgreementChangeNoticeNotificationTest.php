<?php

namespace Tests\Unit;

use App\Notifications\LegalAgreementChangeNoticeNotification;
use Carbon\Carbon;
use Tests\TestCase;

class LegalAgreementChangeNoticeNotificationTest extends TestCase
{
    public function test_uses_english_mail_copy_and_english_legal_url_for_english_account_locale(): void
    {
        config()->set('app.supported_locales', ['de', 'en']);
        config()->set('app.timezone', 'Europe/Berlin');
        config()->set('legal.provider.email', 'legal@wayvio.example');

        $notification = new LegalAgreementChangeNoticeNotification(
            agreementType: 'agb',
            targetVersion: 'v1.2',
            effectiveAt: Carbon::parse('2026-04-26 00:00:00', 'Europe/Berlin'),
            documentUrl: 'https://example.test/pages/agb'
        );

        $mail = $notification->toMail((object) [
            'locale' => 'en-US',
            'last_login_locale' => null,
        ]);

        $this->assertSame('Important AGB update (version v1.2)', $mail->subject);
        $this->assertSame('View AGB', $mail->actionText);
        $this->assertStringContainsString('legal_lang=en', (string) $mail->actionUrl);
    }

    public function test_uses_german_mail_copy_without_english_legal_param_when_locale_is_not_english(): void
    {
        config()->set('app.supported_locales', ['de', 'en']);
        config()->set('app.timezone', 'Europe/Berlin');

        $notification = new LegalAgreementChangeNoticeNotification(
            agreementType: 'agb',
            targetVersion: 'v1.2',
            effectiveAt: Carbon::parse('2026-04-26 00:00:00', 'Europe/Berlin'),
            documentUrl: 'https://example.test/pages/agb'
        );

        $mail = $notification->toMail((object) [
            'locale' => 'de',
            'last_login_locale' => null,
        ]);

        $this->assertSame('Wichtige Änderung AGB (Version v1.2)', $mail->subject);
        $this->assertSame('AGB ansehen', $mail->actionText);
        $this->assertSame('https://example.test/pages/agb', $mail->actionUrl);
    }
}
