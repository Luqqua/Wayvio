<?php

namespace App\Notifications;

use App\Support\EmailLocaleResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorDisabledByAdminNotification extends Notification
{
    use Queueable;

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

        return (new MailMessage)
            ->subject($isEnglish
                ? 'Two-factor authentication was disabled for your account'
                : 'Die Zwei-Faktor-Authentifizierung wurde für dein Konto deaktiviert')
            ->line($isEnglish
                ? 'Two-factor authentication for your account was disabled by an administrator.'
                : 'Die Zwei-Faktor-Authentifizierung deines Kontos wurde von einem Administrator deaktiviert.')
            ->line($isEnglish
                ? 'If you did not request this, please log in, change your password, and re-enable 2FA.'
                : 'Wenn du das nicht angefordert hast, melde dich bitte an, ändere dein Passwort und aktiviere 2FA erneut.')
            ->action($isEnglish ? 'Log in' : 'Anmelden', url('/login'));
    }
}
