<?php

namespace App\Notifications;

use App\Support\EmailLocaleResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
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
        $appName = config('app.name');

        return (new MailMessage)
            ->subject($isEnglish ? 'Your password was changed' : 'Dein Passwort wurde geändert')
            ->line($isEnglish
                ? "This is a confirmation that your password for {$appName} was changed."
                : "Dies ist die Bestätigung, dass dein Passwort für {$appName} geändert wurde.")
            ->line($isEnglish
                ? 'If this was not you, please reset your password immediately.'
                : 'Wenn du das nicht warst, setze bitte sofort dein Passwort zurück.')
            ->action($isEnglish ? 'Reset password' : 'Passwort zurücksetzen', url(route('password.request')));
    }
}
