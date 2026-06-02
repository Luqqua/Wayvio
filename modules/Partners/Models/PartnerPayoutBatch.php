<?php

namespace Modules\Partners\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerPayoutBatch extends Model
{
    protected $fillable = [
        'partner_user_id',
        'stripe_transfer_id',
        'transfer_group',
        'currency',
        'net_amount_cents',
        'status',
        'paid_at',
        'settled_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'settled_at' => 'datetime',
    ];
}
