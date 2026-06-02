<?php

return [
    /*
     | Retention period for the compliance_audit_log table in days.
     | Per DSGVO Art. 5 Abs. 1 lit. e (Speicherbegrenzung) muss eine definierte
     | Löschfrist bestehen. Default: 365 Tage.
     | Auf 0 setzen deaktiviert die automatische Löschung (nicht empfohlen).
     */
    'audit_log_retention_days' => (int) env('COMPLIANCE_AUDIT_LOG_RETENTION_DAYS', 365),

    /*
     | Retention periods for operational tables that may contain personal data
     | such as IP addresses, user agents, request metadata, webhook payloads or
     | notification payloads. These values are enforced by
     | `privacy:prune-operational-data`.
     */
    'operational_retention' => [
        'sessions_hours' => max(1, (int) env('PRIVACY_SESSION_RETENTION_HOURS', (int) env('SESSION_LIFETIME', 120) / 60 + 24)),
        'audit_log_days' => max(1, (int) env('PRIVACY_AUDIT_LOG_RETENTION_DAYS', 365)),
        'admin_audit_log_days' => max(1, (int) env('PRIVACY_ADMIN_AUDIT_LOG_RETENTION_DAYS', 365)),
        'partner_audit_log_days' => max(1, (int) env('PRIVACY_PARTNER_AUDIT_LOG_RETENTION_DAYS', 365)),
        'account_deletion_audit_log_days' => max(1, (int) env('PRIVACY_ACCOUNT_DELETION_AUDIT_LOG_RETENTION_DAYS', 365)),
        'billing_webhook_payload_redaction_days' => max(1, (int) env('PRIVACY_BILLING_WEBHOOK_PAYLOAD_REDACTION_DAYS', 90)),
        'stripe_webhook_payload_redaction_days' => max(1, (int) env('PRIVACY_STRIPE_WEBHOOK_PAYLOAD_REDACTION_DAYS', 90)),
        'partner_webhook_payload_redaction_days' => max(1, (int) env('PRIVACY_PARTNER_WEBHOOK_PAYLOAD_REDACTION_DAYS', 90)),
        'billing_webhook_event_days' => max(1, (int) env('PRIVACY_BILLING_WEBHOOK_RETENTION_DAYS', 730)),
        'stripe_webhook_log_days' => max(1, (int) env('PRIVACY_STRIPE_WEBHOOK_LOG_RETENTION_DAYS', 730)),
        'partner_webhook_event_days' => max(1, (int) env('PRIVACY_PARTNER_WEBHOOK_RETENTION_DAYS', 730)),
        'billing_notification_outbox_days' => max(1, (int) env('PRIVACY_BILLING_NOTIFICATION_OUTBOX_RETENTION_DAYS', 365)),
        'admin_login_attempt_hours' => max(1, (int) env('PRIVACY_ADMIN_LOGIN_ATTEMPT_RETENTION_HOURS', 24)),
    ],
];
