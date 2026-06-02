<?php

namespace App\Notifications;

use App\Support\EmailLocaleResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChanged extends Notification
{
    use Queueable;

    public function __construct(
        protected string $oldEmail,
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
            ->subject($isEnglish ? 'Email address changed' : 'E-Mail-Adresse geändert')
            ->line($isEnglish
                ? "The email address of your {$appName} account was changed from {$this->oldEmail} to {$this->newEmail}."
                : "Die E-Mail-Adresse deines {$appName}-Kontos wurde von {$this->oldEmail} auf {$this->newEmail} geändert.")
            ->line($isEnglish
                ? 'If this was not you, please reset your password immediately.'
                : 'Wenn du das nicht warst, setze bitte sofort dein Passwort zurück.');
    }
}
