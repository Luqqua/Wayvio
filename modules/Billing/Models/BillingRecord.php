<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class BillingRecord extends Model
{
    protected $fillable = [
        'user_id', 'stripe_payment_id', 'amount', 'currency', 'tier_id', 'period_months',
    ];
}
