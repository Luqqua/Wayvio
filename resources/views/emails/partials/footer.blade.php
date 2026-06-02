@php
    $mailFooterLocale = strtolower((string) app()->getLocale());
    $mailFooterIsEnglish = str_starts_with($mailFooterLocale, 'en');
    $mailFooterSupportEmail = config('billing.notifications.support_email') ?: env('BILLING_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@example.com'));
    $mailFooterImprintUrl = url('/imprint');
    $mailFooterPrivacyUrl = url('/privacy');
@endphp

<div style="margin-top: 32px; padding-top: 18px; border-top: 1px solid #e8e5df; color: #6b7280; font-size: 12px; line-height: 1.6;">
    @if ($mailFooterIsEnglish)
        <p style="margin: 0 0 8px;">This email was sent automatically by Wayvio. Please do not reply directly to this message.</p>
        <p style="margin: 0 0 8px;">For questions, contact us at <a href="mailto:{{ $mailFooterSupportEmail }}" style="color: #4b5563;">{{ $mailFooterSupportEmail }}</a>.</p>
        <p style="margin: 0;">
            Wayvio<br>
            <a href="{{ $mailFooterImprintUrl }}" style="color: #4b5563;">Legal notice</a> ·
            <a href="{{ $mailFooterPrivacyUrl }}" style="color: #4b5563;">Privacy policy</a>
        </p>
    @else
        <p style="margin: 0 0 8px;">Diese E-Mail wurde automatisch von Wayvio gesendet. Bitte antworte nicht direkt auf diese Nachricht.</p>
        <p style="margin: 0 0 8px;">Bei Fragen erreichst du uns unter <a href="mailto:{{ $mailFooterSupportEmail }}" style="color: #4b5563;">{{ $mailFooterSupportEmail }}</a>.</p>
        <p style="margin: 0;">
            Wayvio<br>
            <a href="{{ $mailFooterImprintUrl }}" style="color: #4b5563;">Impressum</a> ·
            <a href="{{ $mailFooterPrivacyUrl }}" style="color: #4b5563;">Datenschutz</a>
        </p>
    @endif
</div>
