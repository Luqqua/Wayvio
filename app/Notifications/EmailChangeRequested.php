<?php

namespace App\Notifications;

use App\Support\EmailLocaleResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeRequested extends Notification
{
    use Queueable;

    public function __construct(protected string $newEmail)
    {
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
        $appName = config('app.name');

        return (new MailMessage)
            ->subject($isEnglish ? 'Email address change requested' : 'Änderung deiner E-Mail-Adresse angefordert')
            ->line($isEnglish
                ? "A request was made to change the email address of your {$appName} account to {$this->newEmail}."
                : "Es wurde angefordert, die E-Mail-Adresse deines {$appName}-Kontos auf {$this->newEmail} zu ändern.")
            ->line($isEnglish
                ? 'Your current email address will remain active until the new address is confirmed.'
                : 'Deine aktuelle E-Mail-Adresse bleibt aktiv, bis die neue Adresse bestätigt wurde.')
            ->line($isEnglish
                ? 'If this was not you, please secure your account and reset your password immediately.'
                : 'Wenn du das nicht warst, sichere bitte dein Konto und setze dein Passwort sofort zurück.');
    }
}
