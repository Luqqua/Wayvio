<?php

namespace App\Notifications;

use App\Support\EmailLocaleResolver;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingSubscriptionEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $templateKey,
        private readonly array $payload = [],
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $isGerman = $this->isGerman();
        $dashboardUrl = url('/dashboard/subscription');
        $supportEmail = (string) config('billing.notifications.support_email', 'support@wayvio.com');

        $userName = $this->stringValue('user_name');
        $planName = $this->stringValue('plan_name', $isGerman ? 'dein Plan' : 'your plan');
        $pendingPlanName = $this->stringValue('pending_plan_name', $isGerman ? 'der neue Plan' : 'the selected plan');
        $previousPlanName = $this->stringValue('previous_plan_name', $planName);
        $targetPlanName = $this->stringValue('target_plan_name', $isGerman ? 'Free' : 'Free');

        $effectiveAt = $this->formatDateTime($this->payload['effective_at'] ?? $this->payload['current_period_end'] ?? null, $isGerman);
        $deleteAt = $this->formatDateTime($this->payload['delete_at'] ?? $this->payload['delete_after_at'] ?? null, $isGerman);
        $downgradeAt = $this->formatDateTime($this->payload['downgrade_at'] ?? null, $isGerman);
        $resourceDeleteAt = $this->formatDateTime($this->payload['resource_delete_at'] ?? null, $isGerman);
        $graceEndsAt = $this->formatDateTime($this->payload['grace_ends_at'] ?? null, $isGerman);
        $retentionDays = max(1, (int) ($this->payload['retention_days'] ?? config('billing.lifecycle.downgrade_retention_days', 30)));
        $pendingDeletionGraceDays = max(1, (int) config('billing.lifecycle.pending_deletion_grace_days', 1));
        $billingRetentionYears = max(1, (int) ($this->payload['billing_retention_years'] ?? config('billing.lifecycle.billing_retention_years', 10)));

        $mail = (new MailMessage)
            ->greeting($isGerman
                ? ('Hallo' . ($userName !== '' ? ' ' . $userName : '') . ',')
                : ('Hi' . ($userName !== '' ? ' ' . $userName : '') . ','));

        switch ($this->templateKey) {
            case 'plan_downgrade_scheduled':
                $mail->subject($isGerman ? 'Dein Plan ändert sich bald' : 'Your plan change is scheduled')
                    ->line($isGerman
                        ? "Dein Plan wechselt von {$planName} zu {$pendingPlanName}."
                        : "Your plan will change from {$planName} to {$pendingPlanName}.");
                if ($effectiveAt) {
                    $mail->line($isGerman ? "Geplantes Datum: {$effectiveAt}." : "Scheduled date: {$effectiveAt}.");
                }
                $mail->line($isGerman
                    ? 'Du kannst vor dem Termin jederzeit wieder upgraden.'
                    : 'You can upgrade again before the effective date at any time.')
                    ->action($isGerman ? 'Plan verwalten' : 'Manage plan', $this->actionUrl('upgrade_url', $dashboardUrl));
                break;

            case 'plan_downgrade_executed':
                $mail->subject($isGerman ? 'Dein Plan wurde geändert' : 'Your plan has changed')
                    ->line($isGerman
                        ? "Dein Plan wurde von {$previousPlanName} auf {$planName} geändert."
                        : "Your plan was changed from {$previousPlanName} to {$planName}.");
                $mail->line($isGerman
                    ? "Die eingeschränkten Ressourcen bleiben noch {$retentionDays} Tage gesperrt und werden danach in die {$pendingDeletionGraceDays}-Tage-Löschfrist verschoben."
                    : "Restricted resources remain suspended for {$retentionDays} days and then move into the {$pendingDeletionGraceDays}-day deletion grace window.")
                    ->action($isGerman ? 'Jetzt upgraden' : 'Upgrade now', $this->actionUrl('upgrade_url', $dashboardUrl));
                break;

            case 'resources_pending_deletion_warning':
                $pendingDaysLabel = $pendingDeletionGraceDays . ' ' . ($isGerman
                    ? ($pendingDeletionGraceDays === 1 ? 'Tag' : 'Tagen')
                    : ($pendingDeletionGraceDays === 1 ? 'day' : 'days'));
                $mail->subject($isGerman
                    ? "Deine Daten werden in {$pendingDaysLabel} gelöscht"
                    : "Your data will be deleted in {$pendingDaysLabel}")
                    ->line($isGerman
                        ? 'Ein Teil deiner gesperrten Ressourcen ist jetzt zur Löschung vorgemerkt.'
                        : 'Some suspended resources are now pending deletion.');
                if ($deleteAt) {
                    $mail->line($isGerman ? "Geplante Löschung: {$deleteAt}." : "Scheduled deletion: {$deleteAt}.");
                }
                $mail->action($isGerman ? 'Upgrade ausführen' : 'Upgrade now', $this->actionUrl('upgrade_url', $dashboardUrl));
                break;

            case 'resources_deleted_confirmation':
                $mail->subject($isGerman ? 'Deine Daten wurden gelöscht' : 'Your data has been deleted')
                    ->line($isGerman
                        ? 'Die vorgemerkten Ressourcen wurden endgültig gelöscht.'
                        : 'The pending resources were permanently deleted.');
                if ($deleteAt) {
                    $mail->line($isGerman ? "Löschzeitpunkt: {$deleteAt}." : "Deletion time: {$deleteAt}.");
                }
                $mail->action($isGerman ? 'Abo ansehen' : 'View subscription', $dashboardUrl);
                break;

            case 'agency_hub_quota_exceeded':
                $currentHubs = (int) ($this->payload['current_hubs'] ?? 0);
                $allowedHubs = (int) ($this->payload['allowed_hubs'] ?? 0);
                $overQuota = (int) ($this->payload['over_quota'] ?? 0);

                $mail->subject($isGerman ? 'Bitte wähle Hub-Deaktivierungen' : 'Please choose hubs to deactivate')
                    ->line($isGerman
                        ? "Du hast aktuell {$currentHubs} Hubs, dein Plan erlaubt {$allowedHubs}."
                        : "You currently have {$currentHubs} hubs, but your plan allows {$allowedHubs}.")
                    ->line($isGerman
                        ? "Bitte wähle {$overQuota} Hub(s), die deaktiviert werden sollen."
                        : "Please select {$overQuota} hub(s) to deactivate.");
                if ($graceEndsAt) {
                    $mail->line($isGerman
                        ? "Automatische Deaktivierung nach Ablauf der Frist: {$graceEndsAt}."
                        : "Automatic deactivation after the grace period: {$graceEndsAt}.");
                }
                $mail->action($isGerman ? 'Hubs verwalten' : 'Manage hubs', $this->actionUrl('manage_hubs_url', $dashboardUrl));
                break;

            case 'agency_hub_suspended_by_user':
                $hubName = $this->stringValue('hub_name', $isGerman ? 'Hub' : 'Hub');
                $mail->subject($isGerman ? "Hub {$hubName} wurde deaktiviert" : "Hub {$hubName} was deactivated")
                    ->line($isGerman
                        ? 'Der Hub ist öffentlich nicht mehr erreichbar, Inhalte bleiben erhalten.'
                        : 'The hub is no longer publicly reachable, and all content is preserved.')
                    ->action($isGerman ? 'Hubs verwalten' : 'Manage hubs', $this->actionUrl('manage_hubs_url', $dashboardUrl));
                break;

            case 'agency_hub_deleted_by_user':
                $hubName = $this->stringValue('hub_name', $isGerman ? 'Hub' : 'Hub');
                $mail->subject($isGerman ? "Hub {$hubName} wurde gelöscht" : "Hub {$hubName} was deleted")
                    ->line($isGerman
                        ? 'Der Hub wurde dauerhaft gelöscht. Inhalte und Hub-Daten wurden entfernt.'
                        : 'The hub was permanently deleted. Content and hub data were removed.')
                    ->action($isGerman ? 'Hubs verwalten' : 'Manage hubs', $this->actionUrl('manage_hubs_url', $dashboardUrl));
                break;

            case 'agency_hub_suspended_automatically':
                $mail->subject($isGerman ? 'Hub wurde automatisch deaktiviert' : 'Hub was automatically deactivated')
                    ->line($isGerman
                        ? 'Die Schonfrist ist abgelaufen. Mindestens ein Hub wurde automatisch deaktiviert.'
                        : 'The grace period expired. At least one hub was automatically deactivated.')
                    ->line($isGerman
                        ? 'Inhalte bleiben vollständig erhalten und sind nach Upgrade wieder aktivierbar.'
                        : 'All hub content is preserved and can be reactivated after an upgrade.')
                    ->action($isGerman ? 'Hubs ansehen' : 'View hubs', $this->actionUrl('manage_hubs_url', $dashboardUrl));
                break;

            case 'agency_hub_reactivated_by_user':
                $hubName = $this->stringValue('hub_name', $isGerman ? 'Hub' : 'Hub');
                $mail->subject($isGerman ? "Hub {$hubName} wurde reaktiviert" : "Hub {$hubName} was reactivated")
                    ->line($isGerman
                        ? 'Der Hub ist wieder aktiv und öffentlich erreichbar.'
                        : 'The hub is active again and publicly reachable.')
                    ->action($isGerman ? 'Hubs verwalten' : 'Manage hubs', $this->actionUrl('manage_hubs_url', $dashboardUrl));
                break;

            case 'payment_failed_warning':
            case 'renewal_payment_failed_action_required':
                $mail->subject($isGerman
                    ? 'Zahlung fehlgeschlagen: Bitte Zahlungsmethode aktualisieren'
                    : 'Payment failed: please update your payment method')
                    ->line($isGerman
                        ? 'Wir konnten deine letzte Zahlung nicht abbuchen.'
                        : 'We could not collect your latest payment.')
                    ->line($isGerman
                        ? 'Bitte aktualisiere jetzt deine Zahlungsmethode, um Einschränkungen zu vermeiden.'
                        : 'Please update your payment method now to avoid restrictions.')
                    ->action($isGerman ? 'Zahlung aktualisieren' : 'Update payment method', $this->actionUrl('billing_portal_url', $dashboardUrl));
                break;

            case 'account_suspended_warning':
                $mail->subject($isGerman ? 'Dein Account wurde eingeschränkt' : 'Your account is now restricted')
                    ->line($isGerman
                        ? 'Öffentliche Hubs und Domains wurden vorübergehend deaktiviert.'
                        : 'Public hubs and domains were temporarily suspended.');
                if ($resourceDeleteAt) {
                    $mail->line($isGerman
                        ? "Bei ausbleibender Zahlung werden nicht enthaltene Ressourcen gelöscht ab: {$resourceDeleteAt}."
                        : "If payment remains unresolved, resources not included in your plan will be deleted starting: {$resourceDeleteAt}.");
                }
                $mail->action($isGerman ? 'Jetzt bezahlen' : 'Pay now', $this->actionUrl('billing_portal_url', $dashboardUrl));
                break;

            case 'non_payment_downgrade_warning':
                $mail->subject($isGerman
                    ? 'Letzte Erinnerung: Ressourcen-Löschung steht bevor'
                    : 'Final reminder: resource deletion is approaching')
                    ->line($isGerman
                        ? 'Dein Account weist weiterhin offene Zahlungen auf.'
                        : 'Your account is still unpaid.');
                if ($resourceDeleteAt || $downgradeAt) {
                    $plannedAt = $resourceDeleteAt ?: $downgradeAt;
                    $mail->line($isGerman ? "Geplante Löschung: {$plannedAt}." : "Planned deletion: {$plannedAt}.");
                }
                $mail->action($isGerman ? 'Zahlung abschließen' : 'Complete payment', $this->actionUrl('billing_portal_url', $dashboardUrl));
                break;

            case 'non_payment_downgraded_to_free':
                $mail->subject($isGerman
                    ? "Dein Tarif wurde auf {$targetPlanName} umgestellt"
                    : "Your plan was downgraded to {$targetPlanName}")
                    ->line($isGerman
                        ? 'Dein Account bleibt bestehen. Kostenpflichtige Funktionen wurden eingeschränkt.'
                        : 'Your account remains active. Paid features were restricted.');
                if ($resourceDeleteAt) {
                    $mail->line($isGerman
                        ? "Nicht mehr enthaltene Ressourcen werden gelöscht ab: {$resourceDeleteAt}."
                        : "Resources not included in your plan will be deleted starting: {$resourceDeleteAt}.");
                }
                $mail->action($isGerman ? 'Abo verwalten' : 'Manage subscription', $this->actionUrl('billing_portal_url', $dashboardUrl));
                break;

            case 'deletion_imminent_warning':
                $mail->subject($isGerman
                    ? 'Letzte Erinnerung: Deine Daten werden in 30 Tagen gelöscht'
                    : 'Final reminder: your data will be deleted in 30 days')
                    ->line($isGerman
                        ? 'Dein Account weist weiterhin offene Zahlungen auf.'
                        : 'Your account is still unpaid.');
                if ($deleteAt) {
                    $mail->line($isGerman ? "Geplante Löschung: {$deleteAt}." : "Planned deletion: {$deleteAt}.");
                }
                $mail->action($isGerman ? 'Zahlung abschließen' : 'Complete payment', $this->actionUrl('billing_portal_url', $dashboardUrl));
                break;

            case 'deletion_final_warning':
                $mail->subject($isGerman ? 'Deine Daten werden in 7 Tagen gelöscht' : 'Your data will be deleted in 7 days')
                    ->line($isGerman
                        ? 'Dies ist die letzte Frist vor endgültiger Löschung.'
                        : 'This is the final warning before permanent deletion.');
                if ($deleteAt) {
                    $mail->line($isGerman ? "Löschung am: {$deleteAt}." : "Deletion at: {$deleteAt}.");
                }
                $mail->action($isGerman ? 'Jetzt bezahlen' : 'Pay now', $this->actionUrl('billing_portal_url', $dashboardUrl));
                break;

            case 'account_deleted_confirmation':
                $mail->subject($isGerman ? 'Dein Account wurde gelöscht' : 'Your account has been deleted')
                    ->line($isGerman
                        ? 'Dein Account und zugehörige Ressourcen wurden gelöscht oder anonymisiert.'
                        : 'Your account and related resources were deleted or anonymized.')
                    ->line($isGerman
                        ? "Rechnungsdaten bleiben aus steuerlichen Gründen {$billingRetentionYears} Jahre gespeichert."
                        : "Billing records are retained for {$billingRetentionYears} years for tax compliance.");
                break;

            case 'account_reactivated_confirmation':
                $mail->subject($isGerman ? 'Dein Account wurde reaktiviert' : 'Your account has been reactivated')
                    ->line($isGerman
                        ? 'Zahlung erfolgreich. Account, Hubs und Domains wurden wieder freigeschaltet.'
                        : 'Payment successful. Account, hubs, and domains were reactivated.')
                    ->action($isGerman ? 'Abo anzeigen' : 'View subscription', $dashboardUrl);
                break;

            case 'subscription_started':
                $mail->subject($isGerman ? 'Dein Abo ist aktiv' : 'Your subscription is active')
                    ->line($isGerman ? "Dein {$planName}-Abo ist jetzt aktiv." : "Your {$planName} subscription is now active.");
                if ($effectiveAt) {
                    $mail->line($isGerman ? "Aktueller Zeitraum bis {$effectiveAt}." : "Current period until {$effectiveAt}.");
                }
                $mail->action($isGerman ? 'Abo verwalten' : 'Manage subscription', $dashboardUrl);
                break;

            case 'plan_upgraded_now':
                $mail->subject($isGerman ? 'Dein Upgrade ist aktiv' : 'Your upgrade is active')
                    ->line($isGerman
                        ? "Dein Plan wurde von {$previousPlanName} auf {$planName} geändert."
                        : "Your plan changed from {$previousPlanName} to {$planName}.")
                    ->action($isGerman ? 'Abo ansehen' : 'View subscription', $dashboardUrl);
                break;

            case 'cancellation_scheduled':
                $mail->subject($isGerman ? 'Deine Kündigung ist geplant' : 'Your cancellation is scheduled')
                    ->line($isGerman
                        ? 'Deine Kündigung ist zum Ende des Abrechnungszeitraums geplant.'
                        : 'Your cancellation is scheduled for the end of the billing period.');
                if ($effectiveAt) {
                    $mail->line($isGerman ? "Aktiv bis {$effectiveAt}." : "Access remains active until {$effectiveAt}.");
                }
                $mail->action($isGerman ? 'Abo verwalten' : 'Manage subscription', $dashboardUrl);
                break;

            case 'subscription_renewed':
                $mail->subject($isGerman ? 'Dein Abo wurde verlängert' : 'Your subscription has been renewed')
                    ->line($isGerman ? "Dein {$planName}-Abo wurde erfolgreich verlängert." : "Your {$planName} subscription renewed successfully.");
                if ($effectiveAt) {
                    $mail->line($isGerman ? "Nächste Verlängerung: {$effectiveAt}." : "Next renewal: {$effectiveAt}.");
                }
                $mail->action($isGerman ? 'Abo ansehen' : 'View billing', $dashboardUrl);
                break;

            case 'subscription_ended_downgraded_to_free':
                $mail->subject($isGerman ? 'Dein Abo wurde beendet' : 'Your subscription ended')
                    ->line($isGerman
                        ? "Dein {$previousPlanName}-Abo wurde beendet und auf Free gesetzt."
                        : "Your {$previousPlanName} subscription ended and has been moved to the Free plan.")
                    ->action($isGerman ? 'Abo ansehen' : 'View subscription', $dashboardUrl);
                break;

            default:
                $mail->subject($isGerman ? 'Abo-Update' : 'Subscription update')
                    ->line($isGerman ? 'Dein Abo wurde aktualisiert.' : 'Your subscription was updated.')
                    ->action($isGerman ? 'Abo ansehen' : 'View subscription', $dashboardUrl);
                break;
        }

        if (trim($supportEmail) !== '') {
            $mail->line($isGerman ? "Support: {$supportEmail}" : "Support: {$supportEmail}");
        }

        return $mail;
    }

    private function actionUrl(string $payloadKey, string $fallback): string
    {
        $candidate = trim((string) ($this->payload[$payloadKey] ?? ''));
        if ($candidate === '') {
            return $fallback;
        }

        if (!str_starts_with($candidate, 'http://') && !str_starts_with($candidate, 'https://')) {
            return $fallback;
        }

        return $candidate;
    }

    private function isGerman(): bool
    {
        $locale = EmailLocaleResolver::resolve((string) ($this->payload['locale'] ?? null));

        return !EmailLocaleResolver::isEnglish($locale);
    }

    private function stringValue(string $key, string $fallback = ''): string
    {
        $value = $this->payload[$key] ?? null;
        if ($value === null) {
            return $fallback;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : $fallback;
    }

    private function formatDateTime(mixed $value, bool $isGerman): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $isGerman
                ? $date->format('d.m.Y H:i') . ' ' . config('app.timezone')
                : $date->format('M j, Y H:i') . ' ' . config('app.timezone');
        } catch (\Throwable) {
            return null;
        }
    }
}
