<?php

namespace App\Notifications;

use App\Support\EmailLocaleResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeVerification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $verificationUrl,
        protected string $newEmail,
        protected ?string $requestedLocale = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $resolvedLocale = EmailLocaleResolver::resolve(
            $this->requestedLocale,
            data_get($notifiable, 'last_login_locale')
        );
        $isEnglish = EmailLocaleResolver::isEnglish($resolvedLocale);
        $appName = config('app.name');

        return (new MailMessage)
            ->subject($isEnglish ? 'Confirm your new email address' : 'Bestätige deine neue E-Mail-Adresse')
            ->line($isEnglish
                ? "We received a request to change the email address of your {$appName} account to {$this->newEmail}."
                : "Wir haben eine Anfrage erhalten, die E-Mail-Adresse deines {$appName}-Kontos auf {$this->newEmail} zu ändern.")
            ->line($isEnglish
                ? 'Your current email address will stay active until you confirm this new address.'
                : 'Deine aktuelle E-Mail-Adresse bleibt aktiv, bis du die neue Adresse bestätigst.')
            ->action($isEnglish ? 'Confirm email change' : 'E-Mail-Änderung bestätigen', $this->verificationUrl)
            ->line($isEnglish
                ? 'This confirmation link expires in 60 minutes.'
                : 'Dieser Bestätigungslink ist 60 Minuten gültig.')
            ->line($isEnglish
                ? 'If you did not request this change, you can ignore this email.'
                : 'Wenn du diese Änderung nicht angefordert hast, kannst du diese E-Mail ignorieren.');
    }
}
