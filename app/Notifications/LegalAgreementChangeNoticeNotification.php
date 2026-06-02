<?php

namespace App\Notifications;

use App\Support\EmailLocaleResolver;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LegalAgreementChangeNoticeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $agreementType,
        private readonly string $targetVersion,
        private readonly Carbon $effectiveAt,
        private readonly string $documentUrl,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = EmailLocaleResolver::resolve(
            data_get($notifiable, 'locale'),
            data_get($notifiable, 'last_login_locale')
        );
        $isEnglish = EmailLocaleResolver::isEnglish($locale);

        $label = strtolower(trim($this->agreementType)) === 'avv'
            ? 'AVV'
            : 'AGB';
        $effectiveDate = $this->effectiveAt->copy()->timezone(config('app.timezone', 'UTC'));
        $supportEmail = trim((string) config('legal.provider.email', 'support@example.com'));
        $documentUrl = $this->documentUrlForLocale($locale);

        $mail = new MailMessage();

        if (!$isEnglish) {
            $mail->subject("Wichtige Änderung {$label} (Version {$this->targetVersion})")
                ->greeting('Hallo,')
                ->line("wir informieren dich über eine Änderung unserer {$label} in Version {$this->targetVersion}.")
                ->line('Die geänderten Bedingungen treten am ' . $effectiveDate->format('d.m.Y') . ' in Kraft.')
                ->line('Wenn du nicht innerhalb von 30 Tagen Widerspruch einlegst, gelten die Änderungen als angenommen.')
                ->line('Bei Widerspruch kannst du den Vertrag bis zum Inkrafttreten der Änderungen beenden.')
                ->action(strtoupper($label) . ' ansehen', $documentUrl)
                ->line('Bei Fragen erreichst du uns unter: ' . $supportEmail);

            return $mail;
        }

        $mail->subject("Important {$label} update (version {$this->targetVersion})")
            ->greeting('Hi,')
            ->line("This is a notice about an upcoming {$label} update to version {$this->targetVersion}.")
            ->line('The updated terms become effective on ' . $effectiveDate->format('Y-m-d') . '.')
            ->line('If you do not object within 30 days, the changes are considered accepted.')
            ->line('If you object, you may terminate your contract before the effective date.')
            ->action('View ' . strtoupper($label), $documentUrl)
            ->line('Questions? Contact us at: ' . $supportEmail);

        return $mail;
    }

    private function documentUrlForLocale(string $locale): string
    {
        if (!EmailLocaleResolver::isEnglish($locale)) {
            return $this->documentUrl;
        }

        return $this->withQueryParam($this->documentUrl, 'legal_lang', 'en');
    }

    private function withQueryParam(string $url, string $key, string $value): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query[$key] = $value;

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        $queryString = http_build_query($query);
        $querySegment = $queryString !== '' ? '?' . $queryString : '';

        return $scheme . $auth . $host . $port . $path . $querySegment . $fragment;
    }
}
