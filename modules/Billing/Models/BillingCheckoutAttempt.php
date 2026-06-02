<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCheckoutAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'tier_id',
        'period_months',
        'requested_hub_count',
        'stripe_checkout_session_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_invoice_id',
        'checkout_url',
        'source',
        'status',
        'webhook_status',
        'last_event_id',
        'last_event_type',
        'error_code',
        'error_message',
        'confirmed_at',
        'failed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
