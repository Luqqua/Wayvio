<?php

return [
    'notifications' => [
        'enabled' => filter_var(env('BILLING_NOTIFICATION_EMAILS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'batch_size' => max(1, (int) env('BILLING_NOTIFICATION_BATCH_SIZE', 100)),
        'max_attempts' => max(1, (int) env('BILLING_NOTIFICATION_MAX_ATTEMPTS', 5)),
        'send_delay_ms' => max(0, (int) env('BILLING_NOTIFICATION_SEND_DELAY_MS', 0)),
        'support_email' => env('BILLING_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
    ],
    'lifecycle' => [
        // Days after suspension until pending_deletion. Applies to both non-payment and voluntary downgrade flows.
        'downgrade_retention_days' => max(1, (int) env('BILLING_LIFECYCLE_DOWNGRADE_RETENTION_DAYS', 30)),
        // Additional grace after pending_deletion before final delete.
        'pending_deletion_grace_days' => max(1, (int) env('BILLING_LIFECYCLE_PENDING_DELETION_GRACE_DAYS', 1)),
        'deleted_account_evidence_retention_days' => max(1, (int) env('BILLING_LIFECYCLE_DELETED_ACCOUNT_EVIDENCE_RETENTION_DAYS', 365)),
        'billing_retention_years' => max(1, (int) env('BILLING_LIFECYCLE_BILLING_RETENTION_YEARS', 10)),
        'admin_delete_strategy' => env('BILLING_LIFECYCLE_ADMIN_DELETE_STRATEGY', 'finalize_immediately'),
        'agency_hub_auto_cleanup_enabled' => filter_var(env('BILLING_AGENCY_HUB_AUTO_CLEANUP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'deleted_account_purge_enabled' => filter_var(env('BILLING_LIFECYCLE_PURGE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'deleted_account_purge_batch_size' => max(1, (int) env('BILLING_LIFECYCLE_PURGE_BATCH_SIZE', 200)),
    ],
];
