<?php

$manualOnly = filter_var(env('PARTNERS_MANUAL_ONLY', true), FILTER_VALIDATE_BOOLEAN);
$autoApproveDefault = $manualOnly ? false : true;
$showPartnerCodeFieldAtRegistration = in_array(
    strtolower(trim((string) env('SHOW_PARTNER_CODE_FIELD_AT_REGISTRATION', 'no'))),
    ['1', 'true', 'yes', 'on'],
    true
);

return [
    'web_dashboard_enabled' => filter_var(
        env('PARTNERS_WEB_DASHBOARD_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),
    'manual_only' => $manualOnly,
    'auto_approve_commissions' => filter_var(
        env('PARTNERS_AUTO_APPROVE_COMMISSIONS', $autoApproveDefault),
        FILTER_VALIDATE_BOOLEAN
    ),
    'show_partner_code_field_at_registration' => $showPartnerCodeFieldAtRegistration,
    'payment_gate' => [
        // Security-first default: legacy rows without billing reason metadata do not unlock the payment gate.
        'allow_legacy_null_meta' => filter_var(
            env('PARTNER_PAYMENT_GATE_ALLOW_LEGACY_NULL_META', false),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
    'payout_settle' => [
        'require_verified_transfer' => filter_var(
            env('PARTNERS_PAYOUT_SETTLE_REQUIRE_VERIFIED_TRANSFER', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
