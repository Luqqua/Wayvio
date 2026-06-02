<?php

namespace Modules\Partners\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerAccount extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'default_commission_rate_bps',
        'stripe_connect_account_id',
        'legal_country',
        'onboarding_started_at',
        'activated_at',
        'stripe_requirements',
        'payout_minimum_cents',
    ];

    protected $casts = [
        'onboarding_started_at' => 'datetime',
        'activated_at' => 'datetime',
        'stripe_requirements' => 'array',
    ];
}
